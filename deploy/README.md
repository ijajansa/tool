# Production deployment

## Local Windows development (XAMPP)

Prerequisites: XAMPP PHP 8.2+, Composer, Node.js 22+, Python 3.11+, MySQL, Ghostscript, and qpdf. From PowerShell in the repository:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
npm install
php artisan migrate
python -m venv services\image-ai\.venv
services\image-ai\.venv\Scripts\python.exe -m pip install -r services\image-ai\requirements.txt
```

Set the MySQL values in `.env`. For Windows executable paths, use forward slashes and quote the whole value, for example `GHOSTSCRIPT_PATH="C:/Program Files/gs/gs10.05.1/bin/gswin64c.exe"`; a single backslash in a dotenv quoted value is parsed as an escape. Set `QPDF_PATH` the same way.

Run these in separate PowerShell terminals:

```powershell
php artisan serve
php artisan queue:work --queue=default --tries=1 --timeout=240
php artisan schedule:work
npm run dev
services\image-ai\.venv\Scripts\python.exe -m uvicorn app.main:app --app-dir services\image-ai --host 127.0.0.1 --port 8090
```

Open `http://127.0.0.1:8000`. Laravel Pail requires the Unix-only `pcntl` extension, so the default `composer run dev` script can stop all processes on native Windows; the separate commands above avoid that issue. Before sharing a production build, run `php artisan test`, `vendor\bin\pint --test`, and `npm run build`.

To create the first local or production administrator, run `php artisan admin:create` and enter the email and strong password interactively.

## Ubuntu VPS production

These examples assume Ubuntu 24.04, PHP 8.3, MySQL 8, Nginx, `/var/www/tools/releases/<timestamp>` releases, a `/var/www/tools/current` symlink, and persistent `/var/www/tools/shared/.env` plus storage. Replace domains and paths before use.

## Install the host

```bash
sudo apt update
sudo apt install -y nginx mysql-server supervisor git unzip curl ghostscript qpdf python3-venv python3-pip php8.3-fpm php8.3-cli php8.3-mysql php8.3-gd php8.3-curl php8.3-xml php8.3-mbstring php8.3-zip php8.3-bcmath composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
sudo mkdir -p /var/www/tools/{releases,shared/storage,shared/backups}
sudo mkdir -p /var/www/tools/shared/storage/{app/private,framework/cache,framework/sessions,framework/views,logs} /var/www/tools/shared/models
sudo chown -R www-data:www-data /var/www/tools
```

Create MySQL credentials interactively; never put the password in shell history:

```bash
sudo mysql
CREATE DATABASE tools CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tools_app'@'127.0.0.1' IDENTIFIED BY 'replace-interactively';
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP ON tools.* TO 'tools_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

## First release

```bash
export RELEASE=/var/www/tools/releases/$(date +%Y%m%d%H%M%S)
sudo -u www-data git clone --depth=1 YOUR_REPOSITORY "$RELEASE"
cd "$RELEASE"
sudo -u www-data composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
sudo -u www-data npm ci
sudo -u www-data npm run build
sudo -u www-data ln -sfn /var/www/tools/shared/.env .env
sudo -u www-data rm -rf storage
sudo -u www-data ln -s /var/www/tools/shared/storage storage
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo ln -sfn "$RELEASE" /var/www/tools/current
sudo chown -R www-data:www-data /var/www/tools/shared "$RELEASE/bootstrap/cache"
sudo chmod -R ug+rwX /var/www/tools/shared/storage "$RELEASE/bootstrap/cache"
```

Copy `.env.production.example` to `/var/www/tools/shared/.env`, generate `APP_KEY` with `php artisan key:generate --show`, then fill secrets. Keep `APP_DEBUG=false`, use HTTPS, encrypted secure sessions, and localhost-only AI/Ollama endpoints.

CAPTCHA is off by default. To enable Cloudflare Turnstile, set `CAPTCHA_ENABLED=true`, its site key and secret, then rebuild the configuration cache. Analytics/advertising are also off by default; `SITE_ANALYTICS_SCRIPT_URL` is loaded only after the visitor accepts optional cookies. Review your provider domains and privacy wording before enabling either feature.

The application CSP restricts scripts to same-origin/nonced sources (plus explicitly enabled provider origins). `style-src` retains `'unsafe-inline'` because the browser poster editor/Fabric runtime applies element styles dynamically; script execution does not receive that exception. Re-test the poster editor before tightening this directive.

## Services and HTTPS

```bash
sudo cp deploy/nginx/tools.conf /etc/nginx/sites-available/tools
sudo ln -s /etc/nginx/sites-available/tools /etc/nginx/sites-enabled/tools
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tools.example.com
sudo cp deploy/supervisor/tools-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo cp deploy/systemd/tools-scheduler.service /etc/systemd/system/
sudo cp deploy/systemd/image-ai.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now tools-scheduler image-ai
sudo cp deploy/logrotate/tools /etc/logrotate.d/tools
```

AI setup:

```bash
sudo -u www-data python3 -m venv /var/www/tools/shared/image-ai-venv
sudo -u www-data /var/www/tools/shared/image-ai-venv/bin/pip install --upgrade pip
sudo -u www-data /var/www/tools/shared/image-ai-venv/bin/pip install -r /var/www/tools/current/services/image-ai/requirements.txt
sudo install -o root -g www-data -m 640 services/image-ai/.env.example /var/www/tools/shared/image-ai.env
```

The first `rembg` operation downloads its open model. Pre-warm it during maintenance if production workers have no internet access.

## Rollback-safe update

1. Back up MySQL and shared `.env`/storage before schema changes.
2. Build and test a new timestamped release without changing `current`.
3. Run `php artisan down --render="errors::503" --retry=60` only for migrations that require it.
4. Run backward-compatible migrations, caches, then `php artisan queue:restart`.
5. Atomically repoint `current`, reload PHP-FPM, and run `/health` plus a signed-download smoke test.
6. Roll back by repointing `current` to the previous release. Restore the database only when a migration is not backward-compatible; never run destructive rollback blindly.

Backup example:

```bash
mysqldump --single-transaction --routines --triggers tools | gzip > /var/www/tools/shared/backups/tools-$(date +%F-%H%M).sql.gz
tar -C /var/www/tools/shared -czf /var/www/tools/shared/backups/storage-$(date +%F-%H%M).tgz storage .env
```

Do not retain temporary customer task directories in long-term backups unless legally required. Restrict backup access and test restores.

## Launch checklist

- DNS, HTTPS renewal, `APP_URL`, trusted proxy behavior, secure cookies, and `APP_DEBUG=false` verified.
- Database backup/restore tested; migrations reviewed for backward compatibility.
- Worker, scheduler, AI service, health monitoring, disk alerts, and log rotation active.
- Nginx/PHP upload and timeout values align with application limits.
- Outbound firewall blocks private/metadata networks as defense in depth for SEO auditing.
- No default admin exists; run `php artisan admin:create` over a secured shell.
- Ghostscript/qpdf paths tested with non-sensitive sample PDFs.
- Privacy/legal templates reviewed by the operator and qualified counsel where appropriate.
- Analytics/ads remain disabled until configured with consent and an updated policy.
- All tests, production build, dependency audits, and representative downloads pass.
