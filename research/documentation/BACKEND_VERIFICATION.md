# Isolated backend verification

Date: 2026-11-05. Application revision: `1cbf524b77947010041bf3fdbdbbfd7412e06176`.

The installed PHP 8.4.7 CLI does not enable Imagick. Verification now uses a separate, ignored runtime directory and process-local environment variables. No system PHP configuration, global PATH, source lockfile, production environment or database was changed.

## Runtime and dependency installation

- Downloaded the [official PHP Windows Imagick 3.8.1 package for PHP 8.4 TS VS17 x64](https://downloads.php.net/~windows/pecl/releases/imagick/3.8.1/php_imagick-3.8.1-8.4-ts-vs17-x64.zip), matching the installed PHP extension build.
- Downloaded ZIP SHA-256: `eec88e274fe51f98a030a10d65982f1d0cae66607de53a255e5aca41313eadbb`.
- Extracted into ignored `.local/runtime/imagick/`. A separate `.local/runtime/php-ini/imagick.ini` loads the extension. Only the verification process receives PHP_INI_SCAN_DIR, the DLL search PATH and MAGICK_CONFIGURE_PATH.
- Extension loaded successfully: Imagick 3.8.1, ImageMagick 7.1.1-46. The PDF coder is available.
- `composer check-platform-reqs --lock` passes with this process-local runtime. No platform requirement was ignored.
- Locked `composer install --prefer-dist --no-scripts` completed in `.local/build-check`, installing 163 packages and generating its autoloader. Composer scripts were deliberately not executed during dependency installation.

The [PHP Imagick installation documentation](https://www.php.net/manual/en/imagick.installation.php) describes the Windows DLL package and its dependency/configuration lookup. The extension binaries and vendor folder are local verification tools and are excluded from the submission ZIP and Git files.

## Test scope

The supplied `phpunit.no-db.xml` sets APP_ENV=testing, uses array mail/cache and synchronous jobs, and forces an unreachable PostgreSQL target. Neither .env nor .env.testing exists in the verification copy. No production credentials are loaded. The first attempt stopped during bootstrap because its default broadcaster requested Pusher keys. Setting BROADCAST_CONNECTION=log, matching the sanitized local configuration, resolved that bootstrap issue without adding credentials.

The next attempt stopped at AccountDirectoryQueryTest because that class uses RefreshDatabase. Despite its filename, the supplied no-db configuration includes database-dependent tests. The unreachable database guard worked; it was not removed or redirected to a real database.

A temporary configuration then selected the 18 existing unit-test files that directly import PHPUnit Framework TestCase. This completed **158 tests and 660 assertions: 155 passed, 3 failed, 0 errors**. See BACKEND_UNIT_RESULTS.json. The tested submitted application/test files were not changed to make this run pass.

## Three unresolved contract failures

All three failures are in tests/Unit/SupportedDocumentCatalogTest.php:

1. The exact supported-field list omits renewal_form and other_files, which the implementation includes.
2. A decode test expects renewal_form to be ignored, but the implementation recognizes it, returning two sources instead of one.
3. A direct-lookup test expects renewal_form to throw an unsupported-field exception, but it is supported.

Both the catalog and its test are byte-identical to the confirmed upstream revision. ApplyOrg also declares renewal_form and other_files. These observations identify a contract discrepancy for researcher/developer review; the submission does not silently remove document support or rewrite assertions to conceal failing tests. Full database and integration tests remain pending.

A successful dependency install or available PDF coder does not establish OCR accuracy, database compatibility, complete application behavior, or production readiness. Fresh PostgreSQL migrations, service integration and end-to-end checks remain separate work.
