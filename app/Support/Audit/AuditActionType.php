<?php

namespace App\Support\Audit;

class AuditActionType
{
    public const LOGIN_SUCCEEDED = 'auth.login_succeeded';
    public const COMPANY_SELECTED = 'company.selected';
    public const COMPANY_SWITCHED = 'company.switched';
    public const ROLE_CHANGED = 'role.changed';
    public const COMPANY_APPROVED = 'company.approved';
    public const COMPANY_REJECTED = 'company.rejected';
    public const JOB_PUBLISHED = 'job.published';
    public const JOB_UNPUBLISHED = 'job.unpublished';
    public const STAGE_CHANGED = 'stage.changed';
    public const EMAIL_SENT = 'email.sent';
    public const CONTRACT_SIGNED = 'contract.signed';
    public const SENSITIVE_DOCUMENT_DOWNLOADED = 'sensitive_document.downloaded';
    public const JOB_POSTING_STATUS_CHANGED = 'job_posting.status_changed';
    public const AI_REQUEST_CREATED = 'ai_request.created';
    public const AI_REQUEST_RETRIED = 'ai_request.retried';
}
