<?php

return [
    'max_upload_kb' => (int) env('IMAGE_MAX_UPLOAD_KB', 10240),
    'max_pixels' => (int) env('IMAGE_MAX_PIXELS', 40000000),
    'task_ttl_minutes' => (int) env('IMAGE_TASK_TTL_MINUTES', 30),
    'download_ttl_minutes' => (int) env('IMAGE_DOWNLOAD_TTL_MINUTES', 10),
    'rate_limit_per_minute' => (int) env('IMAGE_RATE_LIMIT', 15),
    'ai' => [
        'url' => rtrim((string) env('IMAGE_AI_URL', 'http://127.0.0.1:8090'), '/'),
        'timeout' => (int) env('IMAGE_AI_TIMEOUT', 120),
    ],
];
