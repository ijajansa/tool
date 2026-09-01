<?php

namespace App\Services;

use InvalidArgumentException;

class WhatsAppLinkService
{
    public function normalize(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', trim($phone));
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }
        $digits = ltrim($phone, '+');
        if (! str_starts_with($phone, '+') || ! preg_match('/^[1-9][0-9]{7,14}$/', $digits)) {
            throw new InvalidArgumentException('Enter a complete international number beginning with + and country code.');
        }

        return $digits;
    }

    public function link(string $phone, string $message = ''): string
    {
        $url = 'https://wa.me/'.$this->normalize($phone);

        return $message === '' ? $url : $url.'?text='.rawurlencode($message);
    }
}
