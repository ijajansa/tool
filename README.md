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

Phase 2 tools are intentionally not included.
