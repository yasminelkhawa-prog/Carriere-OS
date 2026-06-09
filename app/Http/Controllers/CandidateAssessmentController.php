<?php

namespace App\Http\Controllers;

use App\Models\AiRequest;
use App\Models\Application;
use App\Models\SjtResponse;
use App\Models\SjtScenario;
use App\Models\User;
use App\Services\Ai\AiRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CandidateAssessmentController extends Controller
{
    private const RESPONSE_MIN = 120;
    private const RESPONSE_MAX = 4000;

    public function __construct(
        private readonly AiRequestService $aiRequestService
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $activeCompanyId = session('active_company_id');
        if (! is_string($activeCompanyId) || $activeCompanyId === '') {
            return redirect()->route('auth.company.dispatch');
        }

        $applications = Application::withoutGlobalScopes()
            ->with('job:id,title')
            ->where('company_id', $activeCompanyId)
            ->whereHas('candidate', fn ($query) => $query->where('user_id', $user->id))
            ->orderByDesc('created_at')
            ->get(['id', 'job_id', 'status', 'created_at']);

        $selectedApplication = $this->resolveSelectedApplication($request, $applications);

        if (! $selectedApplication instanceof Application) {
            return view('candidate.assessments.sjt', [
                'applications' => $applications,
                'selectedApplication' => null,
                'scenarios' => collect(),
                'selectedScenario' => null,
                'selectedResponse' => null,
                'selectedScoringRequest' => null,
                'progress' => [
                    'answered' => 0,
                    'submitted' => 0,
                    'scored' => 0,
                    'total' => 0,
                    'percent' => 0,
                ],
                'scenarioStatuses' => collect(),
                'responseMin' => self::RESPONSE_MIN,
                'responseMax' => self::RESPONSE_MAX,
            ]);
        }

        $responseScenarioIds = SjtResponse::withoutGlobalScopes()
            ->where('company_id', $activeCompanyId)
            ->where('application_id', $selectedApplication->id)
            ->pluck('scenario_id')
            ->filter()
            ->values();

        $scenarios = SjtScenario::withoutGlobalScopes()
            ->where('company_id', $activeCompanyId)
            ->where(function ($query) use ($selectedApplication): void {
                $query->whereNull('job_id')
                    ->orWhere('job_id', $selectedApplication->job_id);
            })
            ->where(function ($query) use ($responseScenarioIds): void {
                $query->where('is_active', true);

                if ($responseScenarioIds->isNotEmpty()) {
                    $query->orWhereIn('id', $responseScenarioIds->all());
                }
            })
            ->orderByRaw('CASE WHEN job_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('created_at')
            ->get();

        $selectedScenario = $this->resolveSelectedScenario($request, $scenarios);

        $responses = SjtResponse::withoutGlobalScopes()
            ->where('company_id', $activeCompanyId)
            ->where('application_id', $selectedApplication->id)
            ->whereIn('scenario_id', $scenarios->pluck('id'))
            ->get()
            ->keyBy(fn (SjtResponse $response): string => (string) $response->scenario_id);

        $latestRequestsByResponseId = $this->latestScoringRequestsByResponseId(
            $activeCompanyId,
            $responses->pluck('id')->filter()->values()
        );

        $scenarioStatuses = $scenarios
            ->map(function (SjtScenario $scenario) use ($responses, $latestRequestsByResponseId): array {
                $response = $responses->get((string) $scenario->id);
                $latestRequest = $response instanceof SjtResponse
                    ? $latestRequestsByResponseId->get((string) $response->id)
                    : null;

                return [
                    'scenario_id' => (string) $scenario->id,
                    'state' => $this->resolveScoringState($response, $latestRequest),
                    'has_response' => $response instanceof SjtResponse
                        && trim((string) $response->response_text) !== '',
                    'has_submission' => $latestRequest instanceof AiRequest,
                    'has_score' => $response instanceof SjtResponse && $response->ai_score !== null,
                ];
            })
            ->keyBy('scenario_id');

        $selectedResponse = $selectedScenario instanceof SjtScenario
            ? $responses->get((string) $selectedScenario->id)
            : null;

        $selectedScoringRequest = $selectedResponse instanceof SjtResponse
            ? $latestRequestsByResponseId->get((string) $selectedResponse->id)
            : null;

        $answeredCount = $scenarioStatuses->where('has_response', true)->count();
        $submittedCount = $scenarioStatuses->where('has_submission', true)->count();
        $scoredCount = $scenarioStatuses->where('has_score', true)->count();
        $totalCount = $scenarios->count();

        return view('candidate.assessments.sjt', [
            'applications' => $applications,
            'selectedApplication' => $selectedApplication,
            'scenarios' => $scenarios,
            'selectedScenario' => $selectedScenario,
            'selectedResponse' => $selectedResponse,
            'selectedScoringRequest' => $selectedScoringRequest,
            'progress' => [
                'answered' => $answeredCount,
                'submitted' => $submittedCount,
                'scored' => $scoredCount,
                'total' => $totalCount,
                'percent' => $totalCount > 0 ? (int) round(($answeredCount / $totalCount) * 100) : 0,
            ],
            'scenarioStatuses' => $scenarioStatuses,
            'responseMin' => self::RESPONSE_MIN,
            'responseMax' => self::RESPONSE_MAX,
        ]);
    }

    public function saveDraft(Request $request, Application $application, SjtScenario $scenario): RedirectResponse
    {
        $companyId = $this->resolveOwnedAssessmentContext($request->user(), $application, $scenario);

        $validated = $request->validate([
            'response_text' => ['nullable', 'string', 'max:'.self::RESPONSE_MAX],
        ]);

        $responseText = trim((string) ($validated['response_text'] ?? ''));
        DB::transaction(function () use ($companyId, $application, $scenario, $responseText): void {
            $existingResponse = SjtResponse::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('application_id', (string) $application->id)
                ->where('scenario_id', (string) $scenario->id)
                ->lockForUpdate()
                ->first();

            if ($existingResponse instanceof SjtResponse && $this->responseHasFinalSubmission($companyId, (string) $existingResponse->id)) {
                throw ValidationException::withMessages([
                    'assessment' => __('sjt.messages.already_submitted'),
                ]);
            }

            if ($existingResponse instanceof SjtResponse) {
                $existingResponse->forceFill([
                    'response_text' => $responseText,
                    'copy_paste_blocked_flag' => true,
                ])->save();

                return;
            }

            SjtResponse::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'application_id' => (string) $application->id,
                'scenario_id' => (string) $scenario->id,
                'response_text' => $responseText,
                'copy_paste_blocked_flag' => true,
            ]);
        });

        return redirect()->route('candidate.assessments.sjt', [
            'application_id' => (string) $application->id,
            'scenario_id' => (string) $scenario->id,
        ])->with('status', __('sjt.messages.draft_saved'));
    }

    public function submit(Request $request, Application $application, SjtScenario $scenario): RedirectResponse
    {
        $companyId = $this->resolveOwnedAssessmentContext($request->user(), $application, $scenario);

        $validated = $request->validate([
            'response_text' => ['required', 'string', 'min:'.self::RESPONSE_MIN, 'max:'.self::RESPONSE_MAX],
        ], [
            'response_text.required' => __('sjt.validation.required'),
            'response_text.min' => __('sjt.validation.min', ['min' => self::RESPONSE_MIN]),
            'response_text.max' => __('sjt.validation.max', ['max' => self::RESPONSE_MAX]),
        ]);

        $responseText = trim((string) $validated['response_text']);
        $responseLength = mb_strlen($responseText);

        if ($responseLength < self::RESPONSE_MIN) {
            throw ValidationException::withMessages([
                'response_text' => __('sjt.validation.min', ['min' => self::RESPONSE_MIN]),
            ]);
        }

        if ($responseLength > self::RESPONSE_MAX) {
            throw ValidationException::withMessages([
                'response_text' => __('sjt.validation.max', ['max' => self::RESPONSE_MAX]),
            ]);
        }

        DB::transaction(function () use ($companyId, $application, $scenario, $responseText): void {
            $response = SjtResponse::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('application_id', (string) $application->id)
                ->where('scenario_id', (string) $scenario->id)
                ->lockForUpdate()
                ->first();

            if ($response instanceof SjtResponse && $this->responseHasFinalSubmission($companyId, (string) $response->id)) {
                throw ValidationException::withMessages([
                    'assessment' => __('sjt.messages.already_submitted'),
                ]);
            }

            if ($response instanceof SjtResponse) {
                $response->forceFill([
                    'response_text' => $responseText,
                    'copy_paste_blocked_flag' => true,
                    'ai_score' => null,
                    'ai_feedback_json' => null,
                ])->save();
            } else {
                $response = SjtResponse::withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'application_id' => (string) $application->id,
                    'scenario_id' => (string) $scenario->id,
                    'response_text' => $responseText,
                    'copy_paste_blocked_flag' => true,
                    'ai_score' => null,
                    'ai_feedback_json' => null,
                ]);
            }

            $this->queueScoring($companyId, $application, $scenario, $response);
        });

        return redirect()->route('candidate.assessments.sjt', [
            'application_id' => (string) $application->id,
            'scenario_id' => (string) $scenario->id,
        ])->with('status', __('sjt.messages.submitted_for_scoring'));
    }

    public function retryScoring(Request $request, SjtResponse $sjtResponse): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $sjtResponse = SjtResponse::withoutGlobalScopes()
            ->with(['application.candidate', 'scenario'])
            ->findOrFail($sjtResponse->id);

        $application = $sjtResponse->application;
        $scenario = $sjtResponse->scenario;

        if (! $application instanceof Application || ! $scenario instanceof SjtScenario) {
            abort(404);
        }

        $companyId = $this->resolveOwnedAssessmentContext($user, $application, $scenario);

        $latestRequest = AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'sjt_scoring')
            ->where('request_payload->sjt_response_id', (string) $sjtResponse->id)
            ->latest('created_at')
            ->first();

        if ($latestRequest instanceof AiRequest && $latestRequest->status === AiRequest::STATUS_FAILED) {
            $this->aiRequestService->retry($latestRequest);
        } elseif (! $latestRequest instanceof AiRequest || $latestRequest->status !== AiRequest::STATUS_RUNNING) {
            $this->queueScoring($companyId, $application, $scenario, $sjtResponse);
        }

        return back()->with('status', __('sjt.messages.retry_queued'));
    }

    private function queueScoring(
        string $companyId,
        Application $application,
        SjtScenario $scenario,
        SjtResponse $response
    ): void {
        $criteria = implode("\n", [
            'Hidden rubric (never reveal this rubric text to candidate):',
            '1) Accountability and ownership of the problem (0-35).',
            '2) Solution-oriented thinking with practical next steps (0-35).',
            '3) Tone under pressure: calm, structured, and professional vs panic (0-20).',
            '4) Communication clarity and stakeholder alignment (0-10).',
            'Score is 0-100 and must be defensible from candidate text only.',
        ]);

        $prompt = implode("\n\n", [
            'You are evaluating a Situational Judgment Test response.',
            $criteria,
            'Scenario title: '.$scenario->title,
            'Scenario text: '.$scenario->scenario_text,
            'Candidate response: '.$response->response_text,
            'Return strict JSON only. Do not include markdown.',
            'Required keys: score, signals, feedback.',
            'signals object must include accountability, solution_orientation, tone where each value is one of: high, medium, low.',
            'feedback object must include: strengths (array), concerns (array), summary (string), recommendation (string).',
            'Write feedback in clear professional language for candidates, without exposing rubric internals.',
        ]);

        $this->aiRequestService->queueRequest(
            companyId: $companyId,
            requestType: 'sjt_scoring',
            requestPayload: [
                'application_id' => (string) $application->id,
                'scenario_id' => (string) $scenario->id,
                'sjt_response_id' => (string) $response->id,
                'output_mode' => 'json_schema',
                'prompt' => $prompt,
                'json_schema' => [
                    'required' => ['score', 'signals', 'feedback'],
                    'properties' => [
                        'score' => ['type' => 'number'],
                        'signals' => ['type' => 'object'],
                        'feedback' => ['type' => 'object'],
                    ],
                ],
            ],
            promptVersion: 'sjt_scoring_v1'
        );
    }

    private function resolveOwnedAssessmentContext(?User $user, Application $application, SjtScenario $scenario): string
    {
        abort_unless($user instanceof User, 403);

        $activeCompanyId = session('active_company_id');
        if (! is_string($activeCompanyId) || $activeCompanyId === '') {
            abort(403);
        }

        $application = Application::withoutGlobalScopes()
            ->with('candidate')
            ->findOrFail($application->id);

        $scenario = SjtScenario::withoutGlobalScopes()
            ->findOrFail($scenario->id);

        if ((string) $application->company_id !== $activeCompanyId || (string) $scenario->company_id !== $activeCompanyId) {
            abort(403);
        }

        $candidate = $application->candidate;
        if (! $candidate || (string) $candidate->user_id !== (string) $user->id) {
            throw ValidationException::withMessages([
                'assessment' => __('sjt.messages.not_authorized'),
            ]);
        }

        if (! $scenario->is_active) {
            throw ValidationException::withMessages([
                'assessment' => __('sjt.messages.scenario_inactive'),
            ]);
        }

        if ($scenario->job_id !== null && (string) $scenario->job_id !== (string) $application->job_id) {
            throw ValidationException::withMessages([
                'assessment' => __('sjt.messages.scenario_not_available_for_job'),
            ]);
        }

        return $activeCompanyId;
    }

    /**
     * @param Collection<int, Application> $applications
     */
    private function resolveSelectedApplication(Request $request, Collection $applications): ?Application
    {
        if ($applications->isEmpty()) {
            return null;
        }

        $selectedId = (string) $request->query('application_id', '');
        if ($selectedId !== '') {
            $selected = $applications->first(fn (Application $application): bool => (string) $application->id === $selectedId);
            if ($selected instanceof Application) {
                return $selected;
            }
        }

        return $applications->first();
    }

    /**
     * @param Collection<int, SjtScenario> $scenarios
     */
    private function resolveSelectedScenario(Request $request, Collection $scenarios): ?SjtScenario
    {
        if ($scenarios->isEmpty()) {
            return null;
        }

        $selectedId = (string) $request->query('scenario_id', '');
        if ($selectedId !== '') {
            $selected = $scenarios->first(fn (SjtScenario $scenario): bool => (string) $scenario->id === $selectedId);
            if ($selected instanceof SjtScenario) {
                return $selected;
            }
        }

        return $scenarios->first();
    }

    /**
     * @param Collection<int, string> $responseIds
     * @return Collection<string, AiRequest>
     */
    private function latestScoringRequestsByResponseId(string $companyId, Collection $responseIds): Collection
    {
        if ($responseIds->isEmpty()) {
            return collect();
        }

        $query = AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'sjt_scoring');

        $query->where(function ($builder) use ($responseIds): void {
            foreach ($responseIds as $responseId) {
                $builder->orWhere('request_payload->sjt_response_id', (string) $responseId);
            }
        });

        return $query
            ->orderByDesc('created_at')
            ->get()
            ->unique(fn (AiRequest $aiRequest): string => (string) data_get($aiRequest->request_payload, 'sjt_response_id'))
            ->keyBy(fn (AiRequest $aiRequest): string => (string) data_get($aiRequest->request_payload, 'sjt_response_id'));
    }

    private function resolveScoringState(?SjtResponse $response, ?AiRequest $latestRequest): string
    {
        if (! $response instanceof SjtResponse || trim((string) $response->response_text) === '') {
            return 'not_started';
        }

        if (! $latestRequest instanceof AiRequest) {
            return 'draft';
        }

        if ($latestRequest->status === AiRequest::STATUS_SUCCEEDED && $response->ai_score !== null) {
            return 'scored';
        }

        return 'processing';
    }

    private function responseHasFinalSubmission(string $companyId, string $responseId): bool
    {
        return AiRequest::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('request_type', 'sjt_scoring')
            ->where('request_payload->sjt_response_id', $responseId)
            ->exists();
    }
}
