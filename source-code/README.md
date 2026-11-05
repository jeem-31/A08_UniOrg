# UNIORG application source

Researcher-confirmed source: [zorojuro0101/-UniOrg, Modification_based_Mia](https://github.com/zorojuro0101/-UniOrg/tree/Modification_based_Mia), pinned to **1cbf524b77947010041bf3fdbdbbfd7412e06176** (2026-10-21). Complete application source, migrations, tests, dependency manifests and lockfiles are included, with documented privacy/runtime exclusions. See [provenance](../research/documentation/SOURCE_PROVENANCE.md).

## Requirements

- PHP **8.4+ within 8.x**, Composer 2, and extensions required by composer.lock. The default host PHP 8.4.7 does not enable Imagick. An isolated process-local Imagick 3.8.1 runtime was used to pass platform checks and complete locked dependency installation. See [backend verification](../research/documentation/BACKEND_VERIFICATION.md) for scope and unresolved test failures.
- Node.js 22+ and npm. The supplied Dockerfile uses Node 22. See [verification](../research/documentation/VERIFICATION.md) for the isolated frontend checks.
- PostgreSQL and PHP pdo_pgsql for the supplied PostgreSQL/Supabase migration path. A fresh migration run is still required in an isolated development database.
- Tesseract OCR with English data, ImageMagick with PHP Imagick, and Ghostscript for document conversion. Configure tools on PATH.
- Redis for background jobs; separate development credentials for selected cloud integrations.

## Install

Run commands from this source-code directory. On Windows, npm.cmd avoids the PowerShell npm.ps1 wrapper if execution policy blocks it; no system policy change is needed.

```powershell
composer check-platform-reqs --lock
composer install
npm.cmd ci
Copy-Item .env.example .env
php artisan key:generate
```

Use install rather than update to retain lockfiles. Enable missing required PHP extensions before installation; do not ignore platform requirements for deployment. The sanitized .env.example defaults to local file sessions/cache, synchronous jobs, log mail and log broadcasting. These defaults do not reproduce all deployed realtime/OCR behavior.

## Configure

Edit your private .env. Never commit it.

| Function | Actual variables / code locations |
| --- | --- |
| Application | APP_NAME, APP_ENV, APP_KEY, APP_DEBUG, APP_URL |
| PostgreSQL | DB_CONNECTION=pgsql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD; optional DATABASE_URL in config/database.php |
| Cache/session | CACHE_DRIVER, SESSION_DRIVER, SESSION_LIFETIME, SESSION_SECURE_COOKIE |
| Queue | QUEUE_CONNECTION=redis; REDIS_CLIENT=predis, REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DB |
| Files | FILESYSTEM_DISK=public for local; s3 for R2 with AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION=auto, AWS_BUCKET, AWS_ENDPOINT, AWS_URL, AWS_USE_PATH_STYLE_ENDPOINT |
| Google OCR/NLP | GOOGLE_CLOUD_VISION_ENABLED, GOOGLE_CLOUD_NLP_ENABLED; private credential JSON at storage/app/google-cloud/credentials.json |
| AI | GROQ_API_KEY or GROQ_API_KEYS for GroqService; GOOGLE_GEMINI_API_KEY for GeminiService |
| Mail | MAIL_MAILER, MAIL_FROM_ADDRESS, MAIL_FROM_NAME, GMAIL_SENDER; GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REFRESH_TOKEN for the Gmail path |
| Turnstile | TURNSTILE_ENABLED, TURNSTILE_SITE_KEY, TURNSTILE_SECRET_KEY, TURNSTILE_HOSTNAMES; middleware verifies action and hostname when enabled |
| Realtime | BROADCAST_CONNECTION=pusher; PUSHER_APP_ID, PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_CLUSTER; Reverb configuration is also present |
| Browser build | VITE_PUSHER_APP_KEY, VITE_PUSHER_APP_CLUSTER, VITE_SUPABASE_URL, VITE_SUPABASE_ANON_KEY; rebuild when changed |

Only public client configuration belongs in VITE_* variables. Server keys and OAuth tokens stay outside Git. Do not place private uploads on GitHub Pages. This snapshot includes Groq, Gemini and Google Cloud services; verify which paths are enabled with sample development uploads. Upstream Google service and S3 configuration includes TLS-verification exceptions; these are preserved and need review/testing before production.

## Database setup

Create a dedicated PostgreSQL database/user (uniorg_local matches the example), with permission to create application tables. Set its credentials in .env, then:

```powershell
php artisan migrate
php artisan storage:link
php artisan osas:create-admin
```

The admin command prompts for email, a private password of at least 12 characters, names and account ID. The sanitized command has no fixed fallback and does not print the password. Configure organizations, roles and operations through approved OSAS workflows and the manual.

OrganizationSeeder and DatabaseSeeder contain demonstration records. They are optional development fixtures, not a production bootstrap plan. Fixed seed passwords were replaced with random values; use the interactive command for a known login. Do not run migrate:fresh, raw table-rebuild SQL or demonstration seeding on existing databases. No production dump is packaged. Clean-database migration success has not been verified here.

## Run and build

```powershell
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8000
```

For frontend development, run npm.cmd run dev separately. vite.config.js defines app, document library and OSAS notification entry points. public/build is regenerated and intentionally excluded from source.

For asynchronous processing, configure Redis and run separately:

```powershell
php artisan queue:work redis --queue=document-ai,emails,default --tries=3 --timeout=300
php artisan schedule:work
```

The upstream scripts/start.bat is retained, but opens command windows. The explicit commands above keep the web listener on loopback.

## Checks

```powershell
composer validate --no-check-publish
composer check-platform-reqs --lock
npm.cmd run test:js
php artisan test --configuration=phpunit.no-db.xml
```

PHP tests require installed dependencies/extensions. Use BROADCAST_CONNECTION=log for isolated tests without Pusher credentials. The supplied no-db configuration has an unreachable database guard but still includes at least one RefreshDatabase test, so it does not complete without database setup. A separate pure-unit selection produced 155 passed and 3 unresolved catalog-contract failures out of 158 tests. See the backend verification record before treating any suite as passed. Database suites require a disposable test database; these checks do not reproduce historical respondent evaluations.

## Backend deployment

GitHub Pages hosts the research website only. Laravel needs a PHP host/container, PostgreSQL, file/object storage, worker, scheduler, mail and configured cloud services.

1. Provision staging with the required extensions/tools. Run composer install --no-dev --optimize-autoloader, npm.cmd ci and npm.cmd run build with the intended public frontend variables.
2. Configure private secrets, APP_ENV=production, APP_DEBUG=false, a unique APP_KEY, HTTPS APP_URL and secure cookies. Point the document root at public/. Grant write access to storage and bootstrap/cache.
3. Validate migrations in staging, then deliberately apply php artisan migrate --force to the approved deployment after its operator backs up existing data.
4. Supervise the Redis worker and Laravel scheduler; configure Pusher/Reverb and storage for that environment.
5. Verify login/Turnstile, role restrictions, uploads/OCR/tagging, approvals, payments, voting, email and downloads with non-private test data.

Dockerfile and Nixpacks files are retained for review. Their upstream startup commands suppress migration failure with `|| true` and use a development server; review these before production. No backend service was deployed by this task.
