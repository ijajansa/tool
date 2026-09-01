<?php

namespace App\Http\Controllers;

use App\Models\DynamicQrCode;
use App\Models\DynamicQrScan;
use App\Services\QrCodeService;
use App\Services\SafeUrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DynamicQrController extends Controller
{
    public function store(Request $request, SafeUrlValidator $validator, QrCodeService $qr): JsonResponse
    {
        $data = $request->validate(['mode' => ['required', 'in:static,dynamic'], 'content_type' => ['required', 'in:url,text,email,phone,wifi'], 'content' => ['required', 'string', 'max:4096'], 'ecc' => ['required', 'in:L,M,Q,H'], 'size' => ['required', 'integer', 'between:4,20'], 'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        if ($data['mode'] === 'static') {
            return response()->json(['svg' => $qr->svg($data['content'], $data['ecc'], $data['size'], $data['color'] ?? '#000000'), 'notice' => 'Static QR content is encoded directly and does not use tracking.']);
        }
        abort_unless($data['content_type'] === 'url' && filter_var($data['content'], FILTER_VALIDATE_URL), 422, 'Dynamic QR codes require a complete public URL.');
        $data['destination_url'] = $data['content'];
        $validator->validate($data['destination_url']);
        $token = Str::random(64);
        $code = DynamicQrCode::create(['public_id' => Str::lower(Str::random(20)), 'destination_url' => $data['destination_url'], 'management_token_hash' => hash('sha256', $token)]);
        $svg = $qr->svg(route('qr.redirect', $code->public_id), $data['ecc'], $data['size'], $data['color'] ?? '#000000');
        Storage::disk('local')->put("qr/{$code->public_id}.svg", $svg);

        return response()->json(['redirect_url' => route('qr.redirect', $code->public_id), 'download_url' => route('qr.image', ['code' => $code->public_id, 'token' => $token]), 'analytics_url' => route('qr.analytics', ['code' => $code->public_id, 'token' => $token]), 'management_token' => $token], 201);
    }

    public function redirect(Request $request, string $code, SafeUrlValidator $validator): RedirectResponse
    {
        $qr = DynamicQrCode::where('public_id', $code)->where('active', true)->firstOrFail();
        $validator->validate($qr->destination_url);
        $agent = strtolower((string) $request->userAgent());
        $device = preg_match('/mobile|android|iphone/', $agent) ? 'mobile' : (preg_match('/bot|crawler|spider/', $agent) ? 'bot' : 'desktop');
        $referrer = parse_url((string) $request->header('Referer'), PHP_URL_HOST);
        DynamicQrScan::create(['dynamic_qr_code_id' => $qr->id, 'scanned_at' => now(), 'device' => $device, 'referrer_domain' => is_string($referrer) ? Str::limit(strtolower($referrer), 255, '') : null, 'ip_hash' => hash_hmac('sha256', (string) $request->ip(), config('app.key').now()->format('Y-m'))]);

        return redirect()->away($qr->destination_url, 302, ['Referrer-Policy' => 'no-referrer']);
    }

    public function analytics(Request $request, string $code): JsonResponse
    {
        $qr = $this->authorized($request, $code);

        return response()->json(['destination_url' => $qr->destination_url, 'total' => $qr->scans()->count(), 'by_day' => $qr->scans()->selectRaw('DATE(scanned_at) day, COUNT(*) scans')->groupBy('day')->orderBy('day')->pluck('scans', 'day'), 'by_device' => $qr->scans()->selectRaw('device, COUNT(*) scans')->groupBy('device')->pluck('scans', 'device'), 'by_referrer' => $qr->scans()->selectRaw('COALESCE(referrer_domain, ?) referrer, COUNT(*) scans', ['Direct / unknown'])->groupBy('referrer')->pluck('scans', 'referrer')]);
    }

    public function image(Request $request, string $code)
    {
        $qr = $this->authorized($request, $code);
        $path = "qr/{$qr->public_id}.svg";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response(Storage::disk('local')->get($path), 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => 'attachment; filename="dynamic-qr.svg"', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function authorized(Request $request, string $code): DynamicQrCode
    {
        $qr = DynamicQrCode::where('public_id', $code)->firstOrFail();
        abort_unless(is_string($request->query('token')) && hash_equals($qr->management_token_hash, hash('sha256', $request->query('token'))), 403);

        return $qr;
    }
}
