<?php

namespace App\Http\Controllers;

use App\Models\ClickEvent;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobPostingTrackingController extends Controller
{
    public function __invoke(Request $request, Company $company, Job $job, JobPosting $jobPosting): RedirectResponse
    {
        abort_unless((string) $job->company_id === (string) $company->id, 404);
        abort_unless($job->status === Job::STATUS_PUBLISHED, 404);
        abort_unless((string) $jobPosting->company_id === (string) $company->id, 404);
        abort_unless((string) $jobPosting->job_id === (string) $job->id, 404);
        abort_unless($jobPosting->status === JobPosting::STATUS_PUBLISHED, 404);
        abort_unless($jobPosting->tracking_url !== null, 404);

        DB::transaction(function () use ($request, $jobPosting): void {
            JobPosting::withoutGlobalScopes()
                ->whereKey($jobPosting->id)
                ->update([
                    'clicks_count' => DB::raw('clicks_count + 1'),
                    'updated_at' => now(),
                ]);

            ClickEvent::withoutGlobalScopes()->create([
                'company_id' => (string) $jobPosting->company_id,
                'job_posting_id' => (string) $jobPosting->id,
                'clicked_at' => now(),
                'user_agent' => $request->userAgent(),
                'ip_address' => $request->ip(),
            ]);
        });

        $query = $request->query();
        $query['utm_source'] = trim((string) ($query['utm_source'] ?? '')) ?: (string) $jobPosting->platform;
        $query['utm_medium'] = trim((string) ($query['utm_medium'] ?? '')) ?: 'job_board';
        $query['utm_campaign'] = trim((string) ($query['utm_campaign'] ?? '')) ?: 'job-'.$job->id;

        return redirect()->route('career.show', array_merge([
            'company' => $company,
            'job' => $job,
        ], $query));
    }
}
