<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\JobPipelineStage;
use App\Models\PsyTest;
use App\Models\SjtScenario;
use App\Models\User;
use App\Notifications\TechAssessmentRequiredNotification;
use App\Services\PsyTestService;
use App\Mail\PsyTestInvitationMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class ProcessApplicationStageChangeJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Application $application,
        public JobPipelineStage $newStage
    ) {}

    public function handle(PsyTestService $psyTestService): void
    {
        $stageKey = $this->newStage->stage_key;

        if ($stageKey === 'screening') {
            $this->handleScreeningStage($psyTestService);
        } elseif ($stageKey === 'interview') {
            $this->handleInterviewStage();
        }
    }

    private function handleScreeningStage(PsyTestService $psyTestService): void
    {
        $job = $this->application->job;
        if (!$job || empty($job->job_family)) {
            return;
        }

        // Check if PsyTest already exists
        if (PsyTest::where('application_id', $this->application->id)->exists()) {
            return;
        }

        $candidate = $this->application->candidate;
        $fullName = $candidate->full_name ?? 'Candidat';
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $psyTest = PsyTest::create([
            'company_id' => $this->application->company_id,
            'application_id' => $this->application->id,
            'token' => $psyTestService->generateToken(),
            'candidate_first_name' => $firstName,
            'candidate_last_name' => $lastName,
            'candidate_email' => $candidate->email ?? 'no-reply@example.com',
            'profile' => $job->job_family,
            'status' => PsyTest::STATUS_PENDING,
            'expires_at' => now()->addHours(72),
        ]);

        $testUrl = route('public.psy-test.show', ['token' => $psyTest->token]);
        Mail::to($psyTest->candidate_email)->send(new PsyTestInvitationMail($psyTest, $testUrl));
    }

    private function handleInterviewStage(): void
    {
        // Check if SJT scenario or response exists for this candidate/job
        $hasSjt = SjtScenario::where('job_id', $this->application->job_id)
            // SjtScenario is usually tied to Job, wait, does the candidate have an assigned SJT?
            // Let's check candidate_sjt or sjt_responses table?
            // I should just check if any SjtScenario exists for the Job, or if candidate took it.
            // Wait, "generate a technical assessment if there is none"
            ->exists();

        // Let's actually check if SjtScenario exists for this job. If the Job has an SjtScenario, 
        // does the candidate need a specific generation? No, SJT is usually per Job.
        // Wait, the user said: "notification to the admin that he needs to generate a technical assessment if there is none and the candidate is in the interview phase".
        if (!$hasSjt) {
            $admins = User::whereHas('memberships', function ($q) {
                $q->where('company_id', $this->application->company_id)
                  ->whereIn('company_role', [User::ROLE_COMPANY_ADMIN, User::ROLE_RECRUITER]);
            })->get();

            Notification::send($admins, new TechAssessmentRequiredNotification($this->application));
        }
    }
}
