<?php

namespace App\Console\Commands;

use App\Jobs\PruneCompanyRetentionDataJob;
use App\Models\Company;
use Illuminate\Console\Command;

class PruneRetentionDataCommand extends Command
{
    protected $signature = 'retention:prune {--company_id= : Optional company UUID to prune for a single tenant}';
    protected $description = 'Queue retention pruning jobs for video responses and AI artifacts.';

    public function handle(): int
    {
        $companyId = trim((string) $this->option('company_id'));

        if ($companyId !== '') {
            $companyExists = Company::query()->whereKey($companyId)->exists();
            if (! $companyExists) {
                $this->error('Company not found for the provided company_id.');

                return self::FAILURE;
            }

            PruneCompanyRetentionDataJob::dispatch($companyId);
            $this->info("Queued retention prune for company {$companyId}.");

            return self::SUCCESS;
        }

        $companyIds = Company::query()
            ->where('status', Company::STATUS_ACTIVE)
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id);

        if ($companyIds->isEmpty()) {
            $this->warn('No active companies found to prune.');

            return self::SUCCESS;
        }

        foreach ($companyIds as $id) {
            PruneCompanyRetentionDataJob::dispatch($id);
        }

        $this->info('Queued retention prune jobs for '.$companyIds->count().' companies.');

        return self::SUCCESS;
    }
}
