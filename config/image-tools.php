<?php

return [
    'max_upload_kb' => (int) env('IMAGE_MAX_UPLOAD_KB', 10240),
    'max_pixels' => (int) env('IMAGE_MAX_PIXELS', 40000000),
    'task_ttl_minutes' => (int) env('IMAGE_TASK_TTL_MINUTES', 30),
    'download_ttl_minutes' => (int) env('IMAGE_DOWNLOAD_TTL_MINUTES', 10),
    'rate_limit_per_minute' => (int) env('IMAGE_RATE_LIMIT', 15),
    'max_concurrent_tasks' => (int) env('TOOL_MAX_CONCURRENT_TASKS', 3),
    'max_output_kb' => (int) env('TOOL_MAX_OUTPUT_KB', 51200),
    'min_free_disk_mb' => (int) env('TOOL_MIN_FREE_DISK_MB', 512),
    'max_request_kb' => (int) env('TOOL_MAX_REQUEST_KB', 102400),
    'stale_after_minutes' => (int) env('TOOL_STALE_AFTER_MINUTES', 10),
    'captcha' => [
        'enabled' => (bool) env('CAPTCHA_ENABLED', false),
        'site_key' => env('CAPTCHA_SITE_KEY'),
        'secret' => env('CAPTCHA_SECRET'),
        'verify_url' => env('CAPTCHA_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),
    ],
    'ai' => [
        'url' => rtrim((string) env('IMAGE_AI_URL', 'http://127.0.0.1:8090'), '/'),
        'timeout' => (int) env('IMAGE_AI_TIMEOUT', 120),
    ],
];
