<?php

return [
    'portal' => [
        'title' => 'Async Video Interview',
        'subtitle' => 'Complete your question-by-question Stories assessment.',
        'labels' => [
            'progress' => ':current / :total completed',
            'processing' => 'Final report processing',
            'processing_failed' => 'Processing failed',
            'completed' => 'Completed',
            'not_started' => 'Not started',
        ],
        'actions' => [
            'open' => 'Open Stories',
            'continue' => 'Continue Stories',
            'retry_failed' => 'Open and retry later',
        ],
    ],
    'config' => [
        'title' => 'Async Video Interview Configs',
        'subtitle' => 'Configure Stories timers, retries, and ordered question sets by job.',
        'fields' => [
            'job' => 'Job',
            'name' => 'Config Name',
            'read_time_seconds' => 'Read Time (seconds)',
            'answer_time_seconds' => 'Answer Time (seconds)',
            'retries_allowed' => 'Retries Allowed',
            'questions' => 'Questions',
            'question_placeholder' => 'Question :number',
        ],
        'actions' => [
            'create' => 'Create Config',
            'update' => 'Update Config',
        ],
        'messages' => [
            'created' => 'Video interview config created.',
            'updated' => 'Video interview config updated.',
        ],
        'validation' => [
            'questions_required' => 'At least one question is required.',
            'question_text_required' => 'Question text is required.',
        ],
        'empty_title' => 'No video configs',
        'empty_message' => 'Create a config for a job to enable async video stories.',
    ],
    'stories' => [
        'title' => 'Async Video Interview',
        'subtitle' => 'Answer each question within the configured timer.',
        'progress' => ':current / :total completed',
        'labels' => [
            'read_timer' => 'Read timer',
            'answer_timer' => 'Answer timer',
            'retries_left' => 'Retries left',
            'attempt' => 'Attempt :attempt',
            'duration_seconds' => 'Recorded duration (seconds)',
            'pauses_count' => 'Pause count (optional)',
            'speech_rate' => 'Speech rate estimate (optional)',
            'filler_ratio' => 'Filler ratio estimate (optional)',
            'transcript' => 'Transcript text (optional)',
            'recording_file' => 'Video file',
            'guide_blocked' => 'Candidate Guide bot is disabled on video assessment pages.',
            'read_complete' => 'Read time complete. You can submit now.',
            'processing' => 'Final report is processing.',
            'processing_failed' => 'Processing failed. Recruiter can retry from candidate workspace.',
        ],
        'actions' => [
            'submit_and_next' => 'Submit & Next',
            'submit_and_retry' => 'Submit & Retry',
            'open_candidate_portal' => 'Back to Candidate Dashboard',
            'start_reading' => 'Start read timer',
        ],
        'messages' => [
            'no_config_title' => 'No video interview configured',
            'no_config_message' => 'Recruiter has not configured this assessment for your job yet.',
            'completed_title' => 'Video interview completed',
            'completed_message' => 'All questions were submitted. Final AI report is being prepared.',
            'saved' => 'Video response saved.',
            'retry_limit' => 'Retries exceeded for this question.',
            'read_timer_required' => 'You must complete the read timer before submitting.',
            'answer_timer_exceeded' => 'Recorded duration exceeds the allowed answer time.',
        ],
    ],
];
