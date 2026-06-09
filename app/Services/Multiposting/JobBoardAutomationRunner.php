<?php

namespace App\Services\Multiposting;

use App\Models\Job;
use App\Models\JobPosting;
use App\Support\Jobs\JobDescriptionContentRenderer;
use App\Support\Tracking\JobApplicationUrlGenerator;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class JobBoardAutomationRunner
{
    public function __construct(
        private readonly JobApplicationUrlGenerator $urlGenerator,
        private readonly JobDescriptionContentRenderer $descriptionRenderer
    ) {
    }

    public function role(): string
    {
        $role = Str::lower(trim((string) config('multiposting.automation.role', 'fallback')));

        return in_array($role, ['fallback', 'secondary'], true)
            ? $role
            : 'fallback';
    }

    public function isEnabledForPlatform(string $platform): bool
    {
        if (! (bool) config('multiposting.automation.enabled', false)) {
            return false;
        }

        $platforms = array_map(
            static fn ($value): string => Str::lower(trim((string) $value)),
            (array) config('multiposting.automation.platforms', [])
        );

        return in_array(Str::lower(trim($platform)), $platforms, true);
    }

    public function queueName(): string
    {
        return (string) config('multiposting.automation.queue', 'automation');
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticsForPlatform(string $platform): array
    {
        $scriptPath = base_path((string) config('multiposting.automation.script_path', 'scripts/rpa/post-job.mjs'));

        return [
            'enabled' => $this->isEnabledForPlatform($platform),
            'role' => $this->role(),
            'queue' => $this->queueName(),
            'headless' => (bool) config('multiposting.automation.headless', true),
            'script_path' => $scriptPath,
            'script_exists' => is_file($scriptPath),
            'screenshot_dir' => $this->resolveScreenshotDirectory(),
        ];
    }

    public function run(JobPosting $posting): JobBoardAutomationResult
    {
        $posting->loadMissing('job.company');

        if (! $posting->job instanceof Job || ! $posting->job->company) {
            return JobBoardAutomationResult::failure(
                'Job context not found for automation worker.',
                null,
                [],
                'job_context_missing'
            );
        }

        if (! $this->isEnabledForPlatform((string) $posting->platform)) {
            return JobBoardAutomationResult::failure(
                'Automation fallback is disabled for this platform.',
                null,
                [],
                'automation_disabled'
            );
        }

        $payload = $this->buildPayload($posting);
        $input = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($input) || $input === '') {
            return JobBoardAutomationResult::failure(
                'Unable to encode automation payload.',
                null,
                [],
                'payload_encoding_failed'
            );
        }

        $nodeBinary = trim((string) config('multiposting.automation.node_binary', 'node'));
        $scriptPath = base_path((string) config('multiposting.automation.script_path', 'scripts/rpa/post-job.mjs'));
        $timeout = (int) config('multiposting.automation.timeout_seconds', 180);
        $screenshotDir = $this->resolveScreenshotDirectory();

        if (! is_file($scriptPath)) {
            return JobBoardAutomationResult::failure(
                'Automation fallback script is missing.',
                null,
                ['script_path' => $scriptPath],
                'script_missing'
            );
        }

        $process = new Process(
            [$nodeBinary !== '' ? $nodeBinary : 'node', $scriptPath, '--platform', (string) $posting->platform],
            base_path(),
            [
                'RPA_HEADLESS' => (bool) config('multiposting.automation.headless', true) ? 'true' : 'false',
                'RPA_SCREENSHOT_DIR' => $screenshotDir,
                'RPA_AUTOMATION_ROLE' => $this->role(),
            ],
            $input,
            $timeout
        );

        try {
            $process->run();
        } catch (Throwable $exception) {
            return JobBoardAutomationResult::failure(
                $exception->getMessage(),
                null,
                [],
                'worker_exception'
            );
        }

        $stdout = trim((string) $process->getOutput());
        $stderr = trim((string) $process->getErrorOutput());
        $parsed = $this->parseJsonOutput($stdout);

        if (! is_array($parsed)) {
            $message = $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'Automation worker returned non-JSON output.');

            return JobBoardAutomationResult::failure(
                $message,
                null,
                [
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                ],
                'worker_output_invalid'
            );
        }

        $ok = (bool) ($parsed['ok'] ?? false);
        $errorMessage = trim((string) ($parsed['error'] ?? $stderr));
        $screenshotPath = trim((string) ($parsed['screenshotPath'] ?? ''));
        $externalUrl = trim((string) ($parsed['externalUrl'] ?? ''));

        if (! $process->isSuccessful() || ! $ok) {
            return JobBoardAutomationResult::failure(
                $errorMessage !== '' ? $errorMessage : 'Automation worker failed.',
                $screenshotPath !== '' ? $screenshotPath : null,
                $parsed,
                $this->classifyFailureCode($errorMessage, $parsed, $process->getExitCode())
            );
        }

        return JobBoardAutomationResult::success(
            $externalUrl !== '' ? $externalUrl : null,
            $parsed
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonOutput(string $output): ?array
    {
        if ($output === '') {
            return null;
        }

        $decoded = json_decode($output, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $lastLine = trim((string) end($lines));
        if ($lastLine === '') {
            return null;
        }

        $decoded = json_decode($lastLine, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function resolveScreenshotDirectory(): string
    {
        $configured = trim((string) config('multiposting.automation.screenshot_dir', 'storage/app/private/rpa_failures'));
        if ($configured === '') {
            $configured = 'storage/app/private/rpa_failures';
        }

        $looksAbsolute = Str::startsWith($configured, ['/', '\\']) || preg_match('/^[A-Za-z]:\\\\/', $configured) === 1;

        return $looksAbsolute ? $configured : base_path($configured);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(JobPosting $posting): array
    {
        $job = $posting->job;
        if (! $job instanceof Job) {
            return [];
        }

        $job->loadMissing('descriptionBlocks');

        return [
            'jobPostingId' => (string) $posting->id,
            'platform' => (string) $posting->platform,
            'automationRole' => $this->role(),
            'company' => [
                'id' => (string) ($job->company?->id ?? ''),
                'name' => (string) ($job->company?->name ?? ''),
                'slug' => (string) ($job->company?->slug ?? ''),
            ],
            'job' => [
                'id' => (string) $job->id,
                'title' => (string) $job->title,
                'descriptionHtml' => $this->descriptionRenderer->renderHtml($job),
                'location' => (string) ($job->location ?? ''),
                'locationStreet' => (string) ($job->location_street ?? ''),
                'locationCity' => (string) ($job->location_city ?? ''),
                'locationCountry' => (string) ($job->location_country ?? ''),
                'locationPostalCode' => (string) ($job->location_postal_code ?? ''),
                'employmentType' => (string) ($job->employment_type ?? Job::EMPLOYMENT_FULL_TIME),
                'salaryMin' => is_numeric($job->salary_min) ? (int) $job->salary_min : null,
                'salaryMax' => is_numeric($job->salary_max)
                    ? (int) $job->salary_max
                    : (is_numeric($job->salary_budget_max) ? (int) $job->salary_budget_max : null),
                'salaryCurrency' => (string) ($job->salary_currency ?? ''),
                'applyUrl' => $this->urlGenerator->forFeed($job, (string) $posting->platform),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $parsed
     */
    private function classifyFailureCode(string $errorMessage, array $parsed, ?int $exitCode): string
    {
        $explicitCode = trim((string) ($parsed['failureCode'] ?? ''));
        if ($explicitCode !== '') {
            return Str::snake($explicitCode);
        }

        $haystack = Str::lower($errorMessage);

        if (str_contains($haystack, 'login') || str_contains($haystack, 'session expired')) {
            return 'login_expired';
        }

        if (str_contains($haystack, 'selector') || str_contains($haystack, 'locator')) {
            return 'selector_drift';
        }

        if ($exitCode !== null && $exitCode !== 0) {
            return 'worker_process_failed';
        }

        return 'automation_failed';
    }
}
