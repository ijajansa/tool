<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreToolTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tool = config('tools.'.$this->route('tool'));

        return is_array($tool) && ($tool['phase'] ?? 1) === 1;
    }

    public function rules(): array
    {
        $tool = $this->route('tool');
        $max = config('image-tools.max_upload_kb');
        $image = ['required', 'file', "max:$max", 'mimetypes:image/jpeg,image/png,image/webp', 'extensions:jpg,jpeg,png,webp', 'dimensions:min_width=1,min_height=1'];
        $rules = [
            'images' => $tool === 'image-to-webp' ? ['required', 'array', 'min:1', 'max:10'] : ['required', 'array', 'size:1'],
            'images.*' => $image,
        ];

        return array_merge($rules, match ($tool) {
            'background-remover' => [
                'background_mode' => ['required', Rule::in(['transparent', 'solid'])],
                'background_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            ],
            'image-compressor' => [
                'quality' => ['required', 'integer', 'between:1,100'],
                'width' => ['nullable', 'integer', 'between:1,10000'],
                'height' => ['nullable', 'integer', 'between:1,10000'],
                'preserve_aspect' => ['nullable', 'boolean'],
                'prevent_enlarge' => ['nullable', 'boolean'],
                'format' => ['required', Rule::in(['jpg', 'png', 'webp'])],
            ],
            'product-photo-enhancer' => [
                'brightness' => ['required', 'integer', 'between:-100,100'],
                'contrast' => ['required', 'integer', 'between:-100,100'],
                'sharpness' => ['required', 'integer', 'between:0,100'],
                'saturation' => ['required', 'integer', 'between:-100,100'],
                'auto_enhance' => ['nullable', 'boolean'],
                'ai_upscale' => ['nullable', 'boolean'],
                'white_background' => ['nullable', 'boolean'],
            ],
            'passport-photo-maker' => [
                'photo_width_mm' => ['required', 'numeric', 'between:20,100'],
                'photo_height_mm' => ['required', 'numeric', 'between:20,100'],
                'paper' => ['required', Rule::in(['a4', '4x6'])],
                'margin_mm' => ['required', 'numeric', 'between:0,30'],
                'gap_mm' => ['required', 'numeric', 'between:0,20'],
                'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'remove_background' => ['nullable', 'boolean'],
            ],
            'logo-transparency' => [
                'background_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'tolerance' => ['required', 'integer', 'between:0,255'],
            ],
            'image-to-webp' => ['quality' => ['required', 'integer', 'between:1,100']],
            default => [],
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['preserve_aspect', 'prevent_enlarge', 'auto_enhance', 'ai_upscale', 'white_background', 'remove_background'] as $key) {
            if ($this->has($key)) {
                $this->merge([$key => $this->boolean($key)]);
            }
        }
    }
}
