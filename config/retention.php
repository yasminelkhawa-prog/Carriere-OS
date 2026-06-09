<?php

return [
    'defaults' => [
        'video_retention_days' => (int) env('RETENTION_VIDEO_DAYS', 365),
        'ai_artifact_retention_days' => (int) env('RETENTION_AI_ARTIFACT_DAYS', 180),
    ],

    'min_days' => 7,
    'max_days' => 3650,
    'chunk_size' => (int) env('RETENTION_PRUNE_CHUNK_SIZE', 200),
    'queue' => env('RETENTION_QUEUE', 'default'),
];
