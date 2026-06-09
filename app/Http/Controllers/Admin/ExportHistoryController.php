<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ResolvesManagedCompany;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Export;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExportHistoryController extends Controller
{
    use ResolvesManagedCompany;

    public function index(Request $request): View
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $companies = $actor->isSuperadmin()
            ? Company::query()->where('status', Company::STATUS_ACTIVE)->orderBy('name')->get(['id', 'name'])
            : collect();

        $companyId = $this->managedCompanyId($request, false);
        if ($actor->isSuperadmin() && (! is_string($companyId) || $companyId === '')) {
            return view('admin.exports.index', [
                'requiresCompanySelection' => true,
                'companies' => $companies,
                'selectedCompanyId' => null,
                'filters' => [
                    'export_type' => null,
                    'status' => null,
                    'format' => null,
                ],
                'exports' => collect(),
                'exportTypes' => Export::types(),
                'statuses' => Export::statuses(),
                'formats' => Export::formats(),
            ]);
        }

        abort_unless(is_string($companyId) && $companyId !== '', 403);

        $validated = $request->validate([
            'export_type' => ['nullable', Rule::in(Export::types())],
            'status' => ['nullable', Rule::in(Export::statuses())],
            'format' => ['nullable', Rule::in(Export::formats())],
        ]);

        $filters = [
            'export_type' => isset($validated['export_type']) ? (string) $validated['export_type'] : null,
            'status' => isset($validated['status']) ? (string) $validated['status'] : null,
            'format' => isset($validated['format']) ? (string) $validated['format'] : null,
        ];

        $exports = Export::withoutGlobalScopes()
            ->with(['requestedBy.profile'])
            ->where('company_id', $companyId)
            ->when(
                is_string($filters['export_type']) && $filters['export_type'] !== '',
                fn ($query) => $query->where('export_type', $filters['export_type'])
            )
            ->when(
                is_string($filters['status']) && $filters['status'] !== '',
                fn ($query) => $query->where('status', $filters['status'])
            )
            ->when(
                is_string($filters['format']) && $filters['format'] !== '',
                fn ($query) => $query->where('format', $filters['format'])
            )
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.exports.index', [
            'requiresCompanySelection' => false,
            'companies' => $companies,
            'selectedCompanyId' => $companyId,
            'filters' => $filters,
            'exports' => $exports,
            'exportTypes' => Export::types(),
            'statuses' => Export::statuses(),
            'formats' => Export::formats(),
        ]);
    }
}
