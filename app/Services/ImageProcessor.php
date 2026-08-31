<?php

namespace App\Services;

use App\Models\ProcessingTask;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class ImageProcessor
{
    private string $directory;

    public function __construct(private readonly ImageAiClient $ai) {}

    public function process(ProcessingTask $task): array
    {
        $this->directory = "tasks/{$task->id}";
        $inputs = $task->input_metadata['files'];

        return match ($task->tool) {
            'background-remover' => $this->background($inputs[0], $task->options),
            'image-compressor' => $this->compress($inputs[0], $task->options),
            'product-photo-enhancer' => $this->enhance($inputs[0], $task->options),
            'passport-photo-maker' => $this->passport($inputs[0], $task->options),
            'logo-transparency' => $this->transparency($inputs[0], $task->options),
            'image-to-webp' => $this->webp($inputs, $task->options),
            default => throw new RuntimeException('Unknown image tool.'),
        };
    }

    private function background(array $file, array $options): array
    {
        $path = $this->outputPath('background-removed.png');
        $this->ai->removeBackground($this->absolute($file['path']), $this->absolute($path));
        if (($options['background_mode'] ?? 'transparent') === 'solid') {
            $image = $this->load($path);
            $flattened = $this->canvas(imagesx($image), imagesy($image), $options['background_color'] ?? '#ffffff', false);
            imagecopy($flattened, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            imagepng($flattened, $this->absolute($path), 7);
            imagedestroy($flattened);
        }

        return $this->result([$this->fileResult($path, 'background-removed.png')], $file['size']);
    }

    private function compress(array $file, array $options): array
    {
        $source = $this->load($file['path']);
        [$width, $height] = $this->dimensions($source, $options);
        $output = imagecreatetruecolor($width, $height);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagecopyresampled($output, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        $format = $options['format'];
        $path = $this->outputPath("optimized.$format");
        $this->save($output, $path, $format, (int) $options['quality']);
        imagedestroy($source);
        imagedestroy($output);

        return $this->result([$this->fileResult($path, "optimized.$format", $width, $height)], $file['size']);
    }

    private function enhance(array $file, array $options): array
    {
        $working = $file['path'];
        if ($options['ai_upscale'] ?? false) {
            $working = $this->outputPath('ai-enhanced.png');
            $this->ai->enhance($this->absolute($file['path']), $this->absolute($working));
        }
        $image = $this->load($working);
        $brightness = ($options['auto_enhance'] ?? false) ? 5 : (int) $options['brightness'];
        $contrast = ($options['auto_enhance'] ?? false) ? -8 : -(int) $options['contrast'];
        imagefilter($image, IMG_FILTER_BRIGHTNESS, (int) round($brightness * 2.55));
        imagefilter($image, IMG_FILTER_CONTRAST, $contrast);
        $saturation = (int) $options['saturation'];
        if ($saturation !== 0) {
            imagefilter($image, IMG_FILTER_COLORIZE, $saturation, $saturation, $saturation, 0);
        }
        if ((int) $options['sharpness'] > 0) {
            $amount = 1 + ((int) $options['sharpness'] / 25);
            imageconvolution($image, [[0, -1, 0], [-1, 4 + $amount, -1], [0, -1, 0]], $amount, 0);
        }
        if ($options['white_background'] ?? false) {
            $flat = $this->canvas(imagesx($image), imagesy($image), '#ffffff', false);
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagedestroy($image);
            $image = $flat;
        }
        $path = $this->outputPath('enhanced.jpg');
        imagejpeg($image, $this->absolute($path), 92);
        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        return $this->result([$this->fileResult($path, 'enhanced.jpg', $width, $height)], $file['size']);
    }

    private function passport(array $file, array $options): array
    {
        $sourcePath = $file['path'];
        if ($options['remove_background'] ?? false) {
            $sourcePath = $this->outputPath('subject.png');
            $this->ai->removeBackground($this->absolute($file['path']), $this->absolute($sourcePath));
        }
        $source = $this->load($sourcePath);
        $dpi = 300;
        $photoW = (int) round($options['photo_width_mm'] / 25.4 * $dpi);
        $photoH = (int) round($options['photo_height_mm'] / 25.4 * $dpi);
        $photo = $this->cover($source, $photoW, $photoH, $options['background_color']);
        $individualPath = $this->outputPath('passport-photo.jpg');
        imagejpeg($photo, $this->absolute($individualPath), 94);

        [$paperW, $paperH] = $options['paper'] === 'a4' ? [2480, 3508] : [1200, 1800];
        $margin = (int) round($options['margin_mm'] / 25.4 * $dpi);
        $gap = (int) round($options['gap_mm'] / 25.4 * $dpi);
        $cols = max(1, intdiv($paperW - 2 * $margin + $gap, $photoW + $gap));
        $rows = max(1, intdiv($paperH - 2 * $margin + $gap, $photoH + $gap));
        $sheet = $this->canvas($paperW, $paperH, '#ffffff', false);
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                imagecopy($sheet, $photo, $margin + $col * ($photoW + $gap), $margin + $row * ($photoH + $gap), 0, 0, $photoW, $photoH);
            }
        }
        $sheetPath = $this->outputPath('passport-sheet.jpg');
        imagejpeg($sheet, $this->absolute($sheetPath), 94);
        imagedestroy($source);
        imagedestroy($photo);
        imagedestroy($sheet);

        return $this->result([
            $this->fileResult($individualPath, 'passport-photo.jpg', $photoW, $photoH, 'Individual photo'),
            $this->fileResult($sheetPath, 'passport-sheet.jpg', $paperW, $paperH, "Print sheet ($cols × $rows)"),
        ], $file['size']);
    }

    private function transparency(array $file, array $options): array
    {
        $image = $this->load($file['path']);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        [$red, $green, $blue] = $this->rgb($options['background_color']);
        $tolerance = (int) $options['tolerance'];
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $colour = imagecolorat($image, $x, $y);
                $r = ($colour >> 16) & 0xFF;
                $g = ($colour >> 8) & 0xFF;
                $b = $colour & 0xFF;
                $distance = sqrt(($r - $red) ** 2 + ($g - $green) ** 2 + ($b - $blue) ** 2);
                if ($distance <= $tolerance) {
                    $alpha = 127 - (int) round(127 * $distance / max(1, $tolerance));
                    imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, $alpha));
                }
            }
        }
        $path = $this->outputPath('transparent-logo.png');
        imagepng($image, $this->absolute($path), 7);
        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        return $this->result([$this->fileResult($path, 'transparent-logo.png', $width, $height)], $file['size']);
    }

    private function webp(array $files, array $options): array
    {
        $results = [];
        $originalSize = 0;
        foreach ($files as $index => $file) {
            $image = $this->load($file['path']);
            $name = count($files) === 1 ? 'converted.webp' : sprintf('%02d-%s.webp', $index + 1, pathinfo($file['original_name'], PATHINFO_FILENAME));
            $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', $name);
            $path = $this->outputPath($name);
            imagewebp($image, $this->absolute($path), (int) $options['quality']);
            $results[] = $this->fileResult($path, $name, imagesx($image), imagesy($image));
            $originalSize += $file['size'];
            imagedestroy($image);
        }
        if (count($results) > 1) {
            $zipPath = $this->outputPath('webp-images.zip');
            $zip = new ZipArchive;
            $zip->open($this->absolute($zipPath), ZipArchive::CREATE | ZipArchive::OVERWRITE);
            foreach ($results as $result) {
                $zip->addFile($this->absolute($result['path']), $result['name']);
            }
            $zip->close();
            $results = [$this->fileResult($zipPath, 'webp-images.zip', label: count($files).' converted images')];
        }

        return $this->result($results, $originalSize);
    }

    private function load(string $path): GdImage
    {
        $bytes = file_get_contents($this->absolute($path));
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);
        if (! $image) {
            throw new RuntimeException('The uploaded image could not be decoded.');
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private function dimensions(GdImage $image, array $options): array
    {
        $sourceW = imagesx($image);
        $sourceH = imagesy($image);
        $width = (int) ($options['width'] ?: $sourceW);
        $height = (int) ($options['height'] ?: $sourceH);
        if ($options['preserve_aspect'] ?? false) {
            if (! $options['width']) {
                $width = (int) round($height * $sourceW / $sourceH);
            } elseif (! $options['height']) {
                $height = (int) round($width * $sourceH / $sourceW);
            } else {
                $scale = min($width / $sourceW, $height / $sourceH);
                $width = (int) round($sourceW * $scale);
                $height = (int) round($sourceH * $scale);
            }
        }
        if ($options['prevent_enlarge'] ?? false) {
            $scale = min(1, $width / $sourceW, $height / $sourceH);
            $width = (int) round($sourceW * $scale);
            $height = (int) round($sourceH * $scale);
        }

        return [max(1, $width), max(1, $height)];
    }

    private function cover(GdImage $source, int $width, int $height, string $colour): GdImage
    {
        $canvas = $this->canvas($width, $height, $colour, false);
        $scale = max($width / imagesx($source), $height / imagesy($source));
        $srcW = (int) round($width / $scale);
        $srcH = (int) round($height / $scale);
        $srcX = (int) round((imagesx($source) - $srcW) / 2);
        $srcY = (int) round((imagesy($source) - $srcH) / 2);
        imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $width, $height, $srcW, $srcH);

        return $canvas;
    }

    private function canvas(int $width, int $height, string $colour, bool $transparent): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        [$r, $g, $b] = $this->rgb($colour);
        $fill = imagecolorallocatealpha($image, $r, $g, $b, $transparent ? 127 : 0);
        imagefill($image, 0, 0, $fill);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private function save(GdImage $image, string $path, string $format, int $quality): void
    {
        match ($format) {
            'jpg' => imagejpeg($image, $this->absolute($path), $quality),
            'png' => imagepng($image, $this->absolute($path), (int) round((100 - $quality) * 9 / 100)),
            'webp' => imagewebp($image, $this->absolute($path), $quality),
        };
    }

    private function outputPath(string $name): string
    {
        Storage::disk('local')->makeDirectory($this->directory);

        return "{$this->directory}/$name";
    }

    private function absolute(string $path): string
    {
        $absolute = Storage::disk('local')->path($path);
        $root = realpath(Storage::disk('local')->path(''));
        $parent = realpath(dirname($absolute));
        if ($root === false || $parent === false || ! str_starts_with($parent, $root)) {
            throw new RuntimeException('Unsafe storage path.');
        }

        return $absolute;
    }

    private function fileResult(string $path, string $name, ?int $width = null, ?int $height = null, ?string $label = null): array
    {
        return array_filter([
            'path' => $path,
            'name' => $name,
            'label' => $label,
            'size' => Storage::disk('local')->size($path),
            'width' => $width,
            'height' => $height,
        ], fn ($value) => $value !== null);
    }

    private function result(array $files, int $originalSize): array
    {
        $outputSize = array_sum(array_column($files, 'size'));

        return [
            'files' => $files,
            'original_size' => $originalSize,
            'output_size' => $outputSize,
            'saved_percent' => $originalSize > 0 ? round((1 - $outputSize / $originalSize) * 100, 1) : 0,
        ];
    }
}
