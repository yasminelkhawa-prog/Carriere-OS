<?php

namespace Database\Seeders;

use App\Models\ClickEvent;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobPosting;
use Illuminate\Database\Seeder;

class MultipostingModuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'numa-demo')->first()
            ?? Company::query()->where('status', Company::STATUS_ACTIVE)->first();

        if (! $company instanceof Company) {
            $company = Company::query()->create([
                'name' => 'numa Demo',
                'slug' => 'numa-demo',
                'status' => Company::STATUS_ACTIVE,
                'brand_logo_url' => null,
            ]);
        }

        $job = Job::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $linkedinPosting = JobPosting::withoutGlobalScopes()->updateOrCreate(
            [
                'job_id' => $job->id,
                'platform' => 'linkedin',
            ],
            [
                'company_id' => $company->id,
                'status' => JobPosting::STATUS_PUBLISHED,
                'ai_generated_content' => 'AI adapted copy for LinkedIn demo posting.',
                'tracking_url' => null,
                'clicks_count' => 0,
                'posted_at' => now(),
            ]
        );

        $linkedinPosting->forceFill([
            'tracking_url' => route('career.multiposting.track', [
                'company' => $company,
                'job' => $job,
                'jobPosting' => $linkedinPosting,
                'utm_source' => 'linkedin',
                'utm_medium' => 'job_board',
                'utm_campaign' => 'job-'.$job->id,
            ]),
        ])->save();

        JobPosting::withoutGlobalScopes()->updateOrCreate(
            [
                'job_id' => $job->id,
                'platform' => 'indeed',
            ],
            [
                'company_id' => $company->id,
                'status' => JobPosting::STATUS_READY,
                'ai_generated_content' => 'AI adapted copy for Indeed demo posting.',
                'tracking_url' => null,
                'clicks_count' => 0,
                'posted_at' => null,
            ]
        );

        ClickEvent::withoutGlobalScopes()
            ->where('job_posting_id', $linkedinPosting->id)
            ->delete();

        foreach (range(1, 5) as $index) {
            ClickEvent::withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'job_posting_id' => $linkedinPosting->id,
                'clicked_at' => now()->subMinutes(6 - $index),
                'user_agent' => 'Seeder Multiposting Bot',
                'ip_address' => '127.0.0.1',
            ]);
        }

        $linkedinPosting->forceFill(['clicks_count' => 5])->save();

        $this->command?->info('Multiposting module sample data seeded.');
        $this->command?->line('Enabled platforms: LinkedIn and Indeed for one job.');
        $this->command?->line('LinkedIn is published with 5 generated click events.');
    }
}

