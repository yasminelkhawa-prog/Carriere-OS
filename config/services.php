<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'fallback_models' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('GEMINI_FALLBACK_MODELS', 'gemini-2.0-flash,gemini-flash-latest,gemini-2.5-pro'))
        ))),
        'local_stub_enabled' => env('GEMINI_LOCAL_STUB_ENABLED', false),
        'max_attempts' => env('GEMINI_MAX_ATTEMPTS', 3),
        'timeout_seconds' => env('GEMINI_TIMEOUT_SECONDS', 30),
    ],

    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'redirect_uri' => env('LINKEDIN_REDIRECT_URI'),
        'partner_client_id' => env('LINKEDIN_PARTNER_CLIENT_ID'),
        'partner_client_secret' => env('LINKEDIN_PARTNER_CLIENT_SECRET'),
        'job_posting_endpoint' => env('LINKEDIN_JOB_POSTING_ENDPOINT', 'https://api.linkedin.com/rest/simpleJobPostings'),
        'job_posting_task_status_endpoint' => env('LINKEDIN_JOB_POSTING_TASK_STATUS_ENDPOINT', 'https://api.linkedin.com/rest/simpleJobPostings'),
        'oauth_access_token_url' => env('LINKEDIN_OAUTH_ACCESS_TOKEN_URL', 'https://www.linkedin.com/oauth/v2/accessToken'),
        'job_posting_version' => env('LINKEDIN_JOB_POSTING_VERSION', '202603'),
        'job_posting_timeout_seconds' => max(5, (int) env('LINKEDIN_JOB_POSTING_TIMEOUT_SECONDS', 20)),
        'job_posting_task_initial_delay_seconds' => max(15, (int) env('LINKEDIN_JOB_POSTING_TASK_INITIAL_DELAY_SECONDS', 60)),
        'job_posting_task_retry_delay_seconds' => max(15, (int) env('LINKEDIN_JOB_POSTING_TASK_RETRY_DELAY_SECONDS', 120)),
        'job_posting_task_max_checks' => max(1, (int) env('LINKEDIN_JOB_POSTING_TASK_MAX_CHECKS', 5)),
        'scopes' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('LINKEDIN_SCOPES', 'openid,profile,email,w_member_social'))
        ))),
    ],

];
