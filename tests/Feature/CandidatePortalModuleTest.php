<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\ApplicationScoring;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyValue;
use App\Models\Contract;
use App\Models\FaqItem;
use App\Models\Interview;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Offer;
use App\Models\OnboardingTask;
use App\Models\ReverseFeedback;
use App\Models\SjtResponse;
use App\Models\SjtScenario;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CandidatePortalModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidate_portal_shows_only_signed_in_candidates_applications(): void
    {
        $context = $this->createPortalContext();

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertSee('Portal Active Role');
        $response->assertSee('Portal Terminal Role');
        $response->assertDontSee('Hidden Other Candidate Role');
    }

    public function test_candidate_portal_renders_notification_center_and_assessment_modules(): void
    {
        $context = $this->createPortalContext();

        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['activeApplication']->id,
            'event_type' => 'stage.changed',
            'payload' => [
                'to_stage_label' => 'Interview',
            ],
            'actor_user_id' => (string) $context['candidateUser']->id,
            'created_at' => now(),
        ]);

        ApplicationScoring::withoutGlobalScopes()->updateOrCreate(
            [
                'application_id' => (string) $context['activeApplication']->id,
            ],
            [
                'company_id' => (string) $context['company']->id,
                'global_match_score' => 82.4,
                'vrin_json' => [
                    'acquired_skills' => ['Communication', 'Problem solving'],
                    'missing_skills' => ['Python frameworks'],
                ],
                'xai_summary' => 'Strong communication and overall role alignment.',
                'updated_at' => now(),
            ]
        );

        SjtResponse::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['activeApplication']->id,
            'scenario_id' => (string) SjtScenario::withoutGlobalScopes()->create([
                'company_id' => (string) $context['company']->id,
                'job_id' => (string) $context['activeJob']->id,
                'title' => 'Escalation under pressure',
                'scenario_media_url' => null,
                'scenario_text' => 'A release breaks late Friday. What do you do first?',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ])->id,
            'response_text' => str_repeat('a', 180),
            'copy_paste_blocked_flag' => true,
            'ai_score' => 79.5,
            'ai_feedback_json' => [
                'summary' => 'Calm and accountable answer.',
                'signals' => [
                    'accountability' => 'high',
                    'solution_orientation' => 'medium',
                    'tone' => 'high',
                ],
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Interview::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['activeApplication']->id,
            'interview_type' => 'structured',
            'scheduled_start_at' => now()->subHour()->utc(),
            'scheduled_end_at' => now()->utc(),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_ZOOM,
            'meeting_link' => 'https://example.test/interview',
            'status' => Interview::STATUS_COMPLETED,
            'created_by_user_id' => (string) $context['candidateUser']->id,
        ]);

        SocialPost::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'author_user_id' => (string) $context['candidateUser']->id,
            'type' => SocialPost::TYPE_WELCOME,
            'visibility' => SocialPost::VISIBILITY_PUBLIC,
            'content_text' => 'Portal preview welcome post',
            'media_url' => null,
            'reactions' => [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertSee(__('candidate_portal.hub.title', ['company' => $context['company']->name]));
        $response->assertSee(__('candidate_portal.workflow.title'));
        $response->assertSee('data-candidate-portal-shortcut="status"', false);
        $response->assertSee('data-candidate-portal-shortcut="assessments"', false);
        $response->assertSee('data-candidate-portal-shortcut="social-hub"', false);
        $response->assertSee('data-candidate-portal-shortcut="guide"', false);
        $response->assertSee('data-candidate-portal-shortcut="security"', false);
        $response->assertSee('id="candidate-applications"', false);
        $response->assertSee('id="candidate-assessments"', false);
        $response->assertSee('id="candidate-guide"', false);
        $response->assertSee('id="candidate-security"', false);
        $response->assertSee(__('candidate_portal.security.title'));
        $response->assertSee(__('candidate_portal.security.submit'));
        $response->assertSee('id="candidate-guide-bot"', false);
        $response->assertSee(__('candidate_portal.notifications.title'));
        $response->assertSee(__('candidate_portal.assessments.title'));
        $response->assertSee(__('candidate_portal.assessments.modules.situational_title'));
        $response->assertSee(__('candidate_portal.assessments.modules.response_zone_title'));
        $response->assertSee(__('candidate_portal.assessments.modules.stories_title'));
        $this->assertSame(
            2,
            substr_count($response->getContent(), '/candidate/assessments/sjt?application_id='),
            'The portal should not render duplicate SJT overview actions.'
        );
        $response->assertSee(__('candidate_portal.notifications.event_labels.stage_changed'));
        $response->assertSee(__('candidate_portal.notifications.event_labels.social_hub_enabled'));
        $response->assertSee(__('candidate_portal.status_tracker.title'));
        $response->assertSee(__('candidate_portal.xai.title'));
        $response->assertSee(__('candidate_portal.xai.reasons.missing_skills', ['skills' => 'Python frameworks']));
        $response->assertSee(__('candidate_portal.xai.reasons.sjt_signal', [
            'accountability' => 'strong',
            'solution' => 'balanced',
            'tone' => 'strong',
        ]));
        $response->assertSee(__('candidate_portal.culture.title'));
        $response->assertSee('data-culture-value-card', false);
        $response->assertSee(__('candidate_portal.feedback.form_title'));
        $response->assertSee(__('candidate_portal.feedback.privacy_notice'));
        $response->assertSee(__('candidate_portal.guider.privacy_note'));
        $response->assertSee('Portal preview welcome post');
        $response->assertSee('data-social-hub-preview', false);
        $response->assertSee('data-reverse-feedback-secure-link', false);
    }

    public function test_candidate_portal_does_not_unlock_recruiter_feedback_before_completed_zoom_interview(): void
    {
        $context = $this->createPortalContext();

        Interview::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['activeApplication']->id,
            'interview_type' => 'structured',
            'scheduled_start_at' => now()->subDay()->utc(),
            'scheduled_end_at' => now()->subDay()->addHour()->utc(),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_OTHER,
            'meeting_link' => 'https://meet.example.test/session',
            'status' => Interview::STATUS_COMPLETED,
            'created_by_user_id' => (string) $context['candidateUser']->id,
        ]);

        Interview::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['activeApplication']->id,
            'interview_type' => 'zoom-screen',
            'scheduled_start_at' => now()->addDay()->utc(),
            'scheduled_end_at' => now()->addDay()->addHour()->utc(),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_ZOOM,
            'meeting_link' => 'https://zoom.us/j/987654321',
            'status' => Interview::STATUS_SCHEDULED,
            'created_by_user_id' => (string) $context['candidateUser']->id,
        ]);

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertDontSee('reverse-feedback-'.(string) $context['activeApplication']->id);
        $response->assertSee(__('candidate_portal.feedback.unlock_hint'));
    }

    public function test_candidate_portal_treats_non_interview_email_events_as_application_updates(): void
    {
        $context = $this->createPortalContext();

        ApplicationActivityEvent::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['activeApplication']->id,
            'event_type' => 'email.sent',
            'payload' => [
                'template' => 'application_portal_verification',
                'recipient' => (string) $context['candidate']->email,
            ],
            'actor_user_id' => null,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertSee(__('candidate_portal.notifications.event_labels.portal_updated'));
        $response->assertSee(__('candidate_portal.notifications.event_messages.portal_updated', [
            'job' => 'Portal Active Role',
        ]));
        $response->assertDontSee(__('candidate_portal.notifications.event_labels.email_sent'));
    }

    public function test_candidate_guide_answers_only_from_published_faq_and_is_blocked_on_assessment_pages(): void
    {
        $context = $this->createPortalContext();

        FaqItem::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'category' => 'Process',
            'question' => 'When will I hear back?',
            'answer' => 'Recruiters usually respond within 5 business days.',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        FaqItem::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'category' => 'Internal',
            'question' => 'Hidden FAQ',
            'answer' => 'This answer should never appear for candidates.',
            'is_published' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $faqPage = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.faq', ['company' => $context['company']->slug]));

        $faqPage->assertOk();
        $faqPage->assertSee('When will I hear back?');
        $faqPage->assertDontSee('Hidden FAQ');
        $faqPage->assertSee('data-candidate-guide-bot', false);

        $guideAnswer = $this->actingAs($context['candidateUser'])
            ->postJson(route('candidate.guide.ask', ['company' => $context['company']->slug]), [
                'message' => 'when will i hear back from recruiters?',
            ]);

        $guideAnswer->assertOk();
        $guideAnswer->assertJson([
            'ok' => true,
            'refused' => false,
            'answer' => 'Recruiters usually respond within 5 business days.',
        ]);

        $statusAnswer = $this->actingAs($context['candidateUser'])
            ->postJson(route('candidate.guide.ask', ['company' => $context['company']->slug]), [
                'message' => 'Can you share my application status and next step?',
            ]);

        $statusAnswer->assertOk();
        $statusAnswer->assertJson([
            'ok' => true,
            'refused' => false,
        ]);
        $this->assertStringContainsString('Portal Active Role', (string) $statusAnswer->json('answer'));
        $this->assertStringNotContainsString('Hidden Other Candidate Role', (string) $statusAnswer->json('answer'));

        Offer::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['activeApplication']->id,
            'offer_status' => Offer::STATUS_SENT,
            'salary_amount' => 80000,
            'currency' => 'USD',
            'start_date' => now()->addMonth()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hiddenApplicationId = (string) (Application::query()
            ->whereHas('job', fn ($query) => $query->where('title', 'Hidden Other Candidate Role'))
            ->value('id') ?? '');
        $this->assertNotSame('', $hiddenApplicationId);

        Offer::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => $hiddenApplicationId,
            'offer_status' => Offer::STATUS_SENT,
            'salary_amount' => 150000,
            'currency' => 'USD',
            'start_date' => now()->addMonth()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $salaryAnswer = $this->actingAs($context['candidateUser'])
            ->postJson(route('candidate.guide.ask', ['company' => $context['company']->slug]), [
                'message' => 'Can you share my salary package details?',
            ]);

        $salaryAnswer->assertOk();
        $salaryAnswer->assertJson([
            'ok' => true,
            'refused' => false,
        ]);
        $this->assertStringContainsString('Portal Active Role', (string) $salaryAnswer->json('answer'));
        $this->assertStringContainsString('80,000.00', (string) $salaryAnswer->json('answer'));
        $this->assertStringNotContainsString('150,000.00', (string) $salaryAnswer->json('answer'));
        $this->assertStringNotContainsString('Hidden Other Candidate Role', (string) $salaryAnswer->json('answer'));

        $valuesAnswer = $this->actingAs($context['candidateUser'])
            ->postJson(route('candidate.guide.ask', ['company' => $context['company']->slug]), [
                'message' => 'What are the company values?',
            ]);

        $valuesAnswer->assertOk();
        $valuesAnswer->assertJson([
            'ok' => true,
            'refused' => false,
        ]);
        $this->assertStringContainsString('Innovation', (string) $valuesAnswer->json('answer'));

        $guidanceAnswer = $this->actingAs($context['candidateUser'])
            ->postJson(route('candidate.guide.ask', ['company' => $context['company']->slug]), [
                'message' => 'Can you recommend what I should do next?',
            ]);

        $guidanceAnswer->assertOk();
        $guidanceAnswer->assertJson([
            'ok' => true,
            'refused' => false,
        ]);
        $this->assertStringContainsString('Portal Active Role', (string) $guidanceAnswer->json('answer'));

        $processAnswer = $this->actingAs($context['candidateUser'])
            ->postJson(route('candidate.guide.ask', ['company' => $context['company']->slug]), [
                'message' => 'Can you explain the strategy lab and interview process in the portal?',
            ]);

        $processAnswer->assertOk();
        $processAnswer->assertJson([
            'ok' => true,
            'refused' => false,
        ]);
        $this->assertStringContainsString('Portal Active Role', (string) $processAnswer->json('answer'));
        $this->assertStringContainsString(
            'Final approval/rejection decisions are always made by human recruiters.',
            (string) $processAnswer->json('answer')
        );
        $this->assertStringNotContainsString('Hidden Other Candidate Role', (string) $processAnswer->json('answer'));

        $guideRefusal = $this->actingAs($context['candidateUser'])
            ->postJson(route('candidate.guide.ask', ['company' => $context['company']->slug]), [
                'message' => 'Please generate an SJT assessment answer for me.',
            ]);

        $guideRefusal->assertOk();
        $guideRefusal->assertJson([
            'ok' => true,
            'refused' => true,
        ]);

        SjtScenario::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'job_id' => $context['activeJob']->id,
            'title' => 'Assessment Scenario',
            'scenario_media_url' => null,
            'scenario_text' => 'A stakeholder asks for rushed delivery.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assessmentPage = $this->actingAs($context['candidateUser'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidate.assessments.sjt', [
                'application_id' => (string) $context['activeApplication']->id,
            ]));

        $assessmentPage->assertOk();
        $assessmentPage->assertDontSee('data-candidate-guide-bot', false);
        $assessmentPage->assertSee('data-guide-bot-disabled="true"', false);
    }

    public function test_candidate_can_change_password_from_portal(): void
    {
        $context = $this->createPortalContext();

        $response = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.password.update', ['company' => $context['company']->slug]), [
                'current_password' => 'password',
                'password' => 'NewPortalPass123!',
                'password_confirmation' => 'NewPortalPass123!',
            ]);

        $response->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $response->assertSessionHas('status', __('candidate_portal.security.password_updated'));

        $this->assertTrue(Hash::check('NewPortalPass123!', (string) $context['candidateUser']->fresh()?->password));
    }

    public function test_candidate_password_change_requires_valid_current_password(): void
    {
        $context = $this->createPortalContext();

        $response = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.portal', ['company' => $context['company']->slug]))
            ->post(route('candidate.password.update', ['company' => $context['company']->slug]), [
                'current_password' => 'wrong-password',
                'password' => 'NewPortalPass123!',
                'password_confirmation' => 'NewPortalPass123!',
            ]);

        $response->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $response->assertSessionHasErrors([
            'current_password' => __('candidate_portal.security.errors.current_password_invalid'),
        ]);

        $this->assertTrue(Hash::check('password', (string) $context['candidateUser']->fresh()?->password));
    }

    public function test_status_tracker_endpoint_returns_only_logged_in_candidate_records(): void
    {
        $context = $this->createPortalContext();

        Interview::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['activeApplication']->id,
            'interview_type' => 'structured',
            'scheduled_start_at' => now()->addHours(6)->utc(),
            'scheduled_end_at' => now()->addHours(7)->utc(),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_ZOOM,
            'meeting_link' => 'https://example.test/live-status',
            'status' => Interview::STATUS_SCHEDULED,
            'created_by_user_id' => (string) $context['candidateUser']->id,
        ]);

        $response = $this->actingAs($context['candidateUser'])
            ->getJson(route('candidate.status-tracker', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
        ]);

        $trackers = collect($response->json('trackers'));
        $trackerApplicationIds = $trackers->pluck('application_id')->map(static fn ($id): string => (string) $id);

        $this->assertTrue($trackerApplicationIds->contains((string) $context['activeApplication']->id));
        $this->assertTrue($trackerApplicationIds->contains((string) $context['terminalApplication']->id));

        $hiddenApplicationId = (string) (Application::query()
            ->whereHas('job', fn ($query) => $query->where('title', 'Hidden Other Candidate Role'))
            ->value('id') ?? '');
        $this->assertNotSame('', $hiddenApplicationId);
        $this->assertFalse($trackerApplicationIds->contains($hiddenApplicationId));

        $this->assertTrue($trackers->every(function (array $tracker): bool {
            $stepKeys = collect((array) ($tracker['steps'] ?? []))
                ->pluck('key')
                ->map(static fn ($key): string => (string) $key);

            return $stepKeys->contains('application_received')
                && $stepKeys->contains('interview_scheduled')
                && $stepKeys->contains('interview_in_progress')
                && $stepKeys->contains('under_analysis')
                && $stepKeys->contains('outcome');
        }));
    }

    public function test_reverse_feedback_respects_candidate_eligibility_rules_and_remains_single_submission_per_application(): void
    {
        $context = $this->createPortalContext();

        Interview::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['terminalApplication']->id,
            'interview_type' => 'structured',
            'scheduled_start_at' => now()->subDays(2)->utc(),
            'scheduled_end_at' => now()->subDays(2)->addHour()->utc(),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_ZOOM,
            'meeting_link' => 'https://example.test/interview-completed',
            'status' => Interview::STATUS_COMPLETED,
            'created_by_user_id' => (string) $context['candidateUser']->id,
        ]);

        $submit = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $context['terminalApplication']->id,
            ]), [
                'rating_clarity' => 4,
                'rating_speed' => 3,
                'rating_kindness' => 5,
                'comment' => 'Professional process.',
                'is_anonymous' => '1',
            ]);

        $submit->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $submit->assertSessionHas('status', __('candidate_portal.feedback.submitted'));

        $this->assertTrue(
            ReverseFeedback::withoutGlobalScopes()
                ->where('application_id', $context['terminalApplication']->id)
                ->exists()
        );

        $duplicate = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $context['terminalApplication']->id,
            ]), [
                'rating_clarity' => 1,
                'rating_speed' => 1,
                'rating_kindness' => 1,
            ]);

        $duplicate->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $duplicate->assertSessionHas('status', __('candidate_portal.feedback.already_submitted'));

        $this->assertSame(
            1,
            ReverseFeedback::withoutGlobalScopes()
                ->where('application_id', $context['terminalApplication']->id)
                ->count()
        );

        $advancedStageSubmit = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.portal', ['company' => $context['company']->slug]))
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $context['activeApplication']->id,
            ]), [
                'rating_clarity' => 5,
                'rating_speed' => 4,
                'rating_kindness' => 5,
            ]);

        $advancedStageSubmit->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $advancedStageSubmit->assertSessionHasErrors([
            'feedback' => __('candidate_portal.feedback.only_terminal_allowed'),
        ]);

        Interview::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['activeApplication']->id,
            'interview_type' => 'structured',
            'scheduled_start_at' => now()->subHour()->utc(),
            'scheduled_end_at' => now()->utc(),
            'timezone' => 'UTC',
            'location_type' => Interview::LOCATION_ZOOM,
            'meeting_link' => 'https://zoom.us/j/123456789',
            'status' => Interview::STATUS_COMPLETED,
            'created_by_user_id' => (string) $context['candidateUser']->id,
        ]);

        $advancedStageAllowed = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $context['activeApplication']->id,
            ]), [
                'rating_clarity' => 5,
                'rating_speed' => 4,
                'rating_kindness' => 5,
            ]);

        $advancedStageAllowed->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $advancedStageAllowed->assertSessionHas('status', __('candidate_portal.feedback.submitted'));

        $appliedOnlyJob = Job::query()->create([
            'company_id' => (string) $context['company']->id,
            'title' => 'Applied Only Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $appliedOnlyStage = JobPipelineStage::query()->create([
            'job_id' => (string) $appliedOnlyJob->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $appliedOnlyApplication = Application::query()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $context['candidate']->id,
            'job_id' => (string) $appliedOnlyJob->id,
            'current_stage_id' => (string) $appliedOnlyStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $appliedOnlyAttempt = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.portal', ['company' => $context['company']->slug]))
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $appliedOnlyApplication->id,
            ]), [
                'rating_clarity' => 4,
                'rating_speed' => 4,
                'rating_kindness' => 4,
            ]);

        $appliedOnlyAttempt->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $appliedOnlyAttempt->assertSessionHasErrors([
            'feedback' => __('candidate_portal.feedback.only_terminal_allowed'),
        ]);

        $autoRejectedJob = Job::query()->create([
            'company_id' => (string) $context['company']->id,
            'title' => 'Auto Rejected Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $autoRejectedStage = JobPipelineStage::query()->create([
            'job_id' => (string) $autoRejectedJob->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 2,
            'is_terminal' => true,
        ]);

        $autoRejectedApplication = Application::query()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $context['candidate']->id,
            'job_id' => (string) $autoRejectedJob->id,
            'current_stage_id' => (string) $autoRejectedStage->id,
            'status' => Application::STATUS_REJECTED,
            'source_type' => 'career_page',
        ]);

        $autoRejectedAttempt = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.portal', ['company' => $context['company']->slug]))
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $autoRejectedApplication->id,
            ]), [
                'rating_clarity' => 4,
                'rating_speed' => 4,
                'rating_kindness' => 4,
            ]);

        $autoRejectedAttempt->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $autoRejectedAttempt->assertSessionHasErrors([
            'feedback' => __('candidate_portal.feedback.only_terminal_allowed'),
        ]);
    }

    public function test_hired_candidate_can_submit_reverse_feedback_only_after_onboarding_completion(): void
    {
        $context = $this->createPortalContext();

        $hiredJob = Job::query()->create([
            'company_id' => (string) $context['company']->id,
            'title' => 'Hired Candidate Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $hiredStage = JobPipelineStage::query()->create([
            'job_id' => (string) $hiredJob->id,
            'stage_key' => 'hired',
            'stage_label' => 'Hired',
            'display_order' => 3,
            'is_terminal' => true,
        ]);

        $hiredApplication = Application::query()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $context['candidate']->id,
            'job_id' => (string) $hiredJob->id,
            'current_stage_id' => (string) $hiredStage->id,
            'status' => Application::STATUS_HIRED,
            'source_type' => 'career_page',
        ]);

        $contract = Contract::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $hiredApplication->id,
            'contract_file_url' => 'private/contracts/demo.pdf',
            'contract_status' => Contract::STATUS_SENT,
            'signed_at' => null,
            'signer_user_id' => null,
            'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
            'audit_metadata_json' => [],
        ]);

        $task = OnboardingTask::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $hiredApplication->id,
            'task_name' => 'Submit identity documents',
            'due_at' => now()->addDays(3)->utc(),
            'is_completed' => false,
        ]);

        $blocked = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.portal', ['company' => $context['company']->slug]))
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $hiredApplication->id,
            ]), [
                'rating_clarity' => 5,
                'rating_speed' => 5,
                'rating_kindness' => 5,
            ]);

        $blocked->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $blocked->assertSessionHasErrors([
            'feedback' => __('candidate_portal.feedback.only_terminal_allowed'),
        ]);

        $contract->forceFill([
            'contract_status' => Contract::STATUS_SENT,
            'signed_at' => now(),
            'signer_user_id' => (string) $context['candidateUser']->id,
            'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
        ])->save();
        $task->forceFill(['is_completed' => true])->save();

        $stillBlocked = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.portal', ['company' => $context['company']->slug]))
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $hiredApplication->id,
            ]), [
                'rating_clarity' => 5,
                'rating_speed' => 5,
                'rating_kindness' => 5,
            ]);

        $stillBlocked->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $stillBlocked->assertSessionHasErrors([
            'feedback' => __('candidate_portal.feedback.only_terminal_allowed'),
        ]);

        $contract->forceFill([
            'contract_status' => Contract::STATUS_SIGNED,
            'signed_at' => now(),
            'signer_user_id' => (string) $context['candidateUser']->id,
            'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
        ])->save();

        $task->forceFill(['is_completed' => true])->save();

        $allowed = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $hiredApplication->id,
            ]), [
                'rating_clarity' => 5,
                'rating_speed' => 4,
                'rating_kindness' => 5,
            ]);

        $allowed->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $allowed->assertSessionHas('status', __('candidate_portal.feedback.submitted'));
    }

    public function test_hired_stage_candidate_with_active_status_uses_onboarding_completion_for_reverse_feedback(): void
    {
        $context = $this->createPortalContext();

        $hiredFlowJob = Job::query()->create([
            'company_id' => (string) $context['company']->id,
            'title' => 'Hired Flow Stage Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $hiredFlowStage = JobPipelineStage::query()->create([
            'job_id' => (string) $hiredFlowJob->id,
            'stage_key' => 'hired',
            'stage_label' => 'Hired',
            'display_order' => 4,
            'is_terminal' => true,
        ]);

        $hiredFlowApplication = Application::query()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $context['candidate']->id,
            'job_id' => (string) $hiredFlowJob->id,
            'current_stage_id' => (string) $hiredFlowStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $contract = Contract::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $hiredFlowApplication->id,
            'contract_file_url' => 'private/contracts/hired-flow.pdf',
            'contract_status' => Contract::STATUS_SENT,
            'signed_at' => null,
            'signer_user_id' => null,
            'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
            'audit_metadata_json' => [],
        ]);

        $task = OnboardingTask::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $hiredFlowApplication->id,
            'task_name' => 'Finish onboarding checklist',
            'due_at' => now()->addDays(2)->utc(),
            'is_completed' => false,
        ]);

        $portalBeforeCompletion = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $portalBeforeCompletion->assertOk();
        $portalBeforeCompletion->assertDontSee('reverse-feedback-'.(string) $hiredFlowApplication->id);

        $blocked = $this->actingAs($context['candidateUser'])
            ->from(route('candidate.portal', ['company' => $context['company']->slug]))
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $hiredFlowApplication->id,
            ]), [
                'rating_clarity' => 5,
                'rating_speed' => 5,
                'rating_kindness' => 5,
            ]);

        $blocked->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $blocked->assertSessionHasErrors([
            'feedback' => __('candidate_portal.feedback.only_terminal_allowed'),
        ]);

        $contract->forceFill([
            'contract_status' => Contract::STATUS_SIGNED,
            'signed_at' => now(),
            'signer_user_id' => (string) $context['candidateUser']->id,
            'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
        ])->save();

        $task->forceFill(['is_completed' => true])->save();

        $portalAfterCompletion = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $portalAfterCompletion->assertOk();
        $portalAfterCompletion->assertSee('reverse-feedback-'.(string) $hiredFlowApplication->id);

        $allowed = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.reverse-feedback.store', [
                'company' => $context['company']->slug,
                'application' => $hiredFlowApplication->id,
            ]), [
                'rating_clarity' => 4,
                'rating_speed' => 4,
                'rating_kindness' => 5,
            ]);

        $allowed->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $allowed->assertSessionHas('status', __('candidate_portal.feedback.submitted'));
    }

    public function test_rejected_stage_does_not_remain_in_hired_flow_when_status_is_stale_hired(): void
    {
        $context = $this->createPortalContext();

        $rejectedJob = Job::query()->create([
            'company_id' => (string) $context['company']->id,
            'title' => 'Stale Hired Status Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $rejectedStage = JobPipelineStage::query()->create([
            'job_id' => (string) $rejectedJob->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 5,
            'is_terminal' => true,
        ]);

        $staleStatusApplication = Application::query()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $context['candidate']->id,
            'job_id' => (string) $rejectedJob->id,
            'current_stage_id' => (string) $rejectedStage->id,
            'status' => Application::STATUS_HIRED,
            'source_type' => 'career_page',
        ]);

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertDontSee('reverse-feedback-'.(string) $staleStatusApplication->id);
    }

    public function test_reverse_feedback_link_is_hidden_for_applied_only_and_auto_rejected_without_interview(): void
    {
        $context = $this->createPortalContext();

        $appliedOnlyJob = Job::query()->create([
            'company_id' => (string) $context['company']->id,
            'title' => 'UI Applied Only Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);
        $appliedOnlyStage = JobPipelineStage::query()->create([
            'job_id' => (string) $appliedOnlyJob->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);
        $appliedOnlyApplication = Application::query()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $context['candidate']->id,
            'job_id' => (string) $appliedOnlyJob->id,
            'current_stage_id' => (string) $appliedOnlyStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $autoRejectedJob = Job::query()->create([
            'company_id' => (string) $context['company']->id,
            'title' => 'UI Auto Rejected Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);
        $autoRejectedStage = JobPipelineStage::query()->create([
            'job_id' => (string) $autoRejectedJob->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 2,
            'is_terminal' => true,
        ]);
        $autoRejectedApplication = Application::query()->create([
            'company_id' => (string) $context['company']->id,
            'candidate_id' => (string) $context['candidate']->id,
            'job_id' => (string) $autoRejectedJob->id,
            'current_stage_id' => (string) $autoRejectedStage->id,
            'status' => Application::STATUS_REJECTED,
            'source_type' => 'career_page',
        ]);

        $response = $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]));

        $response->assertOk();
        $response->assertDontSee('reverse-feedback-'.(string) $appliedOnlyApplication->id);
        $response->assertDontSee('reverse-feedback-'.(string) $autoRejectedApplication->id);
    }

    public function test_reverse_feedback_aggregate_is_visible_only_to_hr_admin_roles(): void
    {
        $context = $this->createPortalContext();

        $manager = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);
        CompanyMembership::query()->create([
            'company_id' => $context['company']->id,
            'user_id' => $manager->id,
            'company_role' => CompanyMembership::ROLE_MANAGER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $recruiter = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);
        CompanyMembership::query()->create([
            'company_id' => $context['company']->id,
            'user_id' => $recruiter->id,
            'company_role' => CompanyMembership::ROLE_RECRUITER,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $secondCandidateUser = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);
        CompanyMembership::query()->create([
            'company_id' => $context['company']->id,
            'user_id' => $secondCandidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);
        $secondCandidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'user_id' => $secondCandidateUser->id,
            'full_name' => 'Aggregate Candidate',
            'email' => (string) $secondCandidateUser->email,
        ]);

        $secondTerminalApplication = Application::query()->create([
            'company_id' => $context['company']->id,
            'candidate_id' => $secondCandidate->id,
            'job_id' => $context['terminalJob']->id,
            'current_stage_id' => $context['terminalApplication']->current_stage_id,
            'status' => Application::STATUS_REJECTED,
            'source_type' => 'career_page',
        ]);

        ReverseFeedback::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $context['terminalApplication']->id,
            'rating_clarity' => 4,
            'rating_speed' => 3,
            'rating_kindness' => 5,
            'comment' => 'private comment one',
            'is_anonymous' => true,
            'created_at' => now(),
        ]);

        ReverseFeedback::withoutGlobalScopes()->create([
            'company_id' => $context['company']->id,
            'application_id' => $secondTerminalApplication->id,
            'rating_clarity' => 5,
            'rating_speed' => 4,
            'rating_kindness' => 4,
            'comment' => 'private comment two',
            'is_anonymous' => true,
            'created_at' => now(),
        ]);

        $managerView = $this->actingAs($manager)
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidates.index', [
                'application_id' => (string) $context['terminalApplication']->id,
                'company_id' => (string) $context['company']->id,
            ]));

        $managerView->assertOk();
        $managerView->assertSee(__('candidates.detail.reverse_feedback_aggregate'));
        $managerView->assertSee('2');
        $managerView->assertSee('4.5/5');
        $managerView->assertSee('3.5/5');
        $managerView->assertDontSee('private comment one');
        $managerView->assertDontSee('private comment two');

        $recruiterView = $this->actingAs($recruiter)
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidates.index', [
                'application_id' => (string) $context['terminalApplication']->id,
                'company_id' => (string) $context['company']->id,
            ]));

        $recruiterView->assertOk();
        $recruiterView->assertSee(__('candidates.detail.reverse_feedback_aggregate'));
        $recruiterView->assertSee(__('candidates.detail.reverse_feedback_restricted'));
        $recruiterView->assertDontSee('4.5/5');
        $recruiterView->assertDontSee('3.5/5');
        $recruiterView->assertDontSee('private comment one');
        $recruiterView->assertDontSee('private comment two');
    }

    /**
     * @return array{
     *   company: Company,
     *   candidateUser: User,
     *   candidate: Candidate,
     *   activeJob: Job,
     *   terminalJob: Job,
     *   activeApplication: Application,
     *   terminalApplication: Application
     * }
     */
    private function createPortalContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Portal Company',
            'slug' => 'portal-company',
            'status' => Company::STATUS_ACTIVE,
        ]);

        $candidateUser = User::factory()->create([
            'email' => 'portal-candidate@example.com',
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $candidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $candidateUser->id,
            'full_name' => 'Portal Candidate',
            'email' => 'portal-candidate@example.com',
            'phone' => '+1-555-0100',
            'location' => 'Remote',
        ]);

        CompanyValue::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => 'Innovation',
            'description' => 'We build practical solutions that improve candidate and team experience.',
            'icon_name' => 'Spark',
            'display_order' => 1,
        ]);

        $activeJob = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Portal Active Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $terminalJob = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Portal Terminal Role',
            'status' => Job::STATUS_PUBLISHED,
        ]);

        $activeStage = JobPipelineStage::query()->create([
            'job_id' => $activeJob->id,
            'stage_key' => 'preselected',
            'stage_label' => 'Preselected',
            'display_order' => 1,
            'is_terminal' => false,
        ]);

        $terminalStage = JobPipelineStage::query()->create([
            'job_id' => $terminalJob->id,
            'stage_key' => 'rejected',
            'stage_label' => 'Rejected',
            'display_order' => 1,
            'is_terminal' => true,
        ]);

        $activeApplication = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $activeJob->id,
            'current_stage_id' => $activeStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        $terminalApplication = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $terminalJob->id,
            'current_stage_id' => $terminalStage->id,
            'status' => Application::STATUS_REJECTED,
            'source_type' => 'career_page',
        ]);

        $otherUser = User::factory()->create([
            'email' => 'other-candidate@example.com',
            'email_verified_at' => now(),
            'active' => true,
        ]);
        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $otherUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);
        $otherCandidate = Candidate::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'user_id' => $otherUser->id,
            'full_name' => 'Other Candidate',
            'email' => 'other-candidate@example.com',
        ]);
        $hiddenJob = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Hidden Other Candidate Role',
            'status' => Job::STATUS_ARCHIVED,
        ]);
        $hiddenStage = JobPipelineStage::query()->create([
            'job_id' => $hiddenJob->id,
            'stage_key' => 'applied',
            'stage_label' => 'Applied',
            'display_order' => 1,
            'is_terminal' => false,
        ]);
        Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $otherCandidate->id,
            'job_id' => $hiddenJob->id,
            'current_stage_id' => $hiddenStage->id,
            'status' => Application::STATUS_ACTIVE,
            'source_type' => 'career_page',
        ]);

        return compact(
            'company',
            'candidateUser',
            'candidate',
            'activeJob',
            'terminalJob',
            'activeApplication',
            'terminalApplication'
        );
    }
}
