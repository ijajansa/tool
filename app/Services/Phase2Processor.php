<?php

namespace App\Services;

use App\Models\ProcessingTask;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class Phase2Processor
{
    public function __construct(private readonly PdfToolkitService $pdfs, private readonly InvoiceCalculator $invoices, private readonly GstCalculator $gst, private readonly BarcodeService $barcodes, private readonly WhatsAppLinkService $whatsapp, private readonly QrCodeService $qr, private readonly SeoAuditService $seo) {}

    public function process(ProcessingTask $task): array
    {
        $directory = "tasks/{$task->id}";
        Storage::disk('local')->makeDirectory($directory);
        $files = match ($task->tool) {
            'pdf-toolkit' => $this->pdfs->process($task->input_metadata['files'], $task->options, $directory),
            'invoice-generator' => [$this->invoice($task->options, $directory, false, collect($task->input_metadata['files'])->firstWhere('field', 'logo'))],
            'gst-invoice-generator' => [$this->invoice($task->options, $directory, true, collect($task->input_metadata['files'])->firstWhere('field', 'logo'))],
            'barcode-label-generator' => [$this->barcode($task->options, $directory)],
            'product-description-generator' => [$this->description($task->options, $directory)],
            'resume-builder' => [$this->document('documents.resume', $task->options, "$directory/resume.pdf", 'resume.pdf')],
            'visiting-card-letterhead' => [$this->document('documents.stationery', $task->options, "$directory/stationery.pdf", 'stationery.pdf')],
            'whatsapp-link-generator' => [$this->whatsapp($task->options, $directory)],
            'seo-audit' => [$this->seo($task->options, $directory)],
            'bulk-watermark-tool' => $this->watermark(array_values(array_filter($task->input_metadata['files'], fn ($file) => $file['field'] === 'images')), collect($task->input_metadata['files'])->firstWhere('field', 'logo'), $task->options, $directory),
            default => throw new RuntimeException('Unsupported Phase 2 task.'),
        };
        $original = array_sum(array_column($task->input_metadata['files'] ?? [], 'size'));
        $size = array_sum(array_column($files, 'size'));

        return ['files' => $files, 'original_size' => $original, 'output_size' => $size, 'saved_percent' => $original ? round((1 - $size / $original) * 100, 1) : 0];
    }

    private function invoice(array $data, string $directory, bool $gst, ?array $logoFile): array
    {
        $totals = $gst
            ? $this->gst->calculate($data['items'], $data['transaction'] === 'interstate')
            : $this->invoices->calculate($data['items'], (float) ($data['shipping'] ?? 0), (float) ($data['additional'] ?? 0));

        $logo = $logoFile ? 'data:'.$logoFile['mime'].';base64,'.base64_encode(Storage::disk('local')->get($logoFile['path'])) : null;

        return $this->document('documents.invoice', compact('data', 'totals', 'gst', 'logo'), "$directory/{$data['document_type']}.pdf", "{$data['document_type']}.pdf");
    }

    private function barcode(array $data, string $directory): array
    {
        $output = $data['output'];
        $code = $this->barcodes->render($data['value'], $data['format'], $output === 'png' ? 'png' : 'svg');
        if ($output === 'pdf') {
            return $this->document('documents.labels', ['data' => $data, 'barcode' => $code], "$directory/labels.pdf", 'labels.pdf');
        }
        $path = "$directory/barcode.$output";
        Storage::disk('local')->put($path, $code);

        return $this->meta($path, "barcode.$output");
    }

    private function description(array $data, string $directory): array
    {
        $features = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', $data['features']))));
        $text = "{$data['product_name']} is designed for {$data['audience']}. ".($features ? 'It brings together '.implode(', ', $features).'. ' : '').trim($data['benefits'] ?? '')."\n\nIdeal for the {$data['category']} category, this {$data['tone']} description highlights practical value without exaggerated claims.";
        if (($data['length'] ?? 'medium') === 'long') {
            $text .= "\n\nKey details: ".implode('; ', $features).'. Keywords: '.($data['keywords'] ?: 'not specified').'.';
        }
        if (($data['length'] ?? '') === 'short') {
            $text = strtok($text, "\n");
        }
        if (($data['use_ollama'] ?? false) && config('phase2.ollama.url')) {
            $response = Http::timeout(config('phase2.ollama.timeout'))->post(config('phase2.ollama.url').'/api/generate', ['model' => config('phase2.ollama.model'), 'stream' => false, 'prompt' => 'Rewrite this product description accurately. Do not invent facts: '.$text]);
            if ($response->successful() && $response->json('response')) {
                $text = $response->json('response');
            }
        }
        $path = "$directory/product-description.txt";
        Storage::disk('local')->put($path, $text);

        return $this->meta($path, 'product-description.txt', 'Rules-based editable description');
    }

    private function whatsapp(array $data, string $directory): array
    {
        $link = $this->whatsapp->link($data['phone'], $data['message'] ?? '');
        $svg = $this->qr->svg($link);
        $path = "$directory/whatsapp-qr.svg";
        Storage::disk('local')->put($path, $svg);

        return $this->meta($path, 'whatsapp-qr.svg', $link);
    }

    private function seo(array $data, string $directory): array
    {
        $report = $this->seo->audit($data['url']);

        return $this->document('documents.seo-report', compact('report'), "$directory/seo-audit.pdf", 'seo-audit.pdf');
    }

    private function watermark(array $files, ?array $logoFile, array $options, string $directory): array
    {
        $outputs = [];
        foreach ($files as $index => $file) {
            $source = @imagecreatefromstring(Storage::disk('local')->get($file['path']));
            if (! $source) {
                throw new InvalidArgumentException('An uploaded image could not be decoded.');
            }
            if ($options['mode'] === 'repair') {
                $x = (int) round(imagesx($source) * .35);
                $y = (int) round(imagesy($source) * .35);
                imagefilter($source, IMG_FILTER_GAUSSIAN_BLUR);
                imagefilter($source, IMG_FILTER_GAUSSIAN_BLUR);
                // Conservative centre-region soft repair; this is not an ownership bypass tool.
                imagecopyresampled($source, $source, $x, $y, max(0, $x - 20), max(0, $y - 20), (int) (imagesx($source) * .3), (int) (imagesy($source) * .3), (int) (imagesx($source) * .3), (int) (imagesy($source) * .3));
            } else {
                $text = $options['text'] ?: config('site.name');
                $color = imagecolorallocatealpha($source, 255, 255, 255, (int) round(127 * (1 - $options['opacity'] / 100)));
                $points = ($options['tiled'] ?? false) ? $this->tilePoints(imagesx($source), imagesy($source), (int) $options['spacing']) : [$this->watermarkPoint(imagesx($source), imagesy($source), $options['position'])];
                $logo = $logoFile ? @imagecreatefromstring(Storage::disk('local')->get($logoFile['path'])) : false;
                if ($logo) {
                    $targetW = max(20, (int) (imagesx($source) * $options['scale'] / 100));
                    $targetH = (int) (imagesy($logo) * $targetW / imagesx($logo));
                    $scaled = imagescale($logo, $targetW, $targetH);
                    if ((int) $options['rotation'] !== 0) {
                        $scaled = imagerotate($scaled, -(int) $options['rotation'], imagecolorallocatealpha($scaled, 0, 0, 0, 127));
                    }
                    foreach ($points as [$x, $y]) {
                        imagecopymerge($source, $scaled, $x, $y, 0, 0, imagesx($scaled), imagesy($scaled), (int) $options['opacity']);
                    }
                    imagedestroy($logo);
                    imagedestroy($scaled);
                } else {
                    foreach ($points as [$x, $y]) {
                        imagestring($source, 5, $x, $y, $text, $color);
                    }
                }
            }
            $path = "$directory/watermarked-".($index + 1).'.png';
            imagepng($source, Storage::disk('local')->path($path), 7);
            imagedestroy($source);
            $outputs[] = $this->meta($path, basename($path));
        }
        if (count($outputs) <= 1) {
            return $outputs;
        }
        $zipPath = "$directory/watermarked-images.zip";
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($zipPath), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($outputs as $output) {
            $zip->addFile(Storage::disk('local')->path($output['path']), $output['name']);
        }
        $zip->close();

        return [$this->meta($zipPath, 'watermarked-images.zip', count($outputs).' processed images')];
    }

    private function tilePoints(int $width, int $height, int $spacing): array
    {
        $points = [];
        for ($y = 10; $y < $height; $y += $spacing) {
            for ($x = 10; $x < $width; $x += $spacing) {
                $points[] = [$x, $y];
            }
        }

        return $points;
    }

    private function watermarkPoint(int $width, int $height, string $position): array
    {
        return match ($position) {
            'top-left' => [15, 15], 'top-right' => [(int) ($width * .7), 15], 'bottom-left' => [15, (int) ($height * .9)], 'bottom-right' => [(int) ($width * .7), (int) ($height * .9)], default => [(int) ($width * .4), (int) ($height * .5)]
        };
    }

    private function document(string $view, array $data, string $path, string $name): array
    {
        Storage::disk('local')->put($path, Pdf::loadView($view, $data)->setPaper('a4')->output());

        return $this->meta($path, $name);
    }

    private function meta(string $path, string $name, ?string $label = null): array
    {
        return array_filter(['path' => $path, 'name' => $name, 'label' => $label, 'size' => Storage::disk('local')->size($path)]);
    }
}
