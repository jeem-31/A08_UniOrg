# Legacy source review — provisional only

The user confirmed this code is superseded. This is a read-only review of public `zorojuro0101/-UniOrg`, main revision `93062eb604baece7dad8d714b9cb329cfa3e6e75`, retrieved 2026-11-05. The reviewed checkout is excluded from the submission. These steps have not been run against a database and are not verified for the pending latest source.

## Evidence from the reviewed code

| Area | File | Observation |
| --- | --- | --- |
| Backend | composer.json, composer.lock | PHP ^8.4, Laravel ^11, Composer dependency lockfile present |
| Frontend | package.json, package-lock.json | Vite; npm dev, build and test:js scripts |
| Runtime | Dockerfile | PHP 8.4, Node 22 build stage; PostgreSQL PDO, GD, ZIP, Imagick, Tesseract, Ghostscript |
| Database | config/database.php, database/migrations | PostgreSQL/Supabase configuration; also MySQL and SQLite connections |
| Files | config/filesystems.php | local/public and S3-compatible disks (Cloudflare R2 configuration) |
| Background work | Dockerfile, config/queue.php | document-ai, emails and default queues, Redis worker; scheduler |
| Broadcast | config/broadcasting.php | Pusher and Reverb configuration |
| Frontend entry points | vite.config.js | app CSS/JS, document library CSS/JS, OSAS notifications |

## Provisional isolated development sequence

Only apply after verifying the same structure in the confirmed current source. Use an empty local development database, not production.

```powershell
cd source-code
composer install
npm ci
Copy-Item .env.example .env
php artisan key:generate
# Configure a fresh PostgreSQL database and required services in your private .env.
php artisan migrate
php artisan storage:link
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

For Vite development, run `npm run dev` in a separate terminal. If configured for Redis queues, run `php artisan queue:work redis --queue=document-ai,emails,default --tries=3 --timeout=300` in another terminal. Run `php artisan schedule:work` where scheduled jobs are required.

Do not run the default legacy `db:seed` against production: it includes fixed sample account passwords and demo content. An approved bootstrap process and credential-free development seeder must be established for the latest source.

## Configuration review still required

- Database: DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD, or DATABASE_URL; use a dedicated development instance. Never package an actual database or production dump.
- Storage: FILESYSTEM_DISK and AWS_* variables used by the S3-compatible disk; supply separate private R2 credentials and bucket configuration outside Git.
- OCR and AI: inspect the latest service classes and configuration for enabled providers, required credentials, local Tesseract path and fallback behavior. Do not infer the latest provider from the manuscript's mixed Groq/Gemini references.
- Mail: reviewed config/services.php uses Google OAuth/Gmail variables. No real OAuth token or client secret belongs in source.
- Queue/cache and realtime: reconcile Redis, Pusher/Reverb and frontend public configuration before building. Never embed backend secrets in VITE_* variables.
- PDF/image processing: match actual PHP extension requirements and external tools. Validate on the deployment host rather than ignoring platform requirements.

## Backend deployment

GitHub Pages serves only the research website. Laravel needs a PHP-capable host, persistent database, storage, configured background worker and scheduler. The reviewed Dockerfile is evidence of the old deployment approach, not a verified current deployment recipe. Review host configuration, perform a clean locked dependency install/build, apply migrations deliberately, set APP_DEBUG=false, configure HTTPS and validate login, uploads, queues, email and access boundaries in a staging environment before production.

Final installation, sanitized `.env.example`, database bootstrap, runtime and deployment documentation remains pending the latest source. Nothing has been deployed or connected to production by this task.
