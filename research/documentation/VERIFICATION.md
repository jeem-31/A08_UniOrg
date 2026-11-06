# Local verification record

Date: 2026-11-05, Asia/Manila. Local work only; no push, publication, collaborator invitation or form submission.

## Passed checks

- Latest manuscript text retrieved; complete PDF export validated as 445 pages. Identity/project details checked in title/approval/context sections. Findings checked in Table 4.54 and the rendered Table 4.62 (PDF export page 260).
- Six actual screenshots reviewed visually; opaque identity/requester masks are baked into lossless WebP. Five diagram extracts reviewed visually; initially clipped bounds corrected before delivery.
- Manual extraction verified as 60 procedure figure captions (125–184); subsequent survey/private appendix material excluded.
- Website JavaScript parses with node --check.
- Local HTTP 200: main website, documentation, diagrams, manual, public manuscript text, public manual text. Hidden .local and .git requests return 404.
- Locked npm install, frontend production build (Vite 6.4.2; 108 modules), and all **34 tests in 4 existing JS test files** pass in ignored .local/build-check. No node_modules or generated build files are included in the submitted source snapshot.
- PHP 8.4.7 syntax checks pass for **743 non-Blade PHP files**. Sanitized command/seeder PHP files are included in that check; later email-only changes are checked again separately.
- composer validate --no-check-publish passes with existing warnings for an exact Flysystem constraint and unbounded Livewire/Resend constraints. Lockfiles preserved.
- Process-local Imagick 3.8.1 loads, exposes the PDF coder and passes Composer lockfile platform checks. Locked Composer dependency install completed with scripts disabled. No system PHP configuration or lockfile was changed.
- Selected pure-unit PHP run completed 158 tests/660 assertions, with 155 passes and 3 failures. This is a completed test attempt, not a passing suite; see the limitations below.
- Official repository reference: [https://github.com/zorojuro0101/-UniOrg](https://github.com/zorojuro0101/-UniOrg) (`main`), corrected by the researchers on 2026-11-06. The packaged snapshot revision, hashes and exclusions remain recorded in SOURCE_MANIFEST.json.

The offline verifier output is recorded in LOCAL_CHECK_RESULTS.json after the final file pass. Static path and syntax checks do not constitute visual or interactive browser testing.

## Pending / limited

The illustrated manual now includes all 60 screen images. All 264 original procedure steps remain unchanged. Image provenance, dimensions, hashes, metadata and 76 permanent mask regions were checked. Every screen was visually reviewed, with final corrections reviewed again; a supplementary OCR pass over all 60 final images found no remaining matches for the reviewed identity/email/ID/phone patterns. This OCR pass is supplemental, not a guarantee that automated scanning detects every possible personal detail. See MANUAL_VERIFICATION.json and ../manuals/images-manifest.json. Browser preview was attempted again for this update, but Computer Use stopped because it could not determine the current browser URL on Windows confidently; layout and interactive viewer QA remain pending.

- Initial desktop hero render observed at a 1536x960 Chrome window; subtitle contrast issue identified and corrected, along with malformed link arrows. Post-fix visual review, mobile layout, menu behavior, dialog focus/Escape/arrows and browser download UX: **not completed**. After the initial user Escape stop, browser review was resumed on request in an isolated Chrome window. Computer Use then stopped because it could not determine the current browser URL on Windows with sufficient confidence to enforce policy. No alternate browser-control method was used to bypass that stop.
- The default PHP does not enable Imagick. A process-local official Imagick runtime now passes all platform requirements, and locked Composer installation completed in the ignored verification copy. The 18-file pure-unit run produced 155 passes and 3 catalog-contract failures from 158 tests; the supplied no-db suite stopped at a RefreshDatabase test against the unreachable guard. No complete database/integration/runtime suite is claimed. Details: BACKEND_VERIFICATION.md and BACKEND_UNIT_RESULTS.json. PostgreSQL migration/bootstrap, cloud APIs and end-to-end operation remain unverified; Docker has no running daemon.
- No OCR accuracy/time-saving/load/security guarantee independently measured. Research results are manuscript-reported controlled-test and acceptance findings.
- Full paginated/signed manuscript remains outside the public package because appendices contain private material. The public text derivative and original Google Doc link are provided. Restricted academic submission of an approved PDF remains for researcher review.
- Official live system, demo video and Google Form links are not confirmed. The submission repository and research website URLs are now verified; deploying the static research website does not deploy the Laravel system.

## GitHub Pages deployment

The researcher enabled main/root publishing. An explicit build request resolved the missing initial build. The first pages build and deployment run succeeded: https://github.com/jeem-31/A08_UniOrg/actions/runs/42046589407. The live website is https://jeem-31.github.io/A08_UniOrg/. All 32 checked pages, styles/scripts, images and public text downloads returned HTTP 200 and matched local bytes. Full results are in PAGES_VERIFICATION.json. This verifies HTTP delivery, not desktop/mobile rendering or interactive behavior.

## Local release archive

See ARCHIVE.md. The packager excludes private working folders and dependencies, validates the sanitized source manifest, and checks CRCs plus SHA-256 hashes of every archived file. The archive remains local and does not imply visual or runtime QA is complete.

## Manual review to finish

1. Run python scripts/preview.py; open http://127.0.0.1:4173/.
2. Review at desktop width, 390px mobile and 320px narrow mobile; ensure readable text and no horizontal overflow.
3. Follow each navigation/resource link and open both text downloads.
4. Open gallery screenshots, cycle with arrow buttons/keys, close with Escape, confirm focus returns to the opened screenshot.
5. Verify the mobile menu opens/closes and section navigation closes it. Check keyboard focus and reduced-motion behavior.
6. Review source sanitization and remaining manuscript issues before approving publication.

## Palette update

At the researchers' request, the research website now uses the confirmed UniOrg login/OSAS blue/navy/gold palette. Shared CSS, main theme metadata and the SVG mark were updated. Six sampled text/background contrast combinations passed their applicable minimums; local link checks still pass. See DESIGN_PALETTE.md and PALETTE_CHECKS.json. This static verification does not replace pending browser visual review.
