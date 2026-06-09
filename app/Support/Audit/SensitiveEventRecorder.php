<?php

namespace App\Support\Audit;

use App\Models\User;

class SensitiveEventRecorder
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function record(string $actionType, string $entityType, ?string $entityId, array $metadata = [], ?User $actor = null): void
    {
        $this->auditLogger->log(
            actionType: $actionType,
            entityType: $entityType,
            entityId: $entityId,
            metadata: $metadata,
            actor: $actor
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function roleChanged(string $userId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::ROLE_CHANGED, 'user', $userId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function loginSucceeded(string $userId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::LOGIN_SUCCEEDED, 'user', $userId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function companySelected(string $companyId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::COMPANY_SELECTED, 'company', $companyId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function companySwitched(string $companyId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::COMPANY_SWITCHED, 'company', $companyId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function companyApproved(string $companyId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::COMPANY_APPROVED, 'company', $companyId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function companyRejected(string $companyId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::COMPANY_REJECTED, 'company', $companyId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function jobPublished(string $jobId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::JOB_PUBLISHED, 'job', $jobId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function jobUnpublished(string $jobId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::JOB_UNPUBLISHED, 'job', $jobId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function stageChanged(string $applicationId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::STAGE_CHANGED, 'application', $applicationId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function emailSent(string $messageId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::EMAIL_SENT, 'email', $messageId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function contractSigned(string $contractId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::CONTRACT_SIGNED, 'contract', $contractId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function sensitiveDocumentDownloaded(string $documentId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::SENSITIVE_DOCUMENT_DOWNLOADED, 'document', $documentId, $metadata, $actor);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function jobPostingStatusChanged(string $jobPostingId, array $metadata = [], ?User $actor = null): void
    {
        $this->record(AuditActionType::JOB_POSTING_STATUS_CHANGED, 'job_posting', $jobPostingId, $metadata, $actor);
    }
}
