<?php

namespace App\Services\Referral;

use App\Models\Application;
use App\Models\ApplicationActivityEvent;
use App\Models\ApplicationStageHistory;
use App\Models\Candidate;
use App\Models\CandidateDocument;
use App\Models\Job;
use App\Models\JobPipelineStage;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function convertToApplication(Referral $referral, Job $job, User $actor): Application
    {
        if ((string) $referral->company_id !== (string) $job->company_id) {
            throw ValidationException::withMessages([
                'job_id' => __('referrals.validation.job_invalid'),
            ]);
        }

        if (in_array((string) $referral->status, [Referral::STATUS_HIRED, Referral::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'referral' => __('referrals.validation.cannot_convert_terminal'),
            ]);
        }

        $existingLinkedApplication = Application::withoutGlobalScopes()
            ->where('company_id', $referral->company_id)
            ->where('source_type', 'referral')
            ->where('source_detail', (string) $referral->id)
            ->first();

        if ($existingLinkedApplication instanceof Application) {
            throw ValidationException::withMessages([
                'referral' => __('referrals.validation.already_converted'),
            ]);
        }

        $firstStage = JobPipelineStage::withoutGlobalScopes()
            ->where('job_id', $job->id)
            ->where('is_terminal', false)
            ->orderBy('display_order')
            ->first();

        if (! $firstStage instanceof JobPipelineStage) {
            throw ValidationException::withMessages([
                'job_id' => __('referrals.validation.missing_pipeline'),
            ]);
        }

        $candidateEmail = Str::lower(trim((string) $referral->candidate_email));
        $candidateName = trim((string) ($referral->candidate_name ?? ''));
        if ($candidateName === '') {
            $candidateName = Str::headline(Str::before($candidateEmail, '@'));
            if ($candidateName === '') {
                $candidateName = 'Referral Candidate';
            }
        }

        $application = DB::transaction(function () use ($referral, $job, $firstStage, $candidateEmail, $candidateName, $actor): Application {
            $candidate = Candidate::withoutGlobalScopes()->firstOrCreate(
                [
                    'company_id' => (string) $referral->company_id,
                    'email' => $candidateEmail,
                ],
                [
                    'user_id' => null,
                    'full_name' => $candidateName,
                    'phone' => null,
                    'location' => null,
                ]
            );

            if (trim((string) $candidate->full_name) === '') {
                $candidate->forceFill(['full_name' => $candidateName])->save();
            }

            $activeDuplicate = Application::withoutGlobalScopes()
                ->where('company_id', $referral->company_id)
                ->where('candidate_id', $candidate->id)
                ->where('job_id', $job->id)
                ->where('status', Application::STATUS_ACTIVE)
                ->exists();

            if ($activeDuplicate) {
                throw ValidationException::withMessages([
                    'referral' => __('referrals.validation.duplicate_active_application'),
                ]);
            }

            $application = Application::withoutGlobalScopes()->create([
                'company_id' => (string) $referral->company_id,
                'candidate_id' => (string) $candidate->id,
                'job_id' => (string) $job->id,
                'current_stage_id' => (string) $firstStage->id,
                'status' => Application::STATUS_ACTIVE,
                'source_type' => 'referral',
                'source_detail' => (string) $referral->id,
                'utm_source' => null,
                'utm_campaign' => null,
                'utm_medium' => null,
            ]);

            ApplicationStageHistory::withoutGlobalScopes()->create([
                'company_id' => (string) $referral->company_id,
                'application_id' => (string) $application->id,
                'from_stage_id' => null,
                'to_stage_id' => (string) $firstStage->id,
                'actor_user_id' => (string) $actor->id,
                'reason' => 'referral_conversion',
                'created_at' => now(),
            ]);

            ApplicationActivityEvent::withoutGlobalScopes()->create([
                'company_id' => (string) $referral->company_id,
                'application_id' => (string) $application->id,
                'event_type' => 'application.created',
                'payload' => [
                    'source_type' => 'referral',
                    'source_detail' => (string) $referral->id,
                    'referrer_user_id' => (string) $referral->referrer_user_id,
                ],
                'actor_user_id' => (string) $actor->id,
                'created_at' => now(),
            ]);

            $this->attachReferralResumeIfAvailable($referral, $candidate);

            $referral->forceFill([
                'status' => Referral::STATUS_CONVERTED,
                'updated_at' => now(),
            ])->save();

            return $application;
        });

        return $application;
    }

    private function attachReferralResumeIfAvailable(Referral $referral, Candidate $candidate): void
    {
        $resumePath = trim((string) $referral->resume_file_url);
        if ($resumePath === '' || ! Storage::disk('local')->exists($resumePath)) {
            return;
        }

        $alreadyAttached = CandidateDocument::withoutGlobalScopes()
            ->where('company_id', (string) $candidate->company_id)
            ->where('candidate_id', (string) $candidate->id)
            ->where('document_type', CandidateDocument::TYPE_RESUME)
            ->where('file_url', $resumePath)
            ->exists();

        if ($alreadyAttached) {
            return;
        }

        $mimeType = (string) (Storage::disk('local')->mimeType($resumePath) ?: 'application/octet-stream');
        $sizeBytes = (int) (Storage::disk('local')->size($resumePath) ?: 0);

        CandidateDocument::withoutGlobalScopes()->create([
            'company_id' => (string) $candidate->company_id,
            'candidate_id' => (string) $candidate->id,
            'document_type' => CandidateDocument::TYPE_RESUME,
            'file_url' => $resumePath,
            'original_filename' => basename($resumePath),
            'mime_type' => $mimeType,
            'file_size_bytes' => max(0, $sizeBytes),
            'created_at' => now(),
        ]);
    }

    public function syncFromApplication(Application $application): void
    {
        if (Str::lower(trim((string) $application->source_type)) !== 'referral') {
            return;
        }

        $referralId = trim((string) $application->source_detail);
        if ($referralId === '' || ! Str::isUuid($referralId)) {
            return;
        }

        $referral = Referral::withoutGlobalScopes()
            ->where('id', $referralId)
            ->where('company_id', (string) $application->company_id)
            ->first();

        if (! $referral instanceof Referral) {
            return;
        }

        $nextStatus = $this->statusForApplication($application);
        if ((string) $referral->status === $nextStatus) {
            return;
        }

        $referral->forceFill([
            'status' => $nextStatus,
            'updated_at' => now(),
        ])->save();
    }

    private function statusForApplication(Application $application): string
    {
        if ($application->status === Application::STATUS_HIRED) {
            return Referral::STATUS_HIRED;
        }

        if ($application->status === Application::STATUS_REJECTED) {
            return Referral::STATUS_REJECTED;
        }

        $stage = $application->relationLoaded('currentStage')
            ? $application->currentStage
            : JobPipelineStage::withoutGlobalScopes()->find($application->current_stage_id);

        if ($stage instanceof JobPipelineStage && $stage->is_terminal) {
            $stageMarker = Str::lower(trim((string) $stage->stage_key.' '.$stage->stage_label));
            if (str_contains($stageMarker, 'hire') || str_contains($stageMarker, 'onboard')) {
                return Referral::STATUS_HIRED;
            }

            if (str_contains($stageMarker, 'reject') || str_contains($stageMarker, 'declin')) {
                return Referral::STATUS_REJECTED;
            }
        }

        return Referral::STATUS_CONVERTED;
    }
}
