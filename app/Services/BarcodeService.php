<?php

namespace App\Services;

use InvalidArgumentException;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    public function validate(string $value, string $format): void
    {
        if ($format === 'EAN13' && ! preg_match('/^\d{13}$/', $value)) {
            throw new InvalidArgumentException('EAN-13 requires exactly 13 digits.');
        }
        if ($format === 'UPCA' && ! preg_match('/^\d{12}$/', $value)) {
            throw new InvalidArgumentException('UPC-A requires exactly 12 digits.');
        }
        if ($format === 'C39' && ! preg_match('/^[0-9A-Z .\-$\/+%]+$/', $value)) {
            throw new InvalidArgumentException('Code 39 supports uppercase letters, digits, spaces, and . - $ / + %.');
        }
        if (in_array($format, ['EAN13', 'UPCA'], true) && ! $this->validChecksum($value)) {
            throw new InvalidArgumentException("$format checksum is invalid.");
        }
        if ($value === '' || strlen($value) > 120) {
            throw new InvalidArgumentException('Barcode content must contain 1 to 120 characters.');
        }
    }

    public function render(string $value, string $format, string $output = 'svg'): string
    {
        $this->validate($value, $format);
        $type = match ($format) {
            'EAN13' => BarcodeGenerator::TYPE_EAN_13, 'UPCA' => BarcodeGenerator::TYPE_UPC_A, 'C39' => BarcodeGenerator::TYPE_CODE_39, default => BarcodeGenerator::TYPE_CODE_128
        };

        return $output === 'png'
            ? (new BarcodeGeneratorPNG)->getBarcode($value, $type, 3, 90)
            : (new BarcodeGeneratorSVG)->getBarcode($value, $type, 3, 90);
    }

    public function validChecksum(string $digits): bool
    {
        $check = (int) substr($digits, -1);
        $body = str_split(substr($digits, 0, -1));
        $sum = 0;
        foreach (array_reverse($body) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10 === $check;
    }
}
