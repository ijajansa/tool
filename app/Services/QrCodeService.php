<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

class QrCodeService
{
    public function svg(string $content, string $ecc = 'M', int $scale = 8, string $color = '#000000'): string
    {
        $options = new QROptions(['outputBase64' => false, 'eccLevel' => $ecc, 'scale' => max(4, min(20, $scale)), 'addQuietzone' => true]);
        $svg = (new QRCode($options))->render($content);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? str_ireplace('#000000', strtolower($color), $svg) : $svg;
    }
}
