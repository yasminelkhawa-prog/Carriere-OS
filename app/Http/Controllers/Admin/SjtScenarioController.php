<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Job;
use App\Models\SjtScenario;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SjtScenarioController extends Controller
{
    use ResolvesManagedCompany;

    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, $actor->isSuperadmin());
        $filters = $request->validate([
            'job_id' => ['nullable', 'uuid'],
            'is_active' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        if ($actor->isSuperadmin() && $companyId === null) {
            return view('admin.sjt-scenarios.index', [
                'requiresCompanySelection' => true,
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'selectedCompanyId' => null,
                'jobs' => collect(),
                'selectedJobId' => null,
                'selectedActiveFilter' => $filters['is_active'] ?? 'all',
                'searchTerm' => trim((string) ($filters['q'] ?? '')),
                'scenarios' => collect(),
            ]);
        }

        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $jobs = Job::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('title')
            ->get(['id', 'title']);

        $selectedJobId = (string) ($filters['job_id'] ?? '');
        $selectedActiveFilter = (string) ($filters['is_active'] ?? 'all');
        $searchTerm = trim((string) ($filters['q'] ?? ''));

        $scenarios = SjtScenario::withoutGlobalScopes()
            ->with('job:id,title')
            ->withCount('responses')
            ->where('company_id', $companyId)
            ->when($selectedJobId !== '', fn ($query) => $query->where('job_id', $selectedJobId))
            ->when($selectedActiveFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($selectedActiveFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($searchTerm !== '', function ($query) use ($searchTerm): void {
                $query->where(function ($inner) use ($searchTerm): void {
                    $inner->where('title', 'like', '%'.$searchTerm.'%')
                        ->orWhere('scenario_text', 'like', '%'.$searchTerm.'%');
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.sjt-scenarios.index', [
            'requiresCompanySelection' => false,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedCompanyId' => $companyId,
            'jobs' => $jobs,
            'selectedJobId' => $selectedJobId !== '' ? $selectedJobId : null,
            'selectedActiveFilter' => $selectedActiveFilter,
            'searchTerm' => $searchTerm,
            'scenarios' => $scenarios,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 422);

        $validated = $request->validate([
            'job_id' => [
                'nullable',
                'uuid',
                Rule::exists('jobs', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'scenario_media_url' => ['nullable', 'url', 'max:2048'],
            'scenario_text' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        SjtScenario::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'job_id' => $validated['job_id'] ?? null,
            'title' => trim((string) $validated['title']),
            'scenario_media_url' => trim((string) ($validated['scenario_media_url'] ?? '')) ?: null,
            'scenario_text' => trim((string) $validated['scenario_text']),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('status', __('sjt.admin.messages.created'));
    }

    public function update(Request $request, SjtScenario $sjtScenario): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $sjtScenario->company_id === (string) $companyId, 403);

        $validated = $request->validate([
            'job_id' => [
                'nullable',
                'uuid',
                Rule::exists('jobs', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'scenario_media_url' => ['nullable', 'url', 'max:2048'],
            'scenario_text' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $sjtScenario->update([
            'job_id' => $validated['job_id'] ?? null,
            'title' => trim((string) $validated['title']),
            'scenario_media_url' => trim((string) ($validated['scenario_media_url'] ?? '')) ?: null,
            'scenario_text' => trim((string) $validated['scenario_text']),
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('status', __('sjt.admin.messages.updated'));
    }

    public function destroy(Request $request, SjtScenario $sjtScenario): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $sjtScenario->company_id === (string) $companyId, 403);

        $sjtScenario->delete();

        return back()->with('status', __('sjt.admin.messages.deleted'));
    }
}

