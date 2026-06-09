<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AiRequest;
use App\Models\Company;
use App\Models\User;
use App\Services\Ai\AiRequestService;
use App\Support\Ai\AiPayloadRedactor;
use App\Support\Audit\AuditActionType;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiDiagnosticsController extends Controller
{
    /**
     * Request types available for test creation (dev/local only).
     *
     * @var array<int, string>
     */
    public const TEST_REQUEST_TYPES = [
        'candidate_analysis_json',
        'email_draft',
        'executive_summary',
        'sentiment_analysis',
    ];

    public function __construct(
        private readonly AiRequestService $aiRequestService,
        private readonly AiPayloadRedactor $redactor,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('access-platform-console');

        $companyId   = $request->input('company_id');
        $status      = $request->input('status');
        $requestType = $request->input('request_type');
        $search      = (string) $request->input('search', '');

        $query = AiRequest::withoutGlobalScopes()
            ->with('company:id,name')
            ->withCount('artifacts')
            ->orderByDesc('created_at');

        if ($companyId && $companyId !== '') {
            $query->where('company_id', $companyId);
        }

        if ($status && in_array($status, [
            AiRequest::STATUS_QUEUED,
            AiRequest::STATUS_RUNNING,
            AiRequest::STATUS_SUCCEEDED,
            AiRequest::STATUS_FAILED,
        ], true)) {
            $query->where('status', $status);
        }

        if ($requestType && $requestType !== '') {
            $query->where('request_type', $requestType);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like): void {
                $q->where('id', 'ilike', $like)
                    ->orWhere('request_type', 'ilike', $like)
                    ->orWhere('error_message', 'ilike', $like)
                    ->orWhereHas('company', static fn ($cq) => $cq->where('name', 'ilike', $like)->orWhere('slug', 'ilike', $like));
            });
        }

        $aiRequests = $query->paginate(25)->withQueryString();

        $aiRequests->getCollection()->transform(function (AiRequest $aiRequest): AiRequest {
            $aiRequest->setAttribute(
                'request_preview',
                Str::limit(json_encode($this->redactor->redact($aiRequest->request_payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 300)
            );
            $aiRequest->setAttribute(
                'response_preview',
                Str::limit(json_encode($this->redactor->redact($aiRequest->response_payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 300)
            );

            return $aiRequest;
        });

        $requestTypes = AiRequest::withoutGlobalScopes()
            ->select('request_type')
            ->distinct()
            ->orderBy('request_type')
            ->pluck('request_type');

        $companies = Company::query()->orderBy('name')->get(['id', 'name']);

        return view('platform.ai-diagnostics.index', [
            'aiRequests'          => $aiRequests,
            'requestTypes'        => $requestTypes,
            'companies'           => $companies,
            'selectedCompanyId'   => $companyId,
            'selectedStatus'      => $status,
            'selectedRequestType' => $requestType,
            'search'              => $search,
            'testRequestTypes'    => self::TEST_REQUEST_TYPES,
            'isDevMode'           => app()->environment('local', 'development', 'dev'),
        ]);
    }

    public function show(Request $request, string $aiRequestId): View
    {
        $this->authorize('access-platform-console');

        /** @var AiRequest $aiRequest */
        $aiRequest = AiRequest::withoutGlobalScopes()
            ->with('company:id,name,slug')
            ->findOrFail($aiRequestId);

        $redactedRequest  = $this->redactor->redact($aiRequest->request_payload);
        $redactedResponse = $this->redactor->redact($aiRequest->response_payload);

        $attempts = data_get($aiRequest->response_payload, 'attempts', []);

        $duration = null;
        if ($aiRequest->started_at && $aiRequest->finished_at) {
            $duration = $aiRequest->started_at->diffInMilliseconds($aiRequest->finished_at);
        }

        return view('platform.ai-diagnostics.show', [
            'aiRequest'        => $aiRequest,
            'redactedRequest'  => $redactedRequest,
            'redactedResponse' => $redactedResponse,
            'attempts'         => $attempts,
            'duration'         => $duration,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('access-platform-console');

        abort_unless(
            app()->environment('local', 'development', 'dev'),
            403,
            'This action is only available in a local/dev environment.'
        );

        $validated = $request->validate([
            'company_id'        => ['required', 'uuid', 'exists:companies,id'],
            'request_type'      => ['required', Rule::in(self::TEST_REQUEST_TYPES)],
            'input_text'        => ['required', 'string', 'min:5', 'max:5000'],
            'force_invalid_json' => ['sometimes', 'boolean'],
        ]);

        $requestType     = $validated['request_type'];
        $forceInvalidJson = filter_var($validated['force_invalid_json'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $outputMode = in_array($requestType, ['candidate_analysis_json', 'sentiment_analysis'], true)
            ? 'json_schema'
            : 'text';

        $jsonSchema = null;
        if ($requestType === 'candidate_analysis_json') {
            $jsonSchema = [
                'required'   => ['score', 'summary'],
                'properties' => [
                    'score'   => ['type' => 'number'],
                    'summary' => ['type' => 'string'],
                ],
            ];
        } elseif ($requestType === 'sentiment_analysis') {
            $jsonSchema = [
                'required'   => ['score', 'themes', 'risk_level'],
                'properties' => [
                    'score'      => ['type' => 'number'],
                    'themes'     => ['type' => 'array'],
                    'risk_level' => ['type' => 'string'],
                ],
            ];
        }

        $inputText = Str::limit($validated['input_text'], 400);

        $prompt = match ($requestType) {
            'candidate_analysis_json' => <<<PROMPT
                Analyze the text below and return ONLY valid JSON (no markdown, no commentary).
                Required schema:
                {
                  "score": number (0-100),
                  "summary": string
                }
                Text: {$inputText}
                PROMPT,
            'sentiment_analysis' => <<<PROMPT
                Analyze sentiment and return ONLY valid JSON (no markdown, no commentary).
                Required schema:
                {
                  "score": number (-1.0 to 1.0),
                  "themes": array of strings,
                  "risk_level": string
                }
                Text: {$inputText}
                PROMPT,
            'email_draft' => "Write a concise plain-text recruiting email draft based on: {$inputText}",
            'executive_summary' => "Write a concise executive summary in plain text based on: {$inputText}",
            default => 'Analyze the following text and respond accordingly: '.$inputText,
        };

        $payload = [
            'input_text'  => $validated['input_text'],
            'output_mode' => $outputMode,
            'prompt'      => $prompt,
            'dev_flag'    => true,
        ];

        if ($forceInvalidJson) {
            $payload['dev_force_invalid_json'] = true;
        }

        if ($jsonSchema !== null) {
            $payload['json_schema'] = $jsonSchema;
        }

        $aiRequest = $this->aiRequestService->queueRequest(
            companyId: $validated['company_id'],
            requestType: $requestType,
            requestPayload: $payload,
        );

        $this->auditLogger->log(
            actionType: AuditActionType::AI_REQUEST_CREATED,
            entityType: 'ai_request',
            entityId: (string) $aiRequest->id,
            metadata: [
                'request_type'        => $requestType,
                'company_id'          => $validated['company_id'],
                'dev_force_invalid_json' => $forceInvalidJson,
            ],
            companyId: $validated['company_id'],
        );

        return back()->with('status', __('platform.ai_diagnostics.request_queued'));
    }

    public function retry(Request $request, string $aiRequestId): RedirectResponse
    {
        $this->authorize('access-platform-console');

        /** @var AiRequest $aiRequest */
        $aiRequest = AiRequest::withoutGlobalScopes()->findOrFail($aiRequestId);

        $this->aiRequestService->retry($aiRequest);

        $this->auditLogger->log(
            actionType: AuditActionType::AI_REQUEST_RETRIED,
            entityType: 'ai_request',
            entityId: (string) $aiRequest->id,
            metadata: ['request_type' => $aiRequest->request_type],
            companyId: (string) $aiRequest->company_id,
        );

        return back()->with('status', __('platform.ai_diagnostics.retry_queued'));
    }
}
