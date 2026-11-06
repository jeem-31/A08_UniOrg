# UNIORG submission checklist

Local preparation started 2026-11-05 (Asia/Manila). The researchers supplied jeem-31/A08_UniOrg as the submission repository and configured GitHub Pages from main/root. The deployed site is verified available. Collaborator invitation and form submission remain pending separate instructions.

## Verified

- [x] Original research AGENTS.md, notes/memory.md and recent session-log.md read.
- [x] Existing workspace inspected; no existing project work overwritten.
- [x] Local Git repository initialized with main and origin configured to the researcher-supplied submission repository.
- [x] Researcher-supplied submission repository is accessible; no existing remote branches were present before upload preparation.
- [x] Required folders, index.html, CSS/JS/assets, README.md, .gitignore and .nojekyll created.
- [x] Reference AgeSense website and repository README inspected; original UNIORG design/content created.
- [x] Latest manuscript supplied by researchers through Google Docs and downloaded read-only.
- [x] Title, researchers, adviser, campus, college, program and OSAS client checked in the manuscript.
- [x] Problems, general/specific objectives, methodology and all ten feature areas checked against the latest manuscript.
- [x] Reported results checked against Tables 4.54 and 4.62; findings are labeled as reported, controlled-condition results.
- [x] Six actual feature screenshots selected; identity/requester names masked in delivered pixels.
- [x] Five source diagram extracts and illustrated public manual with all 60 procedures and captioned screenshots included; separate text download preserved.
- [x] Manual images aligned by actual screen content; three original research screenshots supplement the appendix, and two related screen states are explicitly identified. Source positions, hashes and baked redactions recorded in research/manuals/images-manifest.json.
- [x] Public manuscript text includes Chapters I–V and literature cited; private tester profiles, embedded images and appendices omitted.
- [x] Misidentified TUGON manual excluded; superseded FINAL_RC manuscript excluded.
- [x] Original research files left unchanged; downloaded originals/private working files ignored under .local/.
- [x] Main website includes manuscript, documentation, manual and diagrams links; unavailable links are explicitly labeled.
- [x] Latest source confirmed as Modification_based_Mia, commit 1cbf524b77947010041bf3fdbdbbfd7412e06176; complete sanitized application source included.
- [x] Both dependency manifests and lockfiles preserved; fresh credential-free .env.example included.
- [x] Source privacy scan/exclusions and eight sanitization changes documented with per-file hashes; Google key removed from upstream text documentation after push protection flagged it.
- [x] Frontend locked install/build passed; 34 existing JavaScript tests passed.
- [x] PHP syntax checks passed for 743 files; Composer manifest validated with upstream constraint warnings.
- [x] Static website verification passed: four HTML pages; local links, anchors and manual images checked by scripts/verify_submission.py. Latest counts are in research/documentation/LOCAL_CHECK_RESULTS.json.
- [x] Local HTTP pages/downloads succeed; hidden working/Git paths are not served.
- [x] Official process-local Imagick runtime loaded; lockfile platform checks and isolated Composer dependency installation passed without changing system PHP settings.
- [x] Existing pure-unit PHP test selection executed: 158 tests, 155 passed and 3 unresolved failures documented. This does not mark the PHP suite as passed.
- [x] Initial desktop hero observed; subtitle contrast and malformed link arrows corrected in files. Post-fix review remains pending.
- [x] Clean local ZIP packaging script added with source-hash, ZIP CRC and per-file hash validation.

- [x] Website palette aligned with confirmed UniOrg login/OSAS blue, navy, gold and off-white colors; six sampled contrast checks passed.

## Pending verification

- [ ] Upstream credential owner revokes/rotates the Google API key found in original source documentation; the submission copy removes it.
- [ ] Resolve/review the three upstream SupportedDocumentCatalog contract failures; provision a disposable PostgreSQL database for migration, database-test and end-to-end verification.
- [ ] Desktop browser visual review of hero, text, diagrams, gallery and results.
- [ ] Mobile review at approximately 390px and narrow 320px widths; check no horizontal overflow.
- [ ] Menu open/close, in-page navigation, focus visibility, keyboard screenshot viewer, Escape close and arrow navigation in a browser.
- [ ] Open every website resource and downloadable manuscript/manual file in a browser.
- [ ] Review the manuscript's unresolved sampling/construct/provider wording in CONTENT_SOURCES.md.
- [ ] Original paginated/signed manuscript PDF for restricted academic submission; local public package currently contains a privacy-safe text derivative and original Google Doc link.
- [ ] Final public-content approval by the researchers after all remaining checks.

Browser review resumed after the user requested continuation. Computer Use then stopped because it could not determine the current browser URL on Windows with sufficient confidence to enforce policy. Visual/interactivity checks remain pending. File/HTTP checks in research/documentation/VERIFICATION.md are not visual browser validation.

## Missing information

| Requirement | Current value / status |
| --- | --- |
| Confirmed system source | https://github.com/zorojuro0101/-UniOrg/tree/Modification_based_Mia — commit 1cbf524b77947010041bf3fdbdbbfd7412e06176 |
| Submission repository owner/name | jeem-31/A08_UniOrg |
| Repository URL | https://github.com/jeem-31/A08_UniOrg |
| GitHub Pages URL | https://jeem-31.github.io/A08_UniOrg/ — HTTP 200 verified |
| Live system URL | https://uniorg-x4g7.onrender.com/login - supplied by researcher; login page HTTP 200 checked 6 November 2026 |
| Demo video URL | Not supplied |
| Designated submission Google Form | Link not yet supplied |

## GitHub and submission actions

- [x] User supplied the target submission repository for upload preparation.
- [x] User explicitly approved pushing the reviewed submission package on 2026-11-05.
- [x] Repository URL recorded above.
- [x] Reviewed local package uploaded to main; initial accepted commit d4809ed4fa8b769a66d1fda0bf3e16bf0d28e609 independently verified with git ls-remote.
- [x] Researcher enabled GitHub Pages; initial build requested during deployment troubleshooting.
- [x] Pages configured from main, / (root); actual deployed URL recorded.
- [x] Pages build and deployment succeeded; 32 deployed pages/assets/downloads returned HTTP 200 and matched local bytes. See research/documentation/PAGES_VERIFICATION.json.
- [ ] Deployed website checked at its GitHub Pages project path on desktop and mobile.
- [ ] User authorizes repository access invitation for GitHub account **teamccs-git**.
- [ ] teamccs-git access confirmed.
- [ ] Designated Google Form link supplied and recorded.
- [ ] User explicitly authorizes final form submission.
- [ ] Submission receipt/confirmation recorded after actual submission.

Follow research/documentation/PUBLISHING.md for deployment details. Repository and Pages URLs above have been verified; collaborator access and form submission are not implied by publication.
