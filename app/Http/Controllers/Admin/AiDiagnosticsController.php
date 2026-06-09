<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiRequestService;
use App\Support\Ai\AiPayloadRedactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiDiagnosticsController extends Controller
{
    public function __construct(
        private readonly AiPayloadRedactor $redactor,
        private readonly AiRequestService $aiRequestService
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return redirect()->route('login');
        }

        $filters = $request->validate([
            'company_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in([
                AiRequest::STATUS_QUEUED,
                AiRequest::STATUS_RUNNING,
                AiRequest::STATUS_SUCCEEDED,
                AiRequest::STATUS_FAILED,
            ])],
            'request_type' => ['nullable', 'string', 'max:100'],
        ]);

        $selectedCompanyId = $this->resolveSelectedCompanyId($actor, $filters);

        if (! $actor->isSuperadmin() && $selectedCompanyId === null) {
            return redirect()->route('auth.company.dispatch');
        }

        $requiresCompanyFilter = $actor->isSuperadmin() && $selectedCompanyId === null;

        $query = AiRequest::query()
            ->withCount('artifacts')
            ->orderByDesc('created_at');

        if ($selectedCompanyId !== null) {
            $query->where('company_id', $selectedCompanyId);
        }

        if (! $requiresCompanyFilter) {
            if (($filters['status'] ?? null) !== null) {
                $query->where('status', $filters['status']);
            }
            if (($filters['request_type'] ?? null) !== null) {
                $query->where('request_type', $filters['request_type']);
            }
        } else {
            $query->whereRaw('1 = 0');
        }

        $aiRequests = $query->paginate(25)->withQueryString();

        $requestTypes = AiRequest::query()
            ->when($requiresCompanyFilter, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($selectedCompanyId !== null, fn ($q) => $q->where('company_id', $selectedCompanyId))
            ->select('request_type')
            ->distinct()
            ->orderBy('request_type')
            ->pluck('request_type');

        $aiRequests->getCollection()->transform(function (AiRequest $aiRequest): AiRequest {
            $redactedRequest = $this->redactor->redact($aiRequest->request_payload);
            $redactedResponse = $this->redactor->redact($aiRequest->response_payload);

            $aiRequest->setAttribute('request_preview', json_encode($redactedRequest, JSON_UNESCAPED_SLASHES));
            $aiRequest->setAttribute('response_preview', json_encode($redactedResponse, JSON_UNESCAPED_SLASHES));

            return $aiRequest;
        });

        return view('admin.ai-diagnostics.index', [
            'aiRequests' => $aiRequests,
            'requestTypes' => $requestTypes,
            'companies' => $actor->isSuperadmin()
                ? Company::query()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'selectedCompanyId' => $selectedCompanyId,
            'selectedStatus' => $filters['status'] ?? null,
            'selectedRequestType' => $filters['request_type'] ?? null,
            'requiresCompanyFilter' => $requiresCompanyFilter,
        ]);
    }

    public function retry(Request $request, AiRequest $aiRequest): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        if ($actor->isSuperadmin()) {
            $validated = $request->validate([
                'company_id' => ['required', 'uuid'],
            ]);
            abort_unless((string) $aiRequest->company_id === (string) $validated['company_id'], 403);
        } else {
            $activeCompanyId = session('active_company_id');
            abort_unless((string) $aiRequest->company_id === (string) $activeCompanyId, 403);
        }

        $requestModel = AiRequest::withoutGlobalScopes()->findOrFail($aiRequest->id);
        $this->aiRequestService->retry($requestModel);

        return back()->with('status', __('ui.ai_diagnostics.retry_queued'));
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function resolveSelectedCompanyId(User $actor, array $filters): ?string
    {
        if ($actor->isSuperadmin()) {
            return Arr::get($filters, 'company_id');
        }

        $activeCompanyId = session('active_company_id');

        return is_string($activeCompanyId) && $activeCompanyId !== '' ? $activeCompanyId : null;
    }
}
