# Source provenance and sanitization

Official source reference, corrected by the researchers on 2026-11-06: [zorojuro0101/-UniOrg](https://github.com/zorojuro0101/-UniOrg), using **main**.

The existing packaged snapshot was imported at commit **1cbf524b77947010041bf3fdbdbbfd7412e06176**, dated 2026-10-21. That revision records the imported files; it is not a claim that the snapshot matches the current main tip. This reference correction changes documentation only.

The complete application is copied into ../../source-code/: backend, frontend, views, assets, routes, migrations, factories, tests, configuration, build/deployment definitions and both manifests/lockfiles. SOURCE_MANIFEST.json records upstream and packaged SHA-256 hashes for each copied upstream file.

## Exclusions

- Nested Git and agent/IDE metadata; actual environment and credential files.
- SOCCSyncs/ database dump and uploaded CORs, medical/consent forms, candidate/event/payment materials.
- database.sqlite, runtime storage/log/cache/session/testing files and generated public/build.
- node_modules, vendor, recordings, video production projects and compiled media, which are ancillary to the application.
- TEST-ACCOUNTS.md and four old documents with private account records/test credentials: DATABASE_BACKUP.md, APPLY_AUTOFILL_SOP.md, RENEW_AUTOFILL_SOP.md and TESTING-RESULTS.md.

## Sanitized changes

| File | Change |
| --- | --- |
| resources/js/env-config.js | Remove deployment Pusher key, Supabase URL and public anon JWT defaults. Supply VITE_* configuration before building. |
| database/seeders/DatabaseSeeder.php | Fixed sample passwords replaced with random values. |
| database/seeders/OsasAdminSeeder.php | Fixed password replaced with random value; password output removed. |
| app/Console/Commands/CreateOsasAdmin.php | Require a supplied password of at least 12 characters; remove fallback and password display. |
| app/Console/Commands/TestEmailNotifications.php | Require explicit recipient; generated test password and demo requester replace fixed/private defaults. |
| app/Console/Commands/TestSubmissionCommand.php | Private default email replaced with a non-deliverable example address. |
| resources/views/welcome.blade.php | Private developer contact replaced with a non-deliverable example pending an official configured contact. |
| docs/AI-SYSTEM-SUMMARY.txt | Remove Gemini/GCP API key and deployment-specific Google project/credential-path assignments; values must be supplied privately. |

A credential-free .env.example and installation README are added. Synthetic unit-test input values are not deployment credentials. No original checkout or upstream repository was changed. This is a documented sanitized snapshot, not a byte-identical upstream archive.

The first push attempt was rejected by GitHub secret protection because the upstream text documentation contained a newer Google API-key format. The local scan was expanded to all text files and additional key formats. The key was removed from the unpublished commit before retrying; push protection was not bypassed. The upstream credential owner should revoke/rotate the exposed key separately.

Composer manifest validation passes with existing version-constraint warnings. PHP syntax checks passed for 743 non-Blade files. The default host lacks Imagick, but a process-local official DLL enabled platform checks and locked Composer installation in the isolated copy. A selected 158-test pure-unit run had 155 passes and 3 upstream catalog-contract failures. The supplied no-db suite includes a database lifecycle test and remains incomplete; database migrations and cloud API execution are unverified. Frontend build/test results are in VERIFICATION.md. No production database or service was contacted.
