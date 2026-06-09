<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobPosting;
use App\Services\Multiposting\MultipostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class JobMultipostingController extends Controller
{
    use ResolvesManagedCompany;

    public function __construct(
        private readonly MultipostingService $multiposting
    ) {
    }

    public function toggle(Request $request, Job $job, string $platform): RedirectResponse
    {
        $this->assertManagedJob($request, $job);
        $this->assertSupportedPlatform($platform);

        $posting = $this->multiposting->getOrCreatePosting($job, $platform);

        if ($posting->status === \App\Models\JobPosting::STATUS_DISABLED) {
            $this->multiposting->enable($posting, $request->user());

            return back()->with('status', __('jobs.multiposting.flash.enabled', [
                'platform' => $this->platformLabel($platform),
            ]));
        }

        $this->multiposting->disable($posting, $request->user());

        return back()->with('status', __('jobs.multiposting.flash.disabled', [
            'platform' => $this->platformLabel($platform),
        ]));
    }

    public function generate(Request $request, Job $job, string $platform): RedirectResponse
    {
        $this->assertManagedJob($request, $job);
        $this->assertSupportedPlatform($platform);

        $posting = $this->multiposting->getOrCreatePosting($job, $platform);
        $this->multiposting->enable($posting, $request->user());

        try {
            $this->multiposting->generateContent($posting, $request->user());
        } catch (Throwable $exception) {
            return back()->with('error', __('jobs.multiposting.flash.generation_failed', [
                'platform' => $this->platformLabel($platform),
                'error' => $exception->getMessage(),
            ]));
        }

        return back()->with('status', __('jobs.multiposting.flash.generated', [
            'platform' => $this->platformLabel($platform),
        ]));
    }

    public function bulk(Request $request, Job $job): RedirectResponse
    {
        $this->assertManagedJob($request, $job);

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:enable,generate,publish'],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', 'string'],
        ]);

        $action = (string) $validated['action'];
        $platforms = collect((array) $validated['platforms'])
            ->map(static fn (mixed $platform): string => trim((string) $platform))
            ->filter(fn (string $platform): bool => $platform !== '')
            ->unique()
            ->values();

        $completed = [];
        $failed = [];

        foreach ($platforms as $platform) {
            if (! $this->multiposting->isSupportedPlatform($platform)) {
                $failed[] = $this->platformLabel($platform).' (unsupported)';
                continue;
            }

            $posting = $this->multiposting->getOrCreatePosting($job, $platform);

            try {
                if ($action === 'enable') {
                    $this->multiposting->enable($posting, $request->user());
                } elseif ($action === 'generate') {
                    $this->multiposting->enable($posting, $request->user());
                    $this->multiposting->generateContent($posting, $request->user());
                } elseif ($action === 'publish') {
                    $this->multiposting->enable($posting, $request->user());
                    $this->multiposting->publish($posting, $request->user());
                }

                $completed[] = $this->platformLabel($platform);
            } catch (Throwable $exception) {
                $failed[] = $this->platformLabel($platform).': '.$exception->getMessage();
            }
        }

        $redirect = back();

        if ($completed !== []) {
            $redirect = $redirect->with('status', __('jobs.multiposting.flash.bulk_completed', [
                'action' => __('jobs.multiposting.bulk.actions.'.$action),
                'platforms' => implode(', ', $completed),
            ]));
        }

        if ($failed !== []) {
            $redirect = $redirect->with('error', __('jobs.multiposting.flash.bulk_failed', [
                'action' => __('jobs.multiposting.bulk.actions.'.$action),
                'details' => implode(' | ', $failed),
            ]));
        }

        return $redirect;
    }

    public function saveContent(Request $request, Job $job, string $platform): RedirectResponse
    {
        $this->assertManagedJob($request, $job);
        $this->assertSupportedPlatform($platform);

        $validated = $request->validate([
            'ai_generated_content' => ['required', 'string', 'max:20000'],
        ]);

        $posting = $this->multiposting->getOrCreatePosting($job, $platform);
        $this->multiposting->enable($posting, $request->user());

        try {
            $this->multiposting->saveEditedContent(
                posting: $posting,
                content: (string) $validated['ai_generated_content'],
                actor: $request->user()
            );
        } catch (Throwable $exception) {
            return back()->with('error', __('jobs.multiposting.flash.content_save_failed', [
                'platform' => $this->platformLabel($platform),
                'error' => $exception->getMessage(),
            ]));
        }

        return back()->with('status', __('jobs.multiposting.flash.content_saved', [
            'platform' => $this->platformLabel($platform),
        ]));
    }

    public function publish(Request $request, Job $job, string $platform): RedirectResponse
    {
        $this->assertManagedJob($request, $job);
        $this->assertSupportedPlatform($platform);

        $posting = $this->multiposting->getOrCreatePosting($job, $platform);
        $this->multiposting->enable($posting, $request->user());

        try {
            $posting = $this->multiposting->publish($posting, $request->user());
        } catch (Throwable $exception) {
            return back()->with('error', __('jobs.multiposting.flash.publish_failed', [
                'platform' => $this->platformLabel($platform),
                'error' => $exception->getMessage(),
            ]));
        }

        $flashKey = (string) $posting->status === JobPosting::STATUS_PUBLISHING
            ? 'jobs.multiposting.flash.publish_queued'
            : 'jobs.multiposting.flash.published';

        return back()->with('status', __($flashKey, [
            'platform' => $this->platformLabel($platform),
        ]));
    }

    public function retry(Request $request, Job $job, string $platform): RedirectResponse
    {
        $this->assertManagedJob($request, $job);
        $this->assertSupportedPlatform($platform);

        $posting = $this->multiposting->getOrCreatePosting($job, $platform);
        $this->multiposting->enable($posting, $request->user());

        try {
            $this->multiposting->retry($posting, $request->user());
        } catch (Throwable $exception) {
            return back()->with('error', __('jobs.multiposting.flash.retry_failed', [
                'platform' => $this->platformLabel($platform),
                'error' => $exception->getMessage(),
            ]));
        }

        return back()->with('status', __('jobs.multiposting.flash.retried', [
            'platform' => $this->platformLabel($platform),
        ]));
    }

    private function assertManagedJob(Request $request, Job $job): void
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $job->company_id === (string) $companyId, 403);
    }

    private function assertSupportedPlatform(string $platform): void
    {
        abort_unless($this->multiposting->isSupportedPlatform($platform), 404);
    }

    private function platformLabel(string $platform): string
    {
        $label = __('jobs.multiposting.platforms.'.$platform);

        return str_contains($label, 'jobs.multiposting.platforms.')
            ? ucfirst(str_replace('_', ' ', $platform))
            : $label;
    }
}
