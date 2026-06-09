<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    use ResolvesManagedCompany;

    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $companyId = $this->managedCompanyId($request, $actor->isSuperadmin());

        if ($actor->isSuperadmin() && $companyId === null) {
            return view('admin.master-data.departments', [
                'departments' => collect(),
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'selectedCompanyId' => null,
                'requiresCompanySelection' => true,
            ]);
        }

        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $departments = Department::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return view('admin.master-data.departments', [
            'departments' => $departments,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedCompanyId' => $companyId,
            'requiresCompanySelection' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 422);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        Department::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'name' => $validated['name'],
        ]);

        return back()->with('status', __('master.departments.created'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $department->company_id === (string) $companyId, 403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')
                    ->where(fn ($q) => $q->where('company_id', $companyId))
                    ->ignore($department->id),
            ],
        ]);

        $department->update([
            'name' => $validated['name'],
        ]);

        return back()->with('status', __('master.departments.updated'));
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $department->company_id === (string) $companyId, 403);

        $department->delete();

        return back()->with('status', __('master.departments.deleted'));
    }
}
