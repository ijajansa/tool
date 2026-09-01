<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class SeoAuditService
{
    public function __construct(private readonly SafeUrlValidator $validator) {}

    public function audit(string $url): array
    {
        $started = microtime(true);
        $redirects = [];
        for ($hop = 0; $hop <= 3; $hop++) {
            $safe = $this->validator->validate($url);
            $resolve = array_map(fn ($ip) => "{$safe['host']}:{$safe['port']}:$ip", $safe['ips']);
            $response = $this->request($url, $resolve);
            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $next = $this->absoluteUrl($url, (string) $response->header('Location'));
                $redirects[] = ['from' => $url, 'to' => $next, 'status' => $response->status()];
                $url = $next;

                continue;
            }
            break;
        }
        if (count($redirects) > 3) {
            throw new InvalidArgumentException('The URL exceeded the three-redirect safety limit.');
        }
        if (strlen($response->body()) > config('phase2.seo.max_bytes')) {
            throw new InvalidArgumentException('The page exceeds the configured response-size limit.');
        }
        $html = $response->body();
        $dom = new DOMDocument;
        @$dom->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);
        $title = trim((string) ($xpath->query('//title')->item(0)?->textContent ?? ''));
        $description = $this->meta($xpath, 'description');
        $canonical = $xpath->query('//link[translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="canonical"]')->item(0)?->getAttribute('href') ?? '';
        $h1 = $xpath->query('//h1')->length;
        $h2 = $xpath->query('//h2')->length;
        $images = $xpath->query('//img');
        $missingAlt = 0;
        foreach ($images as $image) {
            if (! $image->hasAttribute('alt')) {
                $missingAlt++;
            }
        }
        $links = $xpath->query('//a[@href]');
        $internal = $external = 0;
        $auditLinks = [];
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (str_starts_with($href, '/') || parse_url($href, PHP_URL_HOST) === $safe['host']) {
                $internal++;
                $auditLinks[] = str_starts_with($href, '/') ? parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).$href : $href;
            } elseif (preg_match('#^https?://#i', $href)) {
                $external++;
                $auditLinks[] = $href;
            }
        }
        $brokenLinks = [];
        foreach (array_slice(array_values(array_unique($auditLinks)), 0, config('phase2.seo.max_links')) as $link) {
            try {
                $linkSafe = $this->validator->validate($link);
                $linkResolve = array_map(fn ($ip) => "{$linkSafe['host']}:{$linkSafe['port']}:$ip", $linkSafe['ips']);
                if ($this->request($link, $linkResolve, true)->status() >= 400) {
                    $brokenLinks[] = $link;
                }
            } catch (\Throwable) {
                $brokenLinks[] = $link;
            }
        }
        $origin = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST);
        $robotsUrl = $origin.'/robots.txt';
        $sitemapUrl = $origin.'/sitemap.xml';
        $robotsFound = $this->probe($robotsUrl);
        $sitemapFound = $this->probe($sitemapUrl);
        $checks = [
            ['name' => 'HTTPS', 'passed' => str_starts_with($url, 'https://'), 'weight' => 15],
            ['name' => 'Successful status', 'passed' => $response->successful(), 'weight' => 15],
            ['name' => 'Title (10–60 characters)', 'passed' => mb_strlen($title) >= 10 && mb_strlen($title) <= 60, 'weight' => 15],
            ['name' => 'Description (50–160 characters)', 'passed' => mb_strlen($description) >= 50 && mb_strlen($description) <= 160, 'weight' => 15],
            ['name' => 'One H1', 'passed' => $h1 === 1, 'weight' => 10],
            ['name' => 'Canonical', 'passed' => $canonical !== '', 'weight' => 10],
            ['name' => 'Mobile viewport', 'passed' => $this->meta($xpath, 'viewport') !== '', 'weight' => 10],
            ['name' => 'Image alt coverage', 'passed' => $missingAlt === 0, 'weight' => 5],
            ['name' => 'Open Graph title', 'passed' => $xpath->query('//meta[@property="og:title"]')->length > 0, 'weight' => 3],
            ['name' => 'JSON-LD', 'passed' => $xpath->query('//script[@type="application/ld+json"]')->length > 0, 'weight' => 2],
        ];
        $score = array_sum(array_map(fn ($check) => $check['passed'] ? $check['weight'] : 0, $checks));

        return compact('url', 'redirects', 'title', 'description', 'canonical', 'h1', 'h2', 'missingAlt', 'internal', 'external', 'checks', 'score', 'brokenLinks', 'robotsUrl', 'sitemapUrl', 'robotsFound', 'sitemapFound') + ['status' => $response->status(), 'response_ms' => round((microtime(true) - $started) * 1000), 'robots' => $this->meta($xpath, 'robots'), 'x_robots_tag' => $response->header('X-Robots-Tag'), 'json_ld' => $xpath->query('//script[@type="application/ld+json"]')->length];
    }

    private function request(string $url, array $resolve, bool $head = false)
    {
        $pending = Http::timeout(config('phase2.seo.timeout'))->withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => $resolve]])->withHeaders(['User-Agent' => config('site.name').' SEO Audit/1.0', 'Accept' => 'text/html']);

        return $head ? $pending->head($url) : $pending->get($url);
    }

    private function probe(string $url): bool
    {
        try {
            $safe = $this->validator->validate($url);
            $resolve = array_map(fn ($ip) => "{$safe['host']}:{$safe['port']}:$ip", $safe['ips']);

            return $this->request($url, $resolve, true)->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function meta(DOMXPath $xpath, string $name): string
    {
        return trim((string) ($xpath->query('//meta[translate(@name,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz")="'.strtolower($name).'"]')->item(0)?->getAttribute('content') ?? ''));
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        if (! str_starts_with($location, '/')) {
            throw new InvalidArgumentException('A redirect returned an unsupported relative destination.');
        }

        return parse_url($base, PHP_URL_SCHEME).'://'.parse_url($base, PHP_URL_HOST).$location;
    }
}
