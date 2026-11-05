# Basic security controls

- Forgot password: 3 code requests per 15 minutes per normalized email, plus 30 requests per hour per client IP.
- Reset password: 5 attempts per 15 minutes per normalized email, plus 30 attempts per hour per client IP. Sending and verifying codes use separate buckets. A blocked request returns JSON with HTTP 429 and a `Retry-After` header.
- Login throttles keep their existing limits. No new throttles are applied to registration, lookups, OCR, or chatbot routes.
- OSAS → Settings → Audit Logs → Login Security shows the newest 50 failed logins and authentication rate-limit hits across the system. Records contain only the event, attempted ID/email, client IP, endpoint, and timestamp. Passwords, codes, and tokens are never recorded.
- Application responses include `nosniff`, `SAMEORIGIN`, and `strict-origin-when-cross-origin` headers, including error and download responses. HTTPS production responses also include HSTS without subdomain or preload directives.
- Login Security can be filtered by activity and a From/To date-time range in Asia/Manila time. Either boundary can be omitted; the selected To minute is included in full. Filters apply before the latest-50 limit, survive Refresh, and can be cleared with Reset. Invalid filters show an inline error without returning unfiltered activity.
- EDuShare uploads allow 20 MB per file. Student batches allow at most 10 files and 20 MB combined. Admin uploads and replacement files use the same per-file limit. Limits are enforced server-side and displayed on the upload forms. Existing file-type support is unchanged.

## Railway deployment

1. Apply the new `authentication_events` migration with `php artisan migrate --force`. The current startup command already runs migrations; verify that it succeeds on deployment.
2. Set Railway variables `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<your-public-domain>`, and `SESSION_SECURE_COOKIE=true`. Secure cookies default to enabled in production if the last variable is unset; local HTTP defaults to disabled.
3. Clear any previously cached configuration with `php artisan config:clear` as part of the release. Do not run a database reset or a fresh migration.
4. Verify a normal login and password reset, a throttled request, the OSAS Login Security panel, and EDuShare uploads on the public HTTPS domain.

The 20 MB combined upload limit fits the current Docker settings of `upload_max_filesize=20M` and `post_max_size=22M`. Login activity is stored in the database so it survives Railway container redeploys.

## Cloudflare Turnstile

Turnstile protects both password-login endpoints, the start of fingerprint login, forgot-password code requests, and password resets when enabled. The existing rate limits run before verification on password login and recovery routes. Rejected bot checks do not count as incorrect-password events. Each submission is checked with Cloudflare Siteverify, including the expected action and an allowed hostname. Missing, invalid, expired, or previously used tokens are rejected. If Cloudflare is unavailable or configuration is incomplete, protected requests are blocked with a retry message.

Configure these variables on the Render or Railway service before deploying/enabling:

```dotenv
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=<public site key from the widget>
TURNSTILE_SECRET_KEY=<private secret key from the widget>
TURNSTILE_HOSTNAMES=uniorg-x4g7.onrender.com
```

The same hostname must be allowed in the Cloudflare widget. Use hostnames only, without a scheme or path; separate multiple hosts with commas. If `TURNSTILE_HOSTNAMES` is blank, the hostname in `APP_URL` is used. Only the site key is rendered in HTML; keep the secret in service environment variables and never commit it. Rotate a secret shared in chat before production use, and update the service variable with the replacement.

Clear cached configuration with `php artisan config:clear` and redeploy. Verify login (including fingerprint on a supported device), both recovery steps, incorrect-password retries, and expired challenges in a browser on the allowed domain. The widgets refresh after submissions because tokens are single-use and expire after five minutes. Local development defaults to `TURNSTILE_ENABLED=false`; use Cloudflare's documented test keys for local verification instead of enabling a production widget on an unlisted localhost. Automated tests use mocked verification responses and never contact Cloudflare.
