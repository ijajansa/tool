# PixelMint Tools — Phase 1

A Laravel 12 platform for free, anonymous image tools with private queued processing and an optional local FastAPI AI service.

## Included tools

- Background removal with transparent PNG or solid colour
- Image compression/resizing to JPG, PNG, or WebP
- Product-photo enhancement with controls and optional 2× AI-service upscale
- Passport/ID photo and print-sheet generation (A4 and 4×6)
- Colour-selected logo background transparency
- Single/batch WebP conversion with ZIP output

The tool catalogue lives in `config/tools.php`; site branding lives in `config/site.php`. Upload limits, expiry, rate limits, and AI connection settings live in `config/image-tools.php`.

## Application setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Use the equivalent `cp .env.example .env` on Linux. Existing installations should merge the new image settings from `.env.example`, preserving their real secrets. PHP needs GD, Fileinfo, cURL, ZIP, and PDO for the chosen database.

For local development, `composer run dev` starts Laravel, Vite, logs, and the queue listener. Alternatively run these in separate terminals:

```powershell
php artisan serve
npm run dev
php artisan queue:work --tries=1 --timeout=180
php artisan schedule:work
```

Production should supervise `php artisan queue:work --tries=1 --timeout=180` and execute `php artisan schedule:run` every minute. The scheduled `images:cleanup` command removes expired database records and private input/output directories every ten minutes. A synchronous queue is suitable only for development/testing (`QUEUE_CONNECTION=sync`).

## Local AI service

The service is documented in [`services/image-ai/README.md`](services/image-ai/README.md). Quick start on Windows:

```powershell
cd services/image-ai
py -3.11 -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8090
```

Linux:

```bash
cd services/image-ai
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8090
```

Background removal and options that explicitly request it need the service. Compression, resizing, standard enhancement, passport layout without removal, transparency, and WebP conversion work entirely inside PHP. The default enhancer is a maintained Pillow fallback; Real-ESRGAN is documented as an optional future backend because its PyTorch/CUDA requirements vary by host.

## Security and lifecycle

Files use UUID names under Laravel's private local disk and are never linked into `public`. Task status is bound to the anonymous session. Downloads require both the owning session and a short-lived Laravel signature. Server-side MIME, extension, byte-size, and decoded pixel limits are enforced. Processing options are allow-listed, raw IPs are not stored, generated files are re-encoded to strip metadata, and no user-provided shell command is executed.

## Verification

```powershell
php artisan test
vendor\bin\pint --test
npm run build
python -m compileall services/image-ai/app
```

The remaining work is final production hardening and deployment-specific monitoring, backups, outbound firewalling, and capacity tuning.

## Phase 2 tools and dependencies

Phase 2 adds PDF compression/merge/split, invoice and GST invoice generation, static/dynamic QR codes with aggregate analytics, barcode label sheets, stationery, a Fabric.js poster editor, rules-based product descriptions, ATS resume PDFs, SSRF-protected SEO audits, WhatsApp links/QRs, and authorized bulk watermark processing.

Composer packages are locked to compatible releases: Dompdf for local PDF generation, FPDI for PDF merge/split, chillerlan/php-qrcode for SVG QR codes, and picqer/php-barcode-generator for validated barcodes. Fabric.js powers browser-side poster editing and export.

PDF merge/split works in PHP. Compression additionally requires Ghostscript:

- Windows: install Ghostscript, then set `GHOSTSCRIPT_PATH=C:\Program Files\gs\gs10.xx.x\bin\gswin64c.exe`.
- Debian/Ubuntu: `sudo apt install ghostscript qpdf` and use `GHOSTSCRIPT_PATH=gs`, `QPDF_PATH=qpdf`.
- macOS: `brew install ghostscript qpdf`.

`qpdf` is recommended for administrator-assisted handling of authorized encrypted documents; the app rejects encrypted/unsupported PDFs rather than attempting insecure password handling. Optional local language generation uses an Ollama-compatible endpoint configured with `OLLAMA_URL` and `OLLAMA_MODEL`; the rules-based product-description generator works without it.

Dynamic QR management tokens are displayed once and stored only as SHA-256 hashes. Losing the token means losing analytics access. QR scan IP hashes rotate monthly, and only aggregate day/device/referrer information is exposed.

The SEO audit accepts only HTTP/HTTPS, resolves and pins public DNS addresses, blocks private/reserved IPv4 and IPv6 plus metadata hosts, revalidates redirects, and enforces response, timeout, redirect, and link limits. Production deployments should also enforce outbound firewall rules as defense in depth.

After updating:

```powershell
composer install
npm install
php artisan migrate
npm run build
php artisan test
```
