<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyValue;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyValueController extends Controller
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
            return view('admin.master-data.values', [
                'values' => collect(),
                'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
                'selectedCompanyId' => null,
                'requiresCompanySelection' => true,
            ]);
        }

        if (! $actor->isSuperadmin() && $companyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $values = CompanyValue::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->orderBy('display_order')
            ->orderBy('created_at')
            ->get();

        return view('admin.master-data.values', [
            'values' => $values,
            'companies' => $actor->isSuperadmin() ? Company::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'selectedCompanyId' => $companyId,
            'requiresCompanySelection' => false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null, 422);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon_name' => ['nullable', 'string', 'max:100'],
            'display_order' => ['required', 'integer', 'min:1'],
        ]);

        CompanyValue::withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'icon_name' => $validated['icon_name'] ?? null,
            'display_order' => $validated['display_order'],
        ]);

        return back()->with('status', __('master.values.created'));
    }

    public function update(Request $request, CompanyValue $companyValue): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $companyValue->company_id === (string) $companyId, 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon_name' => ['nullable', 'string', 'max:100'],
            'display_order' => ['required', 'integer', 'min:1'],
        ]);

        $companyValue->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'icon_name' => $validated['icon_name'] ?? null,
            'display_order' => $validated['display_order'],
        ]);

        return back()->with('status', __('master.values.updated'));
    }

    public function destroy(Request $request, CompanyValue $companyValue): RedirectResponse
    {
        $companyId = $this->managedCompanyId($request, true);
        abort_unless($companyId !== null && (string) $companyValue->company_id === (string) $companyId, 403);

        $companyValue->delete();

        return back()->with('status', __('master.values.deleted'));
    }
}
