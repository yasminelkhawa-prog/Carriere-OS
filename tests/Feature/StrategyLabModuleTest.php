<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiRequestJob;
use App\Models\AiRequest;
use App\Models\Application;
use App\Models\ApplicationScoring;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\StrategyLabAiSummary;
use App\Models\StrategyLabBrief;
use App\Models\StrategyLabSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StrategyLabModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_requires_shortlisted_stage(): void
    {
        $context = $this->createContext('screening');

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.strategy-lab.assign', ['application' => $context['application']->id]), [
                'brief_title' => 'Go-to-market mini project',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', __('strategy_lab.messages.shortlist_required'));

        $this->assertFalse(
            StrategyLabBrief::withoutGlobalScopes()
                ->where('application_id', $context['application']->id)
                ->exists()
        );
    }

    public function test_assign_for_shortlisted_candidate_creates_brief_and_queues_ai_generation(): void
    {
        Queue::fake();
        $context = $this->createContext('shortlist');
        Carbon::setTestNow('2026-03-08 10:00:00');

        try {
            $response = $this->actingAs($context['recruiter'])
                ->withSession(['active_company_id' => (string) $context['company']->id])
                ->post(route('candidates.strategy-lab.assign', ['application' => $context['application']->id]), [
                    'brief_title' => 'Growth strategy challenge',
                ]);

            $response->assertRedirect();
            $response->assertSessionHas('status', __('strategy_lab.messages.assigned'));

            $brief = StrategyLabBrief::withoutGlobalScopes()
                ->where('application_id', $context['application']->id)
                ->first();

            $this->assertNotNull($brief);
            $this->assertSame(StrategyLabBrief::STATUS_ASSIGNED, (string) $brief?->status);
            $this->assertNotNull($brief?->generated_ai_request_id);
            $this->assertSame('2026-03-10 10:00:00', optional($brief?->deadline_at)->format('Y-m-d H:i:s'));

            $this->assertTrue(
                AiRequest::withoutGlobalScopes()
                    ->where('company_id', $context['company']->id)
                    ->where('request_type', 'strategy_lab_brief_generation')
                    ->whereRaw("request_payload->>'strategy_lab_brief_id' = ?", [(string) $brief?->id])
                    ->exists()
            );
        } finally {
            Carbon::setTestNow();
        }

        Queue::assertPushed(ProcessAiRequestJob::class);
    }

    public function test_assign_is_blocked_when_shortlisted_candidate_is_not_top_ten_percent(): void
    {
        $context = $this->createContext('shortlist');
        $this->seedPriorityCompetitors(
            company: $context['company'],
            job: $context['job'],
            stage: $context['stage'],
            scores: [99, 98, 97, 96, 95, 94, 93, 92, 91, 90]
        );

        ApplicationScoring::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('application_id', $context['application']->id)
            ->update([
                'global_match_score' => 10.0,
                'updated_at' => now(),
            ]);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.strategy-lab.assign', ['application' => $context['application']->id]), [
                'brief_title' => 'Top 10 only challenge',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', __('strategy_lab.messages.top_ten_required'));
        $this->assertFalse(
            StrategyLabBrief::withoutGlobalScopes()
                ->where('application_id', $context['application']->id)
                ->exists()
        );
    }

    public function test_assign_is_blocked_until_completed_zoom_interview(): void
    {
        $context = $this->createContext('shortlist', withCompletedZoomInterview: false);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.strategy-lab.assign', ['application' => $context['application']->id]), [
                'brief_title' => 'Needs completed Zoom interview',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', __('strategy_lab.messages.zoom_interview_required'));
        $this->assertFalse(
            StrategyLabBrief::withoutGlobalScopes()
                ->where('application_id', $context['application']->id)
                ->exists()
        );
    }

    public function test_assigned_brief_generates_personalized_pdf_after_ai_processing(): void
    {
        Storage::fake('local');
        $context = $this->createContext('shortlist');

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.strategy-lab.assign', ['application' => $context['application']->id]), [
                'brief_title' => 'Guerrilla marketing strategy with EUR0 budget',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', __('strategy_lab.messages.assigned'));

        $brief = StrategyLabBrief::withoutGlobalScopes()
            ->where('application_id', $context['application']->id)
            ->first();
        $this->assertNotNull($brief);

        $aiRequest = AiRequest::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('request_type', 'strategy_lab_brief_generation')
            ->whereRaw("request_payload->>'strategy_lab_brief_id' = ?", [(string) $brief?->id])
            ->latest('created_at')
            ->first();

        $this->assertNotNull($aiRequest);
        $prompt = (string) data_get($aiRequest?->request_payload, 'prompt', '');
        $this->assertStringContainsString('Job title: Strategy Role', $prompt);
        $this->assertStringContainsString('Candidate name: Strategy Candidate', $prompt);

        if (! is_string($brief?->brief_pdf_url) || trim((string) $brief?->brief_pdf_url) === '') {
            ProcessAiRequestJob::dispatchSync((string) $aiRequest?->id);
            $brief?->refresh();
        }

        $this->assertNotNull($brief?->brief_pdf_url);
        $this->assertStringContainsString('/'.$context['application']->id.'/', (string) $brief?->brief_pdf_url);
        Storage::disk('local')->assertExists((string) $brief?->brief_pdf_url);
    }

    public function test_candidate_portal_hides_strategy_lab_for_non_top_ten_candidate(): void
    {
        $context = $this->createContext('shortlist');
        $this->seedPriorityCompetitors(
            company: $context['company'],
            job: $context['job'],
            stage: $context['stage'],
            scores: [99, 98, 97, 96, 95, 94, 93, 92, 91, 90]
        );

        ApplicationScoring::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('application_id', $context['application']->id)
            ->update([
                'global_match_score' => 10.0,
                'updated_at' => now(),
            ]);

        StrategyLabBrief::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'brief_title' => 'Hidden for non-top10',
            'brief_pdf_url' => 'private/strategy-lab/briefs/hidden.pdf',
            'deadline_at' => now()->addHours(8),
            'status' => StrategyLabBrief::STATUS_ASSIGNED,
            'generated_ai_request_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertDontSee('Hidden for non-top10');
        $response->assertDontSee(__('candidate_portal.applications.next_step_strategy_lab'));
    }

    public function test_candidate_submission_is_blocked_after_deadline_and_allowed_after_extension(): void
    {
        Storage::fake('local');
        Queue::fake();
        $context = $this->createContext('shortlist');

        $brief = StrategyLabBrief::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'brief_title' => 'Deadline challenge',
            'brief_pdf_url' => 'private/strategy-lab/briefs/sample.pdf',
            'deadline_at' => now()->subHour(),
            'status' => StrategyLabBrief::STATUS_ASSIGNED,
            'generated_ai_request_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lateResponse = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.strategy-lab.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'submission_type' => StrategyLabSubmission::TYPE_PRESENTATION,
                'submission_file' => UploadedFile::fake()->create('late-solution.pptx', 1200),
            ]);

        $lateResponse->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $lateResponse->assertSessionHas('error', __('strategy_lab.messages.deadline_passed'));
        $this->assertFalse(
            StrategyLabSubmission::withoutGlobalScopes()
                ->where('application_id', $context['application']->id)
                ->exists()
        );

        $extendResponse = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.strategy-lab.extend-deadline', ['application' => $context['application']->id]), [
                'deadline_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ]);

        $extendResponse->assertRedirect();
        $extendResponse->assertSessionHas('status', __('strategy_lab.messages.deadline_extended'));

        $submitResponse = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.strategy-lab.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'submission_type' => StrategyLabSubmission::TYPE_DOCUMENT,
                'submission_file' => UploadedFile::fake()->create('solution.docx', 600),
            ]);

        $submitResponse->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $submitResponse->assertSessionHas('status', __('strategy_lab.messages.submitted'));

        $submission = StrategyLabSubmission::withoutGlobalScopes()
            ->where('application_id', $context['application']->id)
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame(StrategyLabSubmission::TYPE_DOCUMENT, (string) $submission?->submission_type);
        $this->assertNotNull($submission?->submitted_at);
        Storage::disk('local')->assertExists((string) $submission?->file_url);

        $brief->refresh();
        $this->assertSame(StrategyLabBrief::STATUS_SUBMITTED, (string) $brief->status);

        $summaryRequest = AiRequest::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('request_type', 'strategy_lab_executive_summary')
            ->whereRaw("request_payload->>'strategy_lab_submission_id' = ?", [(string) $submission?->id])
            ->latest('created_at')
            ->first();

        $this->assertNotNull($summaryRequest);
        $summaryPrompt = (string) data_get($summaryRequest?->request_payload, 'prompt', '');
        $this->assertStringContainsString('Submission excerpt:', $summaryPrompt);
        $this->assertStringContainsString('Summarize strategic strengths, weaknesses, and assign a creativity score on a 0-10 scale.', $summaryPrompt);
        $requiredSummaryFields = (array) data_get($summaryRequest?->request_payload, 'json_schema.required', []);
        $this->assertContains('overall_recommendation', $requiredSummaryFields);
        $this->assertSame(
            'string',
            data_get($summaryRequest?->request_payload, 'json_schema.properties.overall_recommendation.type')
        );
        Queue::assertPushed(ProcessAiRequestJob::class);
    }

    public function test_recruiter_can_mark_reviewed_once_submission_and_summary_exist(): void
    {
        $context = $this->createContext('shortlist');

        $brief = StrategyLabBrief::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'brief_title' => 'Review challenge',
            'brief_pdf_url' => 'private/strategy-lab/briefs/review.pdf',
            'deadline_at' => now()->addDay(),
            'status' => StrategyLabBrief::STATUS_SUBMITTED,
            'generated_ai_request_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        StrategyLabSubmission::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'submission_type' => StrategyLabSubmission::TYPE_PRESENTATION,
            'file_url' => 'private/strategy-lab/submissions/submission.pptx',
            'original_filename' => 'submission.pptx',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        StrategyLabAiSummary::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'executive_summary_text' => 'Strong strategic sequencing with measurable milestones.',
            'strengths_json' => ['Clear milestones', 'Good stakeholder mapping'],
            'weaknesses_json' => ['Budget needs refinement'],
            'creativity_score' => 82.5,
            'overall_recommendation' => 'Proceed with recruiter final decision.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.strategy-lab.mark-reviewed', ['application' => $context['application']->id]));

        $response->assertRedirect();
        $response->assertSessionHas('status', __('strategy_lab.messages.reviewed'));

        $brief->refresh();
        $this->assertSame(StrategyLabBrief::STATUS_REVIEWED, (string) $brief->status);
    }

    public function test_recruiter_sets_final_decision_after_reviewed_strategy_lab(): void
    {
        $context = $this->createContext('shortlist');

        $brief = StrategyLabBrief::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'brief_title' => 'Final decision challenge',
            'brief_pdf_url' => 'private/strategy-lab/briefs/final-decision.pdf',
            'deadline_at' => now()->addDay(),
            'status' => StrategyLabBrief::STATUS_REVIEWED,
            'generated_ai_request_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        StrategyLabSubmission::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'submission_type' => StrategyLabSubmission::TYPE_DOCUMENT,
            'file_url' => 'private/strategy-lab/submissions/final-decision.docx',
            'original_filename' => 'final-decision.docx',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        StrategyLabAiSummary::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'executive_summary_text' => 'Promising strategy with clear sequencing.',
            'strengths_json' => ['Strong structure'],
            'weaknesses_json' => ['Needs budget realism'],
            'creativity_score' => 8.3,
            'overall_recommendation' => 'Proceed carefully with manual validation.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($context['recruiter'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->post(route('candidates.strategy-lab.final-decision', ['application' => $context['application']->id]), [
                'decision_status' => StrategyLabBrief::DECISION_APPROVED,
                'decision_note' => 'Validated after checking assumptions.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', __('strategy_lab.messages.final_decision_saved'));

        $brief->refresh();
        $this->assertSame(StrategyLabBrief::DECISION_APPROVED, (string) $brief->final_decision_status);
        $this->assertSame('Validated after checking assumptions.', (string) $brief->final_decision_note);
        $this->assertSame((string) $context['recruiter']->id, (string) $brief->final_decision_by_user_id);
        $this->assertNotNull($brief->final_decision_at);
    }

    public function test_candidate_portal_displays_strategy_lab_workspace_when_assigned(): void
    {
        $context = $this->createContext('shortlist');

        StrategyLabBrief::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'brief_title' => 'Workspace visible challenge',
            'brief_pdf_url' => 'private/strategy-lab/briefs/workspace.pdf',
            'deadline_at' => now()->addDays(2),
            'status' => StrategyLabBrief::STATUS_ASSIGNED,
            'generated_ai_request_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertSee(__('strategy_lab.candidate.title'));
        $response->assertSee('Workspace visible challenge');
        $response->assertSee(__('strategy_lab.actions.download_brief'));
        $response->assertSee('data-strategy-countdown', false);
        $response->assertSee('data-strategy-dropzone', false);
    }

    public function test_candidate_portal_shows_final_stage_notification_when_strategy_lab_unlocks(): void
    {
        $context = $this->createContext('shortlist');

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertSee(__('candidate_portal.notifications.event_messages.strategy_lab_unlocked'));
    }

    public function test_strategy_lab_files_require_signed_urls_and_owner_scope(): void
    {
        Storage::fake('local');
        $context = $this->createContext('shortlist');

        $briefPath = "private/strategy-lab/briefs/{$context['company']->id}/{$context['application']->id}/sample.pdf";
        Storage::disk('local')->put($briefPath, 'brief-content');

        $brief = StrategyLabBrief::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'brief_title' => 'Signed URL challenge',
            'brief_pdf_url' => $briefPath,
            'deadline_at' => now()->addDay(),
            'status' => StrategyLabBrief::STATUS_SUBMITTED,
            'generated_ai_request_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submissionPath = "private/strategy-lab/submissions/{$context['company']->id}/{$context['application']->id}/sample.docx";
        Storage::disk('local')->put($submissionPath, 'submission-content');

        $submission = StrategyLabSubmission::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'submission_type' => StrategyLabSubmission::TYPE_DOCUMENT,
            'file_url' => $submissionPath,
            'original_filename' => 'sample.docx',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ownerBriefResponse = $this->actingAs($context['candidateUser'])
            ->get(\App\Http\Controllers\StrategyLabController::signedBriefUrl($brief));
        $ownerBriefResponse->assertOk();

        $ownerSubmissionResponse = $this->actingAs($context['candidateUser'])
            ->get(\App\Http\Controllers\StrategyLabController::signedSubmissionUrl($submission));
        $ownerSubmissionResponse->assertOk();

        $unsignedBriefResponse = $this->actingAs($context['candidateUser'])
            ->get(route('media.strategy-lab-brief', ['strategyLabBrief' => $brief->id]));
        $unsignedBriefResponse->assertRedirect(route('home'));
        $unsignedBriefResponse->assertSessionHas('error', __('strategy_lab.errors.download_forbidden'));

        $otherUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        CompanyMembership::query()->create([
            'company_id' => $context['company']->id,
            'user_id' => $otherUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);
        Candidate::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'user_id' => $otherUser->id,
            'full_name' => 'Other Candidate',
            'email' => (string) $otherUser->email,
        ]);

        $forbiddenBriefResponse = $this->actingAs($otherUser)
            ->get(\App\Http\Controllers\StrategyLabController::signedBriefUrl($brief));
        $forbiddenBriefResponse->assertRedirect(route('home'));
        $forbiddenBriefResponse->assertSessionHas('error', __('strategy_lab.errors.download_forbidden'));

        $forbiddenSubmissionResponse = $this->actingAs($otherUser)
            ->get(\App\Http\Controllers\StrategyLabController::signedSubmissionUrl($submission));
        $forbiddenSubmissionResponse->assertRedirect(route('home'));
        $forbiddenSubmissionResponse->assertSessionHas('error', __('strategy_lab.errors.download_forbidden'));
    }

    public function test_signed_strategy_lab_downloads_are_blocked_for_owner_when_not_top_ten_percent(): void
    {
        Storage::fake('local');
        $context = $this->createContext('shortlist');
        $this->seedPriorityCompetitors(
            company: $context['company'],
            job: $context['job'],
            stage: $context['stage'],
            scores: [99, 98, 97, 96, 95, 94, 93, 92, 91, 90]
        );

        ApplicationScoring::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('application_id', $context['application']->id)
            ->update([
                'global_match_score' => 10.0,
                'updated_at' => now(),
            ]);

        $briefPath = "private/strategy-lab/briefs/{$context['company']->id}/{$context['application']->id}/ineligible.pdf";
        Storage::disk('local')->put($briefPath, 'brief-content');
        $brief = StrategyLabBrief::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'brief_title' => 'Ineligible brief',
            'brief_pdf_url' => $briefPath,
            'deadline_at' => now()->addHours(3),
            'status' => StrategyLabBrief::STATUS_ASSIGNED,
            'generated_ai_request_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $submissionPath = "private/strategy-lab/submissions/{$context['company']->id}/{$context['application']->id}/ineligible.docx";
        Storage::disk('local')->put($submissionPath, 'submission-content');
        $submission = StrategyLabSubmission::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'submission_type' => StrategyLabSubmission::TYPE_DOCUMENT,
            'file_url' => $submissionPath,
            'original_filename' => 'ineligible.docx',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $briefResponse = $this->actingAs($context['candidateUser'])
            ->get(\App\Http\Controllers\StrategyLabController::signedBriefUrl($brief));
        $briefResponse->assertRedirect(route('home'));
        $briefResponse->assertSessionHas('error', __('strategy_lab.errors.download_forbidden'));

        $submissionResponse = $this->actingAs($context['candidateUser'])
            ->get(\App\Http\Controllers\StrategyLabController::signedSubmissionUrl($submission));
        $submissionResponse->assertRedirect(route('home'));
        $submissionResponse->assertSessionHas('error', __('strategy_lab.errors.download_forbidden'));
    }

    public function test_candidate_submission_validates_file_rules_and_requires_ready_brief(): void
    {
        Storage::fake('local');
        $context = $this->createContext('shortlist');

        StrategyLabBrief::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['application']->id,
            'brief_title' => 'Not ready brief',
            'brief_pdf_url' => null,
            'deadline_at' => now()->addDay(),
            'status' => StrategyLabBrief::STATUS_ASSIGNED,
            'generated_ai_request_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notReadyResponse = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.strategy-lab.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'submission_type' => StrategyLabSubmission::TYPE_DOCUMENT,
                'submission_file' => UploadedFile::fake()->create('solution.docx', 600),
            ]);

        $notReadyResponse->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $notReadyResponse->assertSessionHas('error', __('strategy_lab.messages.brief_processing'));

        StrategyLabBrief::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('application_id', $context['application']->id)
            ->update([
                'brief_pdf_url' => "private/strategy-lab/briefs/{$context['company']->id}/{$context['application']->id}/ready.pdf",
            ]);

        $invalidTypeResponse = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.portal', ['company' => $context['company']->slug]))
            ->post(route('candidate.strategy-lab.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'submission_type' => StrategyLabSubmission::TYPE_DOCUMENT,
                'submission_file' => UploadedFile::fake()->create('payload.exe', 100),
            ]);

        $invalidTypeResponse->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $invalidTypeResponse->assertSessionHasErrors([
            'submission_file' => __('strategy_lab.validation.file_extensions'),
        ]);

        $oversizedResponse = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.portal', ['company' => $context['company']->slug]))
            ->post(route('candidate.strategy-lab.submit', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'submission_type' => StrategyLabSubmission::TYPE_DOCUMENT,
                'submission_file' => UploadedFile::fake()->create('oversized.docx', 21000),
            ]);

        $oversizedResponse->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $oversizedResponse->assertSessionHasErrors([
            'submission_file' => __('strategy_lab.validation.file_max'),
        ]);
    }

    /**
     * @return array{
     *   company: Company,
     *   recruiter: User,
     *   candidateUser: User,
     *   candidate: Candidate,
     *   job: Job,
     *   stage: JobPipelineStage,
     *   application: Application
     * }
     */
    private function createContext(string $stageKey, bool $withCompletedZoomInterview = true): array
    {
        $company = Company::query()->create([
            'name' => 'Strategy Lab Company',
            'slug' => 'strategy-lab-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $recruiter = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $recruiter->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $candidateUser = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Strategy Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $stage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => $stageKey,
            'stage_label' => ucfirst($stageKey),
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $candidate = Candidate::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'full_name' => 'Strategy Candidate',
            'email' => (string) $candidateUser->email,
        ]);

        $application = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $stage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        ApplicationScoring::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'global_match_score' => 88.0,
            'vrin_json' => ['acquired_skills' => ['Strategy'], 'missing_skills' => []],
            'xai_summary' => 'Strong strategic fit.',
            'updated_at' => now(),
        ]);

        if ($withCompletedZoomInterview) {
            Interview::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'interview_type' => 'final',
                'scheduled_start_at' => now()->subDay(),
                'scheduled_end_at' => now()->subDay()->addHour(),
                'timezone' => 'UTC',
                'location_type' => Interview::LOCATION_ZOOM,
                'meeting_link' => 'https://zoom.us/j/123456789',
                'status' => Interview::STATUS_COMPLETED,
                'created_by_user_id' => $recruiter->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return compact('company', 'recruiter', 'candidateUser', 'candidate', 'job', 'stage', 'application');
    }

    /**
     * @param array<int, float|int> $scores
     */
    private function seedPriorityCompetitors(Company $company, Job $job, JobPipelineStage $stage, array $scores): void
    {
        foreach ($scores as $index => $score) {
            $user = User::factory()->create([
                'email' => sprintf('strategy-competitor-%d@example.test', $index + 1),
                'email_verified_at' => now(),
            ]);

            $candidate = Candidate::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'full_name' => sprintf('Strategy Competitor %02d', $index + 1),
                'email' => (string) $user->email,
            ]);

            CompanyMembership::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'company_role' => CompanyMembership::ROLE_CANDIDATE,
                'membership_status' => CompanyMembership::STATUS_ACTIVE,
            ]);

            $application = Application::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'candidate_id' => $candidate->id,
                'job_id' => $job->id,
                'current_stage_id' => $stage->id,
                'status' => Application::STATUS_ACTIVE,
                'source_type' => 'referral',
            ]);

            ApplicationScoring::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'global_match_score' => (float) $score,
                'vrin_json' => ['acquired_skills' => ['Strategy'], 'missing_skills' => []],
                'xai_summary' => 'Ranked competitor profile.',
                'updated_at' => now(),
            ]);
        }
    }
}
