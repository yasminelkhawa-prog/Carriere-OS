<?php

return [
    'tenant' => [
        'scope_column' => 'company_id',
    ],

    'ai_output_modes' => [
        'candidate_analysis_report' => 'json_schema',
        'email_drafts' => 'text',
        'multiposting_rewrites' => 'text',
        'executive_summaries' => 'text',
        'chatbots' => 'text_with_policy_constraints',
    ],

    'decision_accountability' => [
        'auto_send_allowed' => [
            'application_acknowledgment',
            'interview_confirmation',
            'onboarding_welcome_after_contract_signing',
        ],
        'reject_auto_send' => false,
        'offer_auto_send' => false,
        'hire_auto_send' => false,
    ],

    'audit_firewall' => [
        'sensitive_inference_allowed_in_audit_pipeline' => true,
        'sensitive_inference_allowed_in_decision_pipeline' => false,
        'storage_level' => 'aggregate_only',
        'aggregate_dimensions' => ['job_id', 'stage', 'time_bucket'],
        'allow_per_candidate_sensitive_labels' => false,
    ],
];
