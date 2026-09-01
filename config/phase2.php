<?php

return [
    'max_pdf_kb' => (int) env('PDF_MAX_UPLOAD_KB', 20480),
    'max_pdf_pages' => (int) env('PDF_MAX_PAGES', 200),
    'ghostscript' => env('GHOSTSCRIPT_PATH', 'gs'),
    'qpdf' => env('QPDF_PATH', 'qpdf'),
    'seo' => ['timeout' => (int) env('SEO_AUDIT_TIMEOUT', 10), 'max_bytes' => (int) env('SEO_AUDIT_MAX_BYTES', 2097152), 'max_links' => (int) env('SEO_AUDIT_MAX_LINKS', 10)],
    'ollama' => ['url' => rtrim((string) env('OLLAMA_URL', ''), '/'), 'model' => env('OLLAMA_MODEL', 'llama3.2:3b'), 'timeout' => (int) env('OLLAMA_TIMEOUT', 45)],
    'dynamic_qr' => ['create_per_hour' => (int) env('DYNAMIC_QR_CREATE_PER_HOUR', 20), 'scan_per_minute' => (int) env('DYNAMIC_QR_SCAN_PER_MINUTE', 120)],
];
