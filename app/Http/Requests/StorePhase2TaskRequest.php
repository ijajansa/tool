<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhase2TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->route('tool'), ['pdf-toolkit', 'invoice-generator', 'barcode-label-generator', 'gst-invoice-generator', 'visiting-card-letterhead', 'product-description-generator', 'resume-builder', 'seo-audit', 'whatsapp-link-generator', 'bulk-watermark-tool'], true);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('payload'))) {
            $this->merge(['payload' => json_decode($this->input('payload'), true)]);
        }
    }

    public function rules(): array
    {
        $tool = $this->route('tool');
        $base = ['payload' => ['required', 'array']];

        return array_merge($base, match ($tool) {
            'pdf-toolkit' => ['documents' => ['required', 'array', 'min:1', 'max:10'], 'documents.*' => ['file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.config('phase2.max_pdf_kb')], 'payload.operation' => ['required', Rule::in(['compress', 'merge', 'split'])], 'payload.level' => ['nullable', Rule::in(['screen', 'ebook', 'printer'])], 'payload.ranges' => ['nullable', 'required_if:payload.operation,split', 'string', 'max:500', 'regex:/^\s*\d+(?:\s*-\s*\d+)?(?:\s*,\s*\d+(?:\s*-\s*\d+)?)*\s*$/']],
            'invoice-generator' => $this->invoiceRules(),
            'gst-invoice-generator' => array_merge($this->invoiceRules(), ['payload.supplier_gstin' => ['required', 'regex:/^[0-9]{2}[A-Z0-9]{13}$/'], 'payload.customer_gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z0-9]{13}$/'], 'payload.transaction' => ['required', Rule::in(['intrastate', 'interstate'])], 'payload.items.*.hsn' => ['required', 'string', 'max:12'], 'payload.items.*.gst_rate' => ['required', 'numeric', 'between:0,100']]),
            'barcode-label-generator' => ['payload.value' => ['required', 'string', 'max:120'], 'payload.format' => ['required', Rule::in(['C128', 'C39', 'EAN13', 'UPCA'])], 'payload.output' => ['required', Rule::in(['svg', 'png', 'pdf'])], 'payload.columns' => ['required', 'integer', 'between:1,5'], 'payload.rows' => ['required', 'integer', 'between:1,12']],
            'product-description-generator' => ['payload.product_name' => ['required', 'string', 'max:150'], 'payload.category' => ['required', 'string', 'max:100'], 'payload.features' => ['required', 'string', 'max:2000'], 'payload.benefits' => ['nullable', 'string', 'max:2000'], 'payload.audience' => ['required', 'string', 'max:200'], 'payload.tone' => ['required', Rule::in(['professional', 'friendly', 'concise', 'luxury'])], 'payload.keywords' => ['nullable', 'string', 'max:500'], 'payload.length' => ['required', Rule::in(['short', 'medium', 'long'])], 'payload.use_ollama' => ['nullable', 'boolean']],
            'resume-builder' => ['payload.name' => ['required', 'string', 'max:120'], 'payload.email' => ['required', 'email', 'max:200'], 'payload.summary' => ['required', 'string', 'max:3000'], 'payload.sections' => ['required', 'array', 'max:12'], 'payload.template' => ['required', Rule::in(['classic', 'modern', 'compact'])]],
            'visiting-card-letterhead' => ['payload.name' => ['required', 'string', 'max:120'], 'payload.business' => ['required', 'string', 'max:150'], 'payload.email' => ['nullable', 'email'], 'payload.website' => ['nullable', 'url'], 'payload.output' => ['required', Rule::in(['card', 'letterhead'])]],
            'whatsapp-link-generator' => ['payload.phone' => ['required', 'string', 'max:30'], 'payload.message' => ['nullable', 'string', 'max:1000']],
            'seo-audit' => ['payload.url' => ['required', 'url:http,https', 'max:2048']],
            'bulk-watermark-tool' => ['images' => ['required', 'array', 'min:1', 'max:20'], 'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.config('image-tools.max_upload_kb')], 'logo' => ['nullable', 'file', 'mimes:png,webp', 'max:2048'], 'payload.mode' => ['required', Rule::in(['add', 'repair'])], 'payload.text' => ['nullable', 'string', 'max:100'], 'payload.position' => ['required', Rule::in(['center', 'top-left', 'top-right', 'bottom-left', 'bottom-right'])], 'payload.opacity' => ['required', 'integer', 'between:5,100'], 'payload.rotation' => ['required', 'integer', 'between:-180,180'], 'payload.scale' => ['required', 'integer', 'between:5,80'], 'payload.spacing' => ['required', 'integer', 'between:10,500'], 'payload.tiled' => ['nullable', 'boolean'], 'payload.authorization_confirmed' => [Rule::requiredIf(fn () => data_get($this->input('payload'), 'mode') === 'repair'), 'accepted']],
            default => [],
        });
    }

    private function invoiceRules(): array
    {
        return ['logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'], 'payload.document_type' => ['required', Rule::in(['invoice', 'quotation'])], 'payload.business_name' => ['required', 'string', 'max:180'], 'payload.customer_name' => ['required', 'string', 'max:180'], 'payload.number' => ['required', 'string', 'max:60'], 'payload.issue_date' => ['required', 'date'], 'payload.due_date' => ['required', 'date'], 'payload.currency' => ['required', 'string', 'size:3'], 'payload.items' => ['required', 'array', 'min:1', 'max:100'], 'payload.items.*.description' => ['required', 'string', 'max:500'], 'payload.items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'], 'payload.items.*.price' => ['required', 'numeric', 'min:0', 'max:100000000'], 'payload.items.*.discount' => ['nullable', 'numeric', 'between:0,100'], 'payload.items.*.tax' => ['nullable', 'numeric', 'between:0,100'], 'payload.shipping' => ['nullable', 'numeric', 'min:0'], 'payload.additional' => ['nullable', 'numeric'], 'payload.notes' => ['nullable', 'string', 'max:5000']];
    }
}
