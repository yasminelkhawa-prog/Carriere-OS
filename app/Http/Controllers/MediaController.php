<?php

namespace App\Http\Controllers;

use App\Models\CandidateDocument;
use App\Models\Application;
use App\Models\Candidate;
use App\Models\Profile;
use App\Models\Contract;
use App\Models\OnboardingDocument;
use App\Models\StrategyLabBrief;
use App\Models\StrategyLabSubmission;
use App\Models\VideoResponse;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Support\Audit\SensitiveEventRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct(private readonly SensitiveEventRecorder $sensitiveEvents)
    {
    }

    public function avatar(Request $request, Profile $profile): Response
    {
        abort_unless($request->hasValidSignature(), 403);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        if (! $actor->isSuperadmin()) {
            $activeCompanyId = session('active_company_id');

            abort_unless(
                $activeCompanyId !== null &&
                $profile->user->memberships()
                    ->where('company_id', $activeCompanyId)
                    ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                    ->exists(),
                403
            );
        }

        if (! $profile->avatar_url || ! Storage::disk('local')->exists($profile->avatar_url)) {
            abort(404);
        }

        return response(Storage::disk('local')->get($profile->avatar_url), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($profile->avatar_url) ?: 'application/octet-stream',
        ]);
    }

    public function candidateDocument(Request $request, CandidateDocument $candidateDocument): Response|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            $this->sensitiveEvents->record(
                actionType: 'sensitive_document.access_denied',
                entityType: 'document',
                entityId: (string) $candidateDocument->id,
                metadata: ['reason' => 'unauthenticated'],
                actor: null
            );

            abort(403);
        }

        if (! $request->hasValidSignature()) {
            $this->sensitiveEvents->record(
                actionType: 'sensitive_document.access_denied',
                entityType: 'document',
                entityId: (string) $candidateDocument->id,
                metadata: ['reason' => 'invalid_signature'],
                actor: $actor
            );

            return redirect()
                ->route('candidates.index')
                ->with('error', __('candidates.errors.document_forbidden_message'));
        }

        if (! $actor->isSuperadmin()) {
            $allowed = $actor->memberships()
                    ->where('company_id', $candidateDocument->company_id)
                    ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
                    ->exists();

            if (! $allowed) {
                $this->sensitiveEvents->record(
                    actionType: 'sensitive_document.access_denied',
                    entityType: 'document',
                    entityId: (string) $candidateDocument->id,
                    metadata: ['reason' => 'membership_forbidden'],
                    actor: $actor
                );

                return redirect()
                    ->route('candidates.index')
                    ->with('error', __('candidates.errors.document_forbidden_message'));
            }
        }

        if (! Storage::disk('local')->exists($candidateDocument->file_url)) {
            abort(404);
        }

        $this->sensitiveEvents->sensitiveDocumentDownloaded(
            documentId: (string) $candidateDocument->id,
            metadata: ['document_type' => $candidateDocument->document_type],
            actor: $actor
        );

        return response(Storage::disk('local')->get($candidateDocument->file_url), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($candidateDocument->file_url) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($candidateDocument->original_filename).'"',
        ]);
    }

    public function contract(Request $request, Contract $contract): Response|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        if (! $request->hasValidSignature()) {
            return redirect()
                ->route('home')
                ->with('error', __('strategy_lab.errors.download_forbidden'));
        }

        $contract->loadMissing(['application.candidate']);
        $companyId = (string) $contract->company_id;

        if (! $this->canAccessStrategyResource($actor, $companyId, $contract->application?->candidate_id)) {
            return redirect()
                ->route('home')
                ->with('error', __('strategy_lab.errors.download_forbidden'));
        }

        if (! Storage::disk('local')->exists($contract->contract_file_url)) {
            abort(404);
        }

        $this->sensitiveEvents->record(
            actionType: 'contract.downloaded',
            entityType: 'contract',
            entityId: (string) $contract->id,
            metadata: ['application_id' => (string) $contract->application_id],
            actor: $actor
        );

        $fileName = basename((string) $contract->contract_file_url) ?: 'contract.pdf';

        return response(Storage::disk('local')->get($contract->contract_file_url), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($contract->contract_file_url) ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($fileName).'"',
        ]);
    }

    public function onboardingDocument(Request $request, OnboardingDocument $onboardingDocument): Response|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        if (! $request->hasValidSignature()) {
            return redirect()
                ->route('home')
                ->with('error', __('strategy_lab.errors.download_forbidden'));
        }

        $onboardingDocument->loadMissing(['application.candidate']);
        $companyId = (string) $onboardingDocument->company_id;

        if (! $this->canAccessStrategyResource($actor, $companyId, $onboardingDocument->application?->candidate_id)) {
            return redirect()
                ->route('home')
                ->with('error', __('strategy_lab.errors.download_forbidden'));
        }

        if (! Storage::disk('local')->exists($onboardingDocument->file_url)) {
            abort(404);
        }

        $this->sensitiveEvents->sensitiveDocumentDownloaded(
            documentId: (string) $onboardingDocument->id,
            metadata: [
                'document_type' => (string) $onboardingDocument->doc_type,
                'application_id' => (string) $onboardingDocument->application_id,
            ],
            actor: $actor
        );

        $fileName = basename((string) $onboardingDocument->file_url) ?: 'onboarding-document';

        return response(Storage::disk('local')->get($onboardingDocument->file_url), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($onboardingDocument->file_url) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($fileName).'"',
        ]);
    }

    public function strategyLabBrief(Request $request, StrategyLabBrief $strategyLabBrief): Response|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            $this->sensitiveEvents->record(
                actionType: 'strategy_lab_brief.access_denied',
                entityType: 'strategy_lab_brief',
                entityId: (string) $strategyLabBrief->id,
                metadata: ['reason' => 'unauthenticated'],
                actor: null
            );
            abort(403);
        }

        if (! $request->hasValidSignature()) {
            return $this->denyStrategyDownload($actor, (string) $strategyLabBrief->id, 'invalid_signature');
        }

        $strategyLabBrief->loadMissing(['application.candidate', 'application.currentStage']);

        if (! $this->canAccessStrategyResource($actor, $strategyLabBrief->company_id, $strategyLabBrief->application?->candidate_id)) {
            return $this->denyStrategyDownload($actor, (string) $strategyLabBrief->id, 'membership_forbidden');
        }

        if (! $this->candidateCanAccessStrategyLabApplication($actor, $strategyLabBrief->application)) {
            return $this->denyStrategyDownload($actor, (string) $strategyLabBrief->id, 'strategy_lab_not_eligible');
        }

        if (! $strategyLabBrief->brief_pdf_url || ! Storage::disk('local')->exists($strategyLabBrief->brief_pdf_url)) {
            abort(404);
        }

        $this->sensitiveEvents->record(
            actionType: 'strategy_lab_brief.downloaded',
            entityType: 'strategy_lab_brief',
            entityId: (string) $strategyLabBrief->id,
            metadata: ['application_id' => (string) $strategyLabBrief->application_id],
            actor: $actor
        );

        return response(Storage::disk('local')->get($strategyLabBrief->brief_pdf_url), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($strategyLabBrief->brief_pdf_url) ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="strategy-brief.pdf"',
        ]);
    }

    public function strategyLabSubmission(Request $request, StrategyLabSubmission $strategyLabSubmission): Response|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            $this->sensitiveEvents->record(
                actionType: 'strategy_lab_submission.access_denied',
                entityType: 'strategy_lab_submission',
                entityId: (string) $strategyLabSubmission->id,
                metadata: ['reason' => 'unauthenticated'],
                actor: null
            );
            abort(403);
        }

        if (! $request->hasValidSignature()) {
            return $this->denyStrategyDownload($actor, (string) $strategyLabSubmission->id, 'invalid_signature');
        }

        $strategyLabSubmission->loadMissing(['application.candidate', 'application.currentStage']);

        if (! $this->canAccessStrategyResource($actor, $strategyLabSubmission->company_id, $strategyLabSubmission->application?->candidate_id)) {
            return $this->denyStrategyDownload($actor, (string) $strategyLabSubmission->id, 'membership_forbidden');
        }

        if (! $this->candidateCanAccessStrategyLabApplication($actor, $strategyLabSubmission->application)) {
            return $this->denyStrategyDownload($actor, (string) $strategyLabSubmission->id, 'strategy_lab_not_eligible');
        }

        if (! Storage::disk('local')->exists($strategyLabSubmission->file_url)) {
            abort(404);
        }

        $this->sensitiveEvents->record(
            actionType: 'strategy_lab_submission.downloaded',
            entityType: 'strategy_lab_submission',
            entityId: (string) $strategyLabSubmission->id,
            metadata: ['application_id' => (string) $strategyLabSubmission->application_id],
            actor: $actor
        );

        return response(Storage::disk('local')->get($strategyLabSubmission->file_url), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($strategyLabSubmission->file_url) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($strategyLabSubmission->original_filename).'"',
        ]);
    }

    private function canAccessStrategyResource(User $actor, string $companyId, ?string $candidateId): bool
    {
        if ($actor->isSuperadmin()) {
            return true;
        }

        $membership = $actor->memberships()
            ->where('company_id', $companyId)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->first();

        if (! $membership instanceof CompanyMembership) {
            return false;
        }

        if ($membership->company_role !== CompanyMembership::ROLE_CANDIDATE) {
            return true;
        }

        if (! is_string($candidateId) || $candidateId === '') {
            return false;
        }

        $candidate = Candidate::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('user_id', $actor->id)
            ->first();

        return $candidate instanceof Candidate
            && (string) $candidate->id === $candidateId;
    }

    private function candidateCanAccessStrategyLabApplication(User $actor, ?Application $application): bool
    {
        if ($actor->isSuperadmin()) {
            return true;
        }

        if (! $application instanceof Application) {
            return false;
        }

        $membership = $actor->memberships()
            ->where('company_id', (string) $application->company_id)
            ->where('membership_status', CompanyMembership::STATUS_ACTIVE)
            ->first();

        if (! $membership instanceof CompanyMembership) {
            return false;
        }

        if ($membership->company_role !== CompanyMembership::ROLE_CANDIDATE) {
            return true;
        }

        return StrategyLabController::canAccessStrategyLab($application);
    }

    private function denyStrategyDownload(User $actor, string $entityId, string $reason): RedirectResponse
    {
        $this->sensitiveEvents->record(
            actionType: 'strategy_lab.access_denied',
            entityType: 'strategy_lab',
            entityId: $entityId,
            metadata: ['reason' => $reason],
            actor: $actor
        );

        return redirect()
            ->route('home')
            ->with('error', __('strategy_lab.errors.download_forbidden'));
    }

    public function videoResponse(Request $request, VideoResponse $videoInterviewResponse): Response|RedirectResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        if (! $request->hasValidSignature()) {
            return redirect()
                ->route('home')
                ->with('error', __('strategy_lab.errors.download_forbidden'));
        }

        $videoInterviewResponse->loadMissing(['application.candidate']);
        $companyId = (string) $videoInterviewResponse->company_id;

        if (! $this->canAccessStrategyResource($actor, $companyId, $videoInterviewResponse->application?->candidate_id)) {
            return redirect()
                ->route('home')
                ->with('error', __('strategy_lab.errors.download_forbidden'));
        }

        if (! Storage::disk('local')->exists($videoInterviewResponse->video_file_url)) {
            abort(404);
        }

        $this->sensitiveEvents->record(
            actionType: 'video_response.downloaded',
            entityType: 'video_response',
            entityId: (string) $videoInterviewResponse->id,
            metadata: ['application_id' => (string) $videoInterviewResponse->application_id],
            actor: $actor
        );

        return response(Storage::disk('local')->get($videoInterviewResponse->video_file_url), 200, [
            'Content-Type' => Storage::disk('local')->mimeType($videoInterviewResponse->video_file_url) ?: 'video/mp4',
            'Content-Disposition' => 'inline; filename="video-response.'.pathinfo($videoInterviewResponse->video_file_url, PATHINFO_EXTENSION).'"',
        ]);
    }
}
