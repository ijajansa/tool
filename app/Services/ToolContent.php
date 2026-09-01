<?php

namespace App\Services;

class ToolContent
{
    public function for(string $slug, array $tool): array
    {
        $privacy = ($tool['phase'] ?? 1) === 1 || in_array($slug, ['pdf-toolkit', 'bulk-watermark-tool'], true)
            ? 'Uploaded files use random private names and are deleted after '.config('image-tools.task_ttl_minutes').' minutes.'
            : 'Generated server files are temporary; browser drafts remain only in your browser until you clear them.';
        $special = match ($slug) {
            'seo-audit' => ['Submit a public HTTP or HTTPS URL.', 'Wait for the bounded security-checked crawl.', 'Download the transparent weighted report.'],
            'qr-code-generator' => ['Choose static or dynamic mode.', 'Enter the final content and QR settings.', 'Download the code; save the management token for dynamic analytics.'],
            'invoice-generator', 'gst-invoice-generator' => ['Enter supplier and customer information.', 'Add and review editable line items and tax rates.', 'Export the calculated document as PDF or save its JSON draft.'],
            'social-poster-generator' => ['Choose a canvas preset.', 'Arrange text, gradients, and uploaded visual layers.', 'Export the exact canvas as PNG, JPG, or WebP.'],
            'pdf-toolkit' => ['Choose PDFs in the intended order.', 'Select compression, merge, or validated page ranges.', 'Download the private PDF or ZIP before it expires.'],
            default => ['Choose or enter the source information.', 'Adjust the available settings for your intended result.', 'Process, review, and download the temporary output.'],
        };

        return [
            'steps' => $special,
            'benefits' => ['No customer account is required.', 'Processing uses local or self-hosted components instead of paid APIs.', $privacy],
            'privacy' => $privacy,
            'faq' => [
                ['question' => 'Is '.$tool['name'].' free?', 'answer' => 'Yes. The tool is available without registration. Resource and abuse limits still apply so the public service remains reliable.'],
                ['question' => 'How long is my data retained?', 'answer' => $privacy],
                ['question' => 'What should I verify before using the result?', 'answer' => 'Review generated files and calculations for your situation. You remain responsible for content, ownership, legal, tax, and regulatory requirements.'],
            ],
        ];
    }
}
