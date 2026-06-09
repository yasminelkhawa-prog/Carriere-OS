<?php

namespace Tests\Feature;

use App\Http\Controllers\CandidatePortalController;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\Contract;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\OnboardingDocument;
use App\Models\OnboardingSchedule;
use App\Models\OnboardingTask;
use App\Models\User;
use Database\Seeders\OfferOnboardingModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfferOnboardingModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_hired_application_exposes_onboarding_hub_signing_secure_docs_and_calendar_widgets(): void
    {
        Storage::fake('local');
        $context = $this->createHiredOnboardingContext(withContract: true);

        $this->actingAs($context['admin'])
            ->withSession(['active_company_id' => (string) $context['company']->id])
            ->get(route('candidates.index', [
                'application_id' => (string) $context['application']->id,
                'company_id' => (string) $context['company']->id,
            ]))
            ->assertOk()
            ->assertSee(__('candidates.onboarding.tabs.onboarding_hub'));

        $this->actingAs($context['candidateUser'])
            ->get(route('candidate.portal', ['company' => $context['company']->slug]))
            ->assertOk()
            ->assertSee('data-onboarding-hub', false)
            ->assertSee(__('candidate_portal.onboarding.title'))
            ->assertSee(__('candidate_portal.onboarding.calendar.start_date'))
            ->assertSee(__('candidate_portal.onboarding.calendar.team_introductions'))
            ->assertSee(__('candidate_portal.onboarding.calendar.training_sessions'))
            ->assertSee('Day 1 Orientation')
            ->assertSee('Submit bank details');

        $task = OnboardingTask::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('application_id', $context['application']->id)
            ->firstOrFail();

        $toggleDoneResponse = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.onboarding-tasks.toggle', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
                'onboardingTask' => $task->id,
            ]));

        $toggleDoneResponse->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $toggleDoneResponse->assertSessionHas('status', __('candidate_portal.onboarding.tasks.updated_done'));
        $this->assertTrue((bool) $task->fresh()?->is_completed);

        $toggleOpenResponse = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.onboarding-tasks.toggle', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
                'onboardingTask' => $task->id,
            ]));

        $toggleOpenResponse->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $toggleOpenResponse->assertSessionHas('status', __('candidate_portal.onboarding.tasks.updated_open'));
        $this->assertFalse((bool) $task->fresh()?->is_completed);

        $signResponse = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.contract.sign', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'typed_signature' => 'Portal Candidate',
                'acknowledgement' => '1',
            ]);

        $signResponse->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $signResponse->assertSessionHas('status', __('candidate_portal.onboarding.contract.signed_success'));
        $signResponse->assertSessionHas('onboarding_confetti', true);

        $contract = $context['contract']->fresh();
        $this->assertSame(Contract::STATUS_SIGNED, (string) $contract?->contract_status);
        $this->assertNotNull($contract?->signed_at);
        $this->assertSame((string) $context['candidateUser']->id, (string) $contract?->signer_user_id);
        $this->assertDatabaseHas('email_outbox_logs', [
            'company_id' => $context['company']->id,
            'template_key' => 'onboarding_welcome_after_signing',
            'related_entity_type' => 'application',
            'related_entity_id' => (string) $context['application']->id,
        ]);
        $this->assertDatabaseHas('application_activity_events', [
            'company_id' => $context['company']->id,
            'application_id' => (string) $context['application']->id,
            'event_type' => 'contract.signed',
        ]);

        $this->actingAs($context['candidateUser'])
            ->post(route('candidate.onboarding-documents.store', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'doc_type' => OnboardingDocument::TYPE_ID,
                'file' => UploadedFile::fake()->create('id-doc.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));

        $duplicateUploadResponse = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.onboarding-documents.store', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'doc_type' => OnboardingDocument::TYPE_ID,
                'file' => UploadedFile::fake()->create('id-doc-duplicate.pdf', 200, 'application/pdf'),
            ]);

        $duplicateUploadResponse->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $duplicateUploadResponse->assertSessionHas('status', __('candidate_portal.onboarding.documents.already_uploaded'));

        $this->assertSame(
            1,
            OnboardingDocument::withoutGlobalScopes()
                ->where('company_id', $context['company']->id)
                ->where('application_id', $context['application']->id)
                ->where('doc_type', OnboardingDocument::TYPE_ID)
                ->count()
        );

        $this->actingAs($context['candidateUser'])
            ->post(route('candidate.onboarding-documents.store', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'doc_type' => OnboardingDocument::TYPE_BANK,
                'file' => UploadedFile::fake()->create('bank-details.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));

        $this->actingAs($context['candidateUser'])
            ->post(route('candidate.onboarding-documents.store', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'doc_type' => OnboardingDocument::TYPE_DIPLOMA,
                'file' => UploadedFile::fake()->create('diploma.pdf', 200, 'application/pdf'),
            ])
            ->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));

        $this->assertSame(
            3,
            OnboardingDocument::withoutGlobalScopes()
                ->where('company_id', $context['company']->id)
                ->where('application_id', $context['application']->id)
                ->count()
        );
        $this->assertDatabaseHas('onboarding_documents', [
            'company_id' => (string) $context['company']->id,
            'application_id' => (string) $context['application']->id,
            'doc_type' => OnboardingDocument::TYPE_DIPLOMA,
        ]);

        $document = OnboardingDocument::withoutGlobalScopes()
            ->where('company_id', $context['company']->id)
            ->where('application_id', $context['application']->id)
            ->firstOrFail();

        $signedUrl = CandidatePortalController::signedOnboardingDocumentUrl($document);
        $relativeSignedUrl = $this->toRelativeUrl($signedUrl);

        $this->actingAs($context['candidateUser'])
            ->get($relativeSignedUrl)
            ->assertOk();

        $this->actingAs($context['candidateUser'])
            ->get(route('media.onboarding-document', ['onboardingDocument' => $document->id]))
            ->assertStatus(302);

        $otherCandidateUser = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);
        CompanyMembership::query()->create([
            'company_id' => (string) $context['company']->id,
            'user_id' => (string) $otherCandidateUser->id,
            'company_role' => CompanyMembership::ROLE_CANDIDATE,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);
        Candidate::withoutGlobalScopes()->create([
            'company_id' => (string) $context['company']->id,
            'user_id' => (string) $otherCandidateUser->id,
            'full_name' => 'Isolated Candidate',
            'email' => (string) $otherCandidateUser->email,
        ]);

        $this->actingAs($otherCandidateUser)
            ->get($relativeSignedUrl)
            ->assertRedirect(route('home'));
    }

    public function test_signing_contract_requires_existing_contract_and_prevents_double_signing(): void
    {
        Storage::fake('local');
        $contextWithoutContract = $this->createHiredOnboardingContext(withContract: false);

        $missingContract = $this->actingAs($contextWithoutContract['candidateUser'])
            ->post(route('candidate.contract.sign', [
                'company' => $contextWithoutContract['company']->slug,
                'application' => $contextWithoutContract['application']->id,
            ]), [
                'typed_signature' => 'Portal Candidate',
                'acknowledgement' => '1',
            ]);

        $missingContract->assertRedirect(route('candidate.portal', ['company' => $contextWithoutContract['company']->slug]));
        $missingContract->assertSessionHas('error', __('candidate_portal.onboarding.errors.contract_missing'));

        $context = $this->createHiredOnboardingContext(withContract: true);

        $first = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.contract.sign', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'typed_signature' => 'Portal Candidate',
                'acknowledgement' => '1',
            ]);
        $first->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));

        $second = $this->actingAs($context['candidateUser'])
            ->post(route('candidate.contract.sign', [
                'company' => $context['company']->slug,
                'application' => $context['application']->id,
            ]), [
                'typed_signature' => 'Portal Candidate',
                'acknowledgement' => '1',
            ]);

        $second->assertRedirect(route('candidate.portal', ['company' => $context['company']->slug]));
        $second->assertSessionHas('status', __('candidate_portal.onboarding.contract.already_signed'));
    }

    public function test_offer_onboarding_seeder_creates_required_sample_data(): void
    {
        $this->seed(OfferOnboardingModuleSeeder::class);

        $company = Company::query()->where('slug', 'numa-demo')->first();
        $this->assertNotNull($company);

        $application = Application::withoutGlobalScopes()
            ->where('company_id', $company?->id)
            ->where('status', Application::STATUS_HIRED)
            ->first();
        $this->assertNotNull($application);

        $this->assertDatabaseHas('contracts', [
            'company_id' => $company?->id,
            'application_id' => $application?->id,
        ]);

        $this->assertSame(
            2,
            OnboardingDocument::withoutGlobalScopes()
                ->where('company_id', $company?->id)
                ->where('application_id', $application?->id)
                ->count()
        );
    }

    /**
     * @return array{
     *   company: Company,
     *   admin: User,
     *   candidateUser: User,
     *   candidate: Candidate,
     *   application: Application,
     *   contract: ?Contract
     * }
     */
    private function createHiredOnboardingContext(bool $withContract): array
    {
        $company = Company::query()->create([
            'name' => 'Onboarding Company '.strtoupper((string) \Illuminate\Support\Str::random(8)),
            'slug' => 'onboarding-company-'.strtolower((string) \Illuminate\Support\Str::random(6)),
            'status' => Company::STATUS_ACTIVE,
        ]);

        $admin = User::factory()->create([
            'email_verified_at' => now(),
            'active' => true,
        ]);

        CompanyMembership::query()->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'company_role' => CompanyMembership::ROLE_COMPANY_ADMIN,
            'membership_status' => CompanyMembership::STATUS_ACTIVE,
        ]);

        $candidateUser = User::factory()->create([
            'email' => 'onboarding-candidate-'.\Illuminate\Support\Str::lower((string) \Illuminate\Support\Str::random(5)).'@example.com',
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
            'email' => (string) $candidateUser->email,
            'phone' => '+1-555-0150',
            'location' => 'Remote',
        ]);

        $job = Job::query()->create([
            'company_id' => $company->id,
            'title' => 'Onboarding Engineer',
            'status' => Job::STATUS_PUBLISHED,
            'location' => 'Remote',
        ]);

        $hiredStage = JobPipelineStage::query()->create([
            'job_id' => $job->id,
            'stage_key' => 'hired',
            'stage_label' => 'Hired',
            'display_order' => 5,
            'is_terminal' => true,
        ]);

        $application = Application::query()->create([
            'company_id' => $company->id,
            'candidate_id' => $candidate->id,
            'job_id' => $job->id,
            'current_stage_id' => $hiredStage->id,
            'status' => Application::STATUS_HIRED,
            'source_type' => 'career_page',
        ]);

        OnboardingSchedule::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'title' => 'Day 1 Orientation',
            'start_at' => now()->addDays(2)->setTime(9, 0)->utc(),
            'end_at' => now()->addDays(2)->setTime(10, 0)->utc(),
            'location' => 'HQ Main Room',
            'created_at' => now(),
        ]);

        OnboardingTask::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'application_id' => $application->id,
            'task_name' => 'Submit bank details',
            'due_at' => now()->addDays(1)->utc(),
            'is_completed' => false,
        ]);

        $contract = null;
        if ($withContract) {
            $contractPath = 'private/onboarding/contracts/'.$company->id.'/'.$application->id.'/offer.pdf';
            Storage::disk('local')->put($contractPath, 'contract content');

            $contract = Contract::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'application_id' => $application->id,
                'contract_file_url' => $contractPath,
                'contract_status' => Contract::STATUS_SENT,
                'signed_at' => null,
                'signer_user_id' => null,
                'signature_method' => Contract::SIGNATURE_METHOD_TYPED,
                'audit_metadata_json' => [],
            ]);
        }

        return compact('company', 'admin', 'candidateUser', 'candidate', 'application', 'contract');
    }

    private function toRelativeUrl(string $absoluteOrRelative): string
    {
        $path = (string) parse_url($absoluteOrRelative, PHP_URL_PATH);
        $query = (string) parse_url($absoluteOrRelative, PHP_URL_QUERY);

        return $query !== '' ? "{$path}?{$query}" : $path;
    }
}

