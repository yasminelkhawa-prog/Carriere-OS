<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyValue;
use App\Models\ContactInquiry;
use App\Models\Job;
use App\Support\Audit\SensitiveEventRecorder;
use App\Support\Seo\JobPostingSchemaBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicSiteController extends Controller
{
    public function __construct(
        private readonly SensitiveEventRecorder $sensitiveEvents,
        private readonly JobPostingSchemaBuilder $jobPostingSchemaBuilder
    )
    {
    }

    public function home(): View
    {
        $jobsQuery = $this->publishedJobsQuery();

        $latestJobs = (clone $jobsQuery)
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $totalOpenJobs = (clone $jobsQuery)->count();

        $activeHiringCompanies = Company::query()
            ->where('status', Company::STATUS_ACTIVE)
            ->whereHas('jobs', fn ($query) => $query->where('status', Job::STATUS_PUBLISHED))
            ->count();

        [$featuredCompany, $values] = $this->resolveFeaturedCompanyValues();

        return view('public.home', [
            'latestJobs' => $latestJobs,
            'totalOpenJobs' => $totalOpenJobs,
            'activeHiringCompanies' => $activeHiringCompanies,
            'featuredCompany' => $featuredCompany,
            'values' => $values,
        ]);
    }

    public function companyEntry(): RedirectResponse
    {
        return redirect()->route('login', ['audience' => 'company']);
    }

    public function candidateEntry(): RedirectResponse
    {
        return redirect()->route('public.jobs.index', ['audience' => 'candidate']);
    }

    public function jobs(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'company_id' => [
                'nullable',
                'uuid',
                Rule::exists('companies', 'id')->where(
                    fn ($query) => $query->where('status', Company::STATUS_ACTIVE)
                ),
            ],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $companyId = isset($filters['company_id']) ? (string) $filters['company_id'] : '';
        $location = trim((string) ($filters['location'] ?? ''));

        $jobsQuery = $this->publishedJobsQuery()
            ->when($search !== '', function ($query) use ($search): void {
                $pattern = '%'.Str::lower($search).'%';

                $query->where(function ($subQuery) use ($pattern): void {
                    $subQuery
                        ->whereRaw('LOWER(title) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(COALESCE(location, \'\')) LIKE ?', [$pattern])
                        ->orWhereHas('company', fn ($companyQuery) => $companyQuery->whereRaw('LOWER(name) LIKE ?', [$pattern]));
                });
            })
            ->when($companyId !== '', fn ($query) => $query->where('company_id', $companyId))
            ->when($location !== '', fn ($query) => $query->where('location', $location));

        $jobs = $jobsQuery
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        $companies = Company::query()
            ->where('status', Company::STATUS_ACTIVE)
            ->whereHas('jobs', fn ($query) => $query->where('status', Job::STATUS_PUBLISHED))
            ->orderBy('name')
            ->get(['id', 'name']);

        $locations = $this->publishedJobsQuery()
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location');

        return view('public.jobs.index', [
            'jobs' => $jobs,
            'companies' => $companies,
            'locations' => $locations,
            'filters' => [
                'q' => $search,
                'company_id' => $companyId,
                'location' => $location,
            ],
        ]);
    }

    public function showJob(Job $job): View
    {
        $job->loadMissing([
            'company:id,name,slug,status',
            'department:id,name',
            'descriptionBlocks',
        ]);

        $company = $job->company;
        abort_unless($company instanceof Company, 404);
        abort_unless($company->status === Company::STATUS_ACTIVE, 404);
        abort_unless((string) $job->status === Job::STATUS_PUBLISHED, 404);

        $relatedJobs = $this->publishedJobsQuery()
            ->where('company_id', (string) $company->id)
            ->where('id', '!=', (string) $job->id)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        return view('public.jobs.show', [
            'job' => $job,
            'company' => $company,
            'relatedJobs' => $relatedJobs,
            'jobPostingSchema' => $this->jobPostingSchemaBuilder->build(
                job: $job,
                company: $company,
                publicUrl: route('public.jobs.show', ['job' => $job])
            ),
        ]);
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function contact(): View
    {
        return view('public.contact');
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'subject' => ['required', 'string', 'min:3', 'max:180'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
            'website' => ['nullable', 'max:0'], // Honeypot field for simple bot filtering.
        ], [
            'website.max' => __('public_site.contact.validation.spam_detected'),
        ]);

        $inquiry = ContactInquiry::query()->create([
            'full_name' => trim((string) $validated['full_name']),
            'email' => Str::lower(trim((string) $validated['email'])),
            'phone' => ($validated['phone'] ?? null) !== null ? trim((string) $validated['phone']) : null,
            'subject' => trim((string) $validated['subject']),
            'message' => trim((string) $validated['message']),
            'status' => ContactInquiry::STATUS_NEW,
            'assigned_to_user_id' => null,
            'notes' => null,
            'source' => ContactInquiry::SOURCE_PUBLIC_CONTACT_FORM,
        ]);

        $this->sensitiveEvents->record(
            actionType: 'contact_inquiry.created',
            entityType: 'contact_inquiry',
            entityId: (string) $inquiry->id,
            metadata: ['source' => ContactInquiry::SOURCE_PUBLIC_CONTACT_FORM],
            actor: null
        );

        return redirect()
            ->route('public.contact')
            ->with('status', __('public_site.contact.flash.submitted'));
    }

    private function publishedJobsQuery()
    {
        return Job::withoutGlobalScopes()
            ->with([
                'company:id,name,slug,status',
                'department:id,name',
            ])
            ->where('status', Job::STATUS_PUBLISHED)
            ->whereHas('company', fn ($query) => $query->where('status', Company::STATUS_ACTIVE));
    }

    /**
     * @return array{0: ?Company, 1: Collection<int, CompanyValue>}
     */
    private function resolveFeaturedCompanyValues(): array
    {
        $featuredCompany = Company::query()
            ->where('status', Company::STATUS_ACTIVE)
            ->whereHas('jobs', fn ($query) => $query->where('status', Job::STATUS_PUBLISHED))
            ->withCount([
                'jobs as published_jobs_count' => fn ($query) => $query->where('status', Job::STATUS_PUBLISHED),
            ])
            ->orderByDesc('published_jobs_count')
            ->orderBy('name')
            ->first(['id', 'name', 'slug']);

        if (! $featuredCompany instanceof Company) {
            return [null, collect()];
        }

        $values = CompanyValue::withoutGlobalScopes()
            ->where('company_id', (string) $featuredCompany->id)
            ->orderBy('display_order')
            ->orderBy('title')
            ->limit(8)
            ->get();

        return [$featuredCompany, $values];
    }
}
