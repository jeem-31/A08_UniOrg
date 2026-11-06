# UNIORG research submission

**UNIORG: A Configurable University-Wide Student Organization Management Platform with OCR and NLP for Document Processing and Auto-Tagging**

Researchers: **Symon Kiel G. Beato** and **Justine Mark R. Gahi**<br>
Adviser: **Rachiel R. Rivano, PhD**<br>
Laguna State Polytechnic University – Santa Cruz Campus · College of Computer Studies<br>
BS Information Technology, Web and Mobile Application Development · Client: Office of Student Affairs and Services (OSAS)

This repository packages the research presentation, privacy-reviewed research assets and submission documentation. The latest manuscript is the researcher-supplied 445-page PDF in `research/manuscript/`, available through the website's manuscript modal. The current source is confirmed as branch Modification_based_Mia, pinned to 1cbf524; its complete sanitized application snapshot is included. See [the submission checklist](SUBMISSION_CHECKLIST.md).

## Preview the website

No framework, npm install or build step is needed for the research website. Use a recent browser and Python 3:

```powershell
python scripts/preview.py
```

Open **http://127.0.0.1:4173/**. Stop the server with Ctrl+C. The brochure-led redesign uses a fixed research sidebar, an accessible mobile drawer, a six-screen carousel, captioned image viewers and GSAP scroll animation, on plain HTML, CSS and JavaScript. Inter and animation scripts are self-hosted. Reduced motion disables the camera and autoplay. With JavaScript disabled, screenshots still open as direct image links and navigation remains available.

The hosted UNIORG login is https://uniorg-x4g7.onrender.com/login. Research resources and local Markdown guides open in formatted document modals.

The Research manuscript resource opens the supplied PDF in a modal with search, zoom, page controls and original/download links. PDF.js is self-hosted, and the local preview supports HTTP byte ranges so the document can begin rendering without downloading the entire file. The website uses a compressed 18.7 MB copy of the 445-page manuscript; the original is retained locally.

## Repository contents

```text
index.html                  Research presentation
assets/css/                 Responsive styles
assets/js/                  Navigation, carousel, viewers and self-hosted animation
assets/pdfjs/               Self-hosted PDF.js reader and license
assets/images/              Official LSPU seal and derived favicon
assets/fonts/               Self-hosted Inter and font license
design-system/              Generated system and authoritative brand overrides
research/manuscript/        Approved public manuscript derivative/status
research/documentation/     Provenance, findings, setup and publication notes
research/diagrams/          Research diagrams and captions
research/screenshots/       Actual UNIORG screenshots with baked redactions
research/manuals/           Illustrated 60-procedure manual, redacted images and text edition
source-code/                Confirmed complete sanitized application source
scripts/                    Safe preview and submission verification
SUBMISSION_CHECKLIST.md     Verified, pending and missing requirements
.gitignore                  Credentials, dependencies and private working data excluded
.nojekyll                   GitHub Pages static-file handling
```

## Read the evidence

- [Content verification and provenance](research/documentation/CONTENT_SOURCES.md)
- [Reported research results and limitations](research/documentation/RESULTS.md)
- [Documentation](research/documentation/index.html), [diagrams](research/diagrams/index.html), [manual](research/manuals/index.html)
- [Superseded source review](research/documentation/LEGACY_SOURCE_REVIEW.md), explicitly provisional
- [GitHub Pages publication instructions](research/documentation/PUBLISHING.md)

The website highlights the manuscript's 428 respondents and overall acceptance mean of 4.64/5. These have been checked against the latest supplied PDF, not independently reproduced. The full manuscript is displayed as supplied; no invented findings, production database or credentials were added.

## Install and deploy the actual system

The Laravel application is separate from the static website. The confirmed source is [Modification_based_Mia](https://github.com/zorojuro0101/-UniOrg/tree/Modification_based_Mia), commit `1cbf524b77947010041bf3fdbdbbfd7412e06176`. Follow [actual installation, configuration, database, run, build and deployment instructions](source-code/README.md), and review [the sanitization log](research/documentation/SOURCE_PROVENANCE.md).

Frontend production build and all 34 existing JavaScript tests passed. PHP syntax checks passed for 743 files. A process-local Imagick runtime now passes all PHP platform requirements, and the locked Composer install completed in the isolated copy without changing system PHP settings. The selected pure-unit PHP run completed 158 tests: 155 passed and 3 catalog contract tests failed. The supplied no-db suite also includes a RefreshDatabase test and cannot complete without a separate database. See [backend verification](research/documentation/BACKEND_VERIFICATION.md).

The local research-site redesign was browser-tested on 6 November 2026 across all four pages at 320, 390, 768, 1024 and 1440px. See [redesign QA](research/documentation/REDESIGN_QA.md) for interaction, accessibility, fallback, link and screenshot checks. Database migrations, external services and end-to-end application runtime remain unverified.

## Verify the submission

```powershell
python scripts/verify_submission.py
git diff --check
```

The verifier checks structure, relative links, fragment anchors, local downloads, screenshot metadata and forbidden packaged files. It cannot confirm current backend behavior or access to external services. See the checklist for browser verification and unresolved requirements.

## Clean local archive

Run `python scripts/package_submission.py` to create a ZIP in ignored `.local/releases/`. It packages only submission files, excludes private working data/dependencies/Git metadata, and verifies source hashes, ZIP CRCs and archived file hashes. See [archive details](research/documentation/ARCHIVE.md).

## Publish with GitHub Pages

The submission package was uploaded to **main** in [jeem-31/A08_UniOrg](https://github.com/jeem-31/A08_UniOrg) after explicit researcher approval on 2026-11-05. The researchers configured Pages from **main → / (root)**, and the first build was requested during deployment troubleshooting. The [research website is live](https://jeem-31.github.io/A08_UniOrg/): 32 deployed pages/assets/downloads returned HTTP 200 and matched the local bytes. See [deployment verification](research/documentation/PAGES_VERIFICATION.json) and [publication instructions](research/documentation/PUBLISHING.md). The 6 November redesign is local only and has not been pushed or published. The live deployment record above refers to the earlier version. Local browser checks are recorded in [redesign QA](research/documentation/REDESIGN_QA.md); collaborator access and form submission remain pending.

| Submission field | Status |
| --- | --- |
| Repository URL | https://github.com/jeem-31/A08_UniOrg |
| GitHub Pages URL | https://jeem-31.github.io/A08_UniOrg/ — HTTP 200 verified |
| Required GitHub account access | teamccs-git — invitation not sent |
| Designated Google Form | Link not yet provided |
| Latest official source code | Modification_based_Mia at 1cbf524; sanitized copy included |
| Live system | https://uniorg-x4g7.onrender.com/login |

## Privacy and ownership

Original research files remain unchanged. Temporary exports, superseded sources and local tooling live under ignored `.local/`; do not upload that directory. Public derivatives omit private appendices and redact screenshot identities. Check all future source files, embedded documents, runtime data, seeders, images and configuration before packaging; `.gitignore` alone does not remove secrets already present in code or Git history.

Research and system authorship belongs to the named researchers and applicable rights holders. No new blanket license is asserted. The [AgeSense reference](https://somarjez.github.io/agesense-pages/) informed presentation flow; this repository uses its own design, content and assets.
