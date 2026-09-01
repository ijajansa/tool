<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use ZipArchive;

class PdfToolkitService
{
    public function process(array $files, array $options, string $directory): array
    {
        $operation = $options['operation'];
        if ($operation === 'compress') {
            return [$this->compress($files[0]['path'], $options['level'] ?? 'ebook', $directory)];
        }
        if ($operation === 'merge') {
            return [$this->merge(array_column($files, 'path'), "$directory/merged.pdf")];
        }

        return $this->split($files[0]['path'], (string) ($options['ranges'] ?? ''), $directory);
    }

    public function parseRanges(string $ranges, int $pages): array
    {
        if (! preg_match('/^\s*\d+(?:\s*-\s*\d+)?(?:\s*,\s*\d+(?:\s*-\s*\d+)?)*\s*$/', $ranges)) {
            throw new InvalidArgumentException('Use page ranges such as 1-3, 5, 8-10.');
        }
        $groups = [];
        foreach (explode(',', $ranges) as $part) {
            $bounds = array_map('intval', preg_split('/\s*-\s*/', trim($part)));
            $end = $bounds[1] ?? $bounds[0];
            if ($bounds[0] < 1 || $end < $bounds[0] || $end > $pages) {
                throw new InvalidArgumentException("Page range '$part' is outside this $pages-page PDF.");
            }
            $groups[] = range($bounds[0], $end);
        }

        return $groups;
    }

    private function count(string $path): int
    {
        try {
            return (new Fpdi)->setSourceFile(Storage::disk('local')->path($path));
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('The PDF is encrypted, damaged, or uses an unsupported structure. Install qpdf to decrypt an authorized document first.', previous: $e);
        }
    }

    private function merge(array $paths, string $output): array
    {
        $pdf = new Fpdi;
        $total = 0;
        foreach ($paths as $path) {
            $pages = $pdf->setSourceFile(Storage::disk('local')->path($path));
            $total += $pages;
            if ($total > config('phase2.max_pdf_pages')) {
                throw new InvalidArgumentException('The combined PDF exceeds the configured page limit.');
            }
            for ($page = 1; $page <= $pages; $page++) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
        }
        Storage::disk('local')->put($output, $pdf->Output('S'));

        return $this->meta($output, 'merged.pdf', "$total pages");
    }

    private function split(string $input, string $ranges, string $directory): array
    {
        $pages = $this->count($input);
        if ($pages > config('phase2.max_pdf_pages')) {
            throw new InvalidArgumentException('The PDF exceeds the configured page limit.');
        }
        $groups = $this->parseRanges($ranges, $pages);
        $outputs = [];
        foreach ($groups as $index => $group) {
            $pdf = new Fpdi;
            $pdf->setSourceFile(Storage::disk('local')->path($input));
            foreach ($group as $page) {
                $template = $pdf->importPage($page);
                $size = $pdf->getTemplateSize($template);
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($template);
            }
            $path = "$directory/pages-".implode('-', $group).'.pdf';
            Storage::disk('local')->put($path, $pdf->Output('S'));
            $outputs[] = $this->meta($path, basename($path), 'Pages '.implode(', ', $group));
        }
        if (count($outputs) === 1) {
            return $outputs;
        }
        $zipPath = "$directory/split-pdfs.zip";
        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($zipPath), ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($outputs as $output) {
            $zip->addFile(Storage::disk('local')->path($output['path']), $output['name']);
        }
        $zip->close();

        return [$this->meta($zipPath, 'split-pdfs.zip', count($outputs).' PDF ranges')];
    }

    private function compress(string $input, string $level, string $directory): array
    {
        $finder = new ExecutableFinder;
        $configured = config('phase2.ghostscript');
        $executable = is_file($configured) ? $configured : ($finder->find($configured) ?: $finder->find('gswin64c'));
        if (! $executable) {
            throw new RuntimeException('PDF compression requires Ghostscript. Set GHOSTSCRIPT_PATH to gs or gswin64c.');
        }
        $output = "$directory/compressed.pdf";
        $process = new Process([$executable, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.6', '-dPDFSETTINGS=/'.$level, '-dNOPAUSE', '-dQUIET', '-dBATCH', '-sOutputFile='.Storage::disk('local')->path($output), Storage::disk('local')->path($input)]);
        $process->setTimeout(180)->mustRun();

        return $this->meta($output, 'compressed.pdf');
    }

    private function meta(string $path, string $name, ?string $label = null): array
    {
        return array_filter(['path' => $path, 'name' => $name, 'label' => $label, 'size' => Storage::disk('local')->size($path)]);
    }
}
