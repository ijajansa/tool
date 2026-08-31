<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImageAiClient
{
    public function removeBackground(string $input, string $output): void
    {
        $this->send('/v1/remove-background', $input, $output);
    }

    public function enhance(string $input, string $output): void
    {
        $this->send('/v1/enhance', $input, $output);
    }

    public function available(): bool
    {
        try {
            return Http::timeout(2)->get(config('image-tools.ai.url').'/health')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    private function send(string $endpoint, string $input, string $output): void
    {
        try {
            $response = Http::timeout(config('image-tools.ai.timeout'))
                ->attach('file', fopen($input, 'rb'), basename($input))
                ->post(config('image-tools.ai.url').$endpoint);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('The local AI service is unavailable. Start it and try again.', previous: $exception);
        }

        if (! $response->successful() || ! str_starts_with($response->header('Content-Type'), 'image/')) {
            throw new RuntimeException($response->json('error.message', 'The local AI service could not process this image.'));
        }

        file_put_contents($output, $response->body());
    }
}
