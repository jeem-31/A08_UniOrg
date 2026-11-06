# UNIORG redesign verification

Verified locally on 6 November 2026 (Asia/Manila). No push or publication was performed.

## Results

| Check | Result |
| --- | --- |
| Homepage, documentation, diagrams and manual at 320, 390, 768, 1024 and 1440px | 20/20 pass |
| Horizontal page overflow and broken images, before/after reading | None found |
| Active homepage sections and navigation space | All tested sections pass; content stays clear of the rail/drawer |
| Carousel, drawer, viewer, motion and fallbacks | 42/42 functional checks pass |
| Project-prefix routes, mobile touch, modal focus, deep links and failed-animation fallback | 10/10 additional checks pass |
| axe-core 4.11.0, WCAG A/AA tags, four pages at 390/1440px | Zero detected violations |
| Open drawer and open viewer accessibility scans | Zero detected violations |
| Browser JavaScript/console errors during layout and interaction checks | None |
| Local linked resources/images/downloads | 96 distinct HTTP targets returned 200 |
| Downloadable manuscript and manual text | Both available |
| Google manuscript and submission repository | HTTP 200 |
| Pre-existing pinned upstream source URL | HTTP 404; preserved and marked unavailable |
| Original main research text | Retained except the researchers' requested test-total/pass-rate removal and manuscript-link replacement; see latest addendum |
| Manual procedures | All 60, figures 125-184, retain original wording/order and image hashes |
| Laravel snapshot | All 986 file hashes unchanged |
| Static submission verification | Blocked by a newly present 232,146,775-byte manuscript PDF; four HTML pages and 254 local references otherwise pass |
| git diff --check | Pass |

The six-screen carousel was tested with actual five-second waits: autoplay, visible pause/play, hover pause/resume, focus stop until explicit Play, previous/next, six position selectors, arrows/Home/End, viewer pause, swipe and vertical-touch noninterference. Focused screenshots stay stable. Reduced motion disables autoplay and camera movement; manual controls remain usable.

Drawer checks cover Escape, backdrop, forward/reverse focus wrap, inert background, focus restoration, active states and deep links. Native image viewers were checked on homepage, manual and diagrams, with arrow navigation, Escape, full-resolution links and focus restoration. A small explicit Tab wrap keeps focus inside the native dialog rather than reaching the browser chrome at its boundary.

No-JavaScript checks pass on all four pages at 320px: research content, navigation and direct image links remain available, and the homepage displays all six screenshot figures. A separate run blocked both animation libraries and confirmed that research content and the browser frame remain readable. All four pages also pass when virtually mounted at `/A08_UniOrg/` through the local preview server.

## Visual review and refinements

Reviewed screenshots for all 20 page/width combinations, plus the research, results and resource sections, first/middle/last manual procedures, diagram collection, carousel, viewer, drawer, reduced motion and no-JavaScript fallback. Fixed the sidebar index contrast, modal container semantics, the pause button's pointer/focus interaction, dialog Tab wrapping, increased supporting prose/table sizes, and separated numeric table columns by at least 10px at every required width. The seal is circular and undistorted; the 28 KB favicon derives from the exact supplied asset.

Review images and browser scripts are in ignored `.local/redesign-qa/` and `.local/*_qa.py`. Four `*-contact.png` sheets collect the 20 viewport screenshots. Detailed machine-readable results are in [REDESIGN_QA.json](REDESIGN_QA.json). The audit and skill workflow are in [REDESIGN.md](REDESIGN.md) and `design-system/uniorg/MASTER.md`.

## Submission blocker discovered at final verification

The final `python scripts/verify_submission.py` exits 1 because `research/manuscript/(NEW format) UNIORG_MANUSCRIPT_CHAPTER 1-5.pdf` is 232,146,775 bytes (about 221.4 MiB), exceeding the existing 95 MiB packaging threshold. This untracked file was absent from the initial inventory and appeared during the redesign. It was not created, edited or relocated by this work. Earlier verifier runs passed. It is not linked by the website and does not affect the local redesign preview, but its disposition must be resolved before the submission verifier can pass. No verifier rule was bypassed.

## Limits and remaining resources

These are Chromium browser tests with responsive and touch emulation, not physical-device or full assistive-technology certification. Automated accessibility checks have manual-review items; contrast, visible focus, text reflow and screenshot layouts were also reviewed. No Firefox/Safari-specific claim is made.

The previously linked upstream snapshot returns 404 and may be private or unavailable. Its URL is retained, and the complete included source/setup guide remains accessible. Official live-system and demo-video URLs were not supplied, so their existing unavailable state is retained. No deployment or Laravel runtime behavior was tested or changed by this redesign.

## Exact preview commands

From the project root:

```powershell
python scripts/verify_submission.py
python scripts/preview.py
```

Open http://127.0.0.1:4173/. The static website needs no npm install, build, CDN or backend. Stop the preview with Ctrl+C.

## Floating sidebar refinement

The follow-up floating glass sidebar uses 16px desktop insets, rounded corners, a translucent surface, 20px backdrop blur and a soft shadow. Section backgrounds continue behind the glass while content retains a clear navigation gutter. The mobile drawer uses 12px insets and the same surface treatment; keyboard/Escape behavior is unchanged.

Rechecked all four pages at 320, 390, 768, 1024 and 1440px: all 20 layouts pass without horizontal overflow or console errors. Desktop content clearance is 48px at 1024 and approximately 74px at 1440. Drawer bounds and Escape focus restoration pass at 320/390/768px. The homepage accessibility scan reports no detected violations. Desktop/mobile screenshots were reviewed, including documentation and the research section. The previously recorded oversized-PDF submission blocker remains separate and unchanged.

## Liquid glass and scrollbar refinement

Added adaptive light/dark translucent material, 24px backdrop blur, a layered highlight, 28px corners and an inset rim. Sidebar text adjusts to the section behind it. Removed the decorative progress strip/label on all four pages, and hid native sidebar scrollbars without disabling overflow scrolling. Verified 20 layouts, drawer Escape/focus restoration, dark/light/drawer accessibility scans, and keyboard access to the repository link at a short 390x500 viewport. No horizontal overflow or JavaScript errors were detected.

## Latest manuscript modal and results correction

The researcher supplied the local 445-page manuscript PDF and explicitly requested removal of the homepage test-total statistic. Inspection confirmed that the original source does include that statistic in Table 4.54, printed page 226 / PDF page 247; it counts step-level test verifications. The homepage statistic and its pass-rate wording were removed as requested. The results now highlight the verified 428 respondents and 4.64/5 acceptance mean (Table 4.62, PDF page 260 / printed page 239). Current results/source notes and README were updated consistently. The source PDF itself is unchanged.

The Research manuscript resource now opens that exact PDF in a native modal. Manual/documentation manuscript links open the same reader; no website viewing action points to Google Docs. Mozilla PDF.js 6.5.438 is self-hosted with its license. The reader supports text search, page navigation, page selection, zoom, original-PDF opening and downloading. It loads only on demand and retains its page when reopened. No-JavaScript links go directly to the PDF.

Validation: 37/37 targeted checks pass, including all four pages at five required widths (20 layouts), PDF rendering at every width, fit within the viewport, iframe Escape close/focus restoration, documentation/manual dialogs, page navigation, zoom, search, reopening, direct-file links, and `/A08_UniOrg/` project-prefix routes. First, acceptance-table and final PDF pages rendered successfully. No console errors were detected. Parent modal accessibility scans at 320/1440px detected no violations; this is not a claim that all source-PDF contents are fully tagged/accessibility-certified.

The safe local preview now handles single-byte-range PDF requests. Tested 206 prefix/suffix ranges against source bytes, 416 invalid/out-of-bounds responses and continued 404 protection for private working files. PDF.js uses these ranges rather than waiting for the entire source file to load. The reader is still entirely static and needs no application backend.

`python scripts/verify_submission.py` reports five HTML pages and 262 local references. Its only remaining error is the unchanged 232,146,775-byte source PDF exceeding the existing 95 MiB file limit. The source was not relocated, recompressed or replaced. A smaller deployment copy remains necessary before an ordinary GitHub upload. No push or publication occurred. `node --check assets/js/main.js`, Python preview syntax and `git diff --check` pass. Laravel source is unchanged.

## Resource cleanup and formatted document previews

Added the researcher-supplied live-system login URL, https://uniorg-x4g7.onrender.com/login. A read-only HTTP check returned 200 and a login form; authenticated workflows were not tested. The resources section now has six working destinations: manuscript, documentation, illustrated manual, diagrams, source/setup guide and live system. Removed the unavailable live/demo placeholder, broken upstream promotion, repetitive submission strip and long audit/status text from that section. Kept the repository and manuscript text download in a compact footer. Simplified the documentation hub by removing the old-source comparison and materials-inventory promotion; original files and research evidence remain available.

All local Markdown links now open a shared native-dialog reader with semantic headings, formatted tables, code blocks with Copy controls, collapsible contents, nested-document navigation, Back, Download, Escape, keyboard focus containment and focus restoration. JSON and non-download text links use the same reader. Downloads retain their native behavior. Relative paths resolve against the viewed source document, including nested links from source-code/README.md. Marked 18.1.3 and DOMPurify 3.4.17 are self-hosted, license-preserved and loaded only when needed. Rendered Markdown is sanitized; unsafe script/URL output is rejected. Source application files are never rewritten.

Validation: 20/20 targeted checks pass. All four pages pass the five required viewport widths (20 layouts); Markdown previews were opened wherever those pages expose Markdown links. The setup guide renders at 320, 390, 768, 1024 and 1440px with no outer horizontal overflow. Seven distinct Markdown resources directly linked by the website all rendered. Nested relative links, contents navigation, Back, code copying, JSON formatting, error/retry, direct-file no-JavaScript fallback and `/A08_UniOrg/` paths pass. Desktop and mobile modal accessibility scans found no violations after making overflowing code blocks keyboard-scrollable. Existing PDF and screenshot viewers still work; no unexpected JavaScript console errors were detected. Screenshots of the final resource grid, setup guide, configuration table and mobile reader were visually reviewed.

The original 986 Laravel snapshot file hashes remain unchanged. JavaScript syntax and git whitespace checks pass. Static submission verification checks five HTML pages and 261 references; its sole remaining error is the previously recorded oversized original manuscript PDF. No push or publication was performed.

## Local glass contrast at section boundaries

Replaced the single midpoint-based sidebar theme switch with complementary CSS masks aligned to the actual viewport intersections of the navy hero/results backgrounds. Glass material and text are painted separately in the light/dark parts of the same rail, including a boundary crossing through an individual navigation label. The masks update on the native-scroll animation frame with no color-switch delay. Active-section tracking remains independent.

There is still one interactive/accessibility navigation set. An aria-hidden, inert visual paint copy uses spans rather than anchors or buttons, with no duplicate IDs or tab stops. It mirrors active, hover, keyboard-focus and internal-scroll positions. Mobile retains the established dark drawer against its full-screen scrim. Sidebar scrollbars remain hidden.

Validation: 23 targeted checks pass plus six hero/results exit-edge checks. At 1024/1440px, mask offsets match five tested section-edge positions within 0.1px and update after one pixel of scrolling. Mixed/partially intersected labels, dark-area clicks, hover/focus mirroring, internal scrolling at short heights and the single accessible navigation set pass. All four pages at the five required widths (20 layouts) have no horizontal overflow, duplicate IDs, drawer-focus regressions or console errors. The mixed-background accessibility scan detected no violations. Mixed and split-label screenshots were visually reviewed. Source application and research content are unchanged.

## Brochure seal composition in the hero

Added the researcher-requested upper-right brochure composition: the supplied official LSPU seal, proportionally scaled and intentionally cropped at the right edge, vertical UNIORG lettering, a white rim and an offset circular navy shadow. The PNG itself is unchanged. The ornament is static, decorative and hidden from assistive technology. At desktop widths the research title, intro and actions share a reserved left column. Below 1100px the decorative corner is omitted to retain the existing compact reading layout and avoid collisions.

Reviewed screenshots at 320, 390, 768, 1024, 1100, 1280, 1440 and 1920px, including regular/reduced-motion desktop renders. All eight widths have no horizontal overflow or JavaScript errors. At the four ornament-visible widths, the seal remains square and neither its layout nor the vertical label overlaps the heading, formal title, intro or actions. The actual screenshot viewer still opens/closes and restores focus; the Explore the system link still works. The accessibility scan detected no violations. Research wording, official image bytes and Laravel source remain unchanged.

## Logo-only hero and typography refinement

Removed the added vertical UNIORG label as requested. Enlarged the proportional official seal to 440px at 1100px, 576px at 1440px and a maximum of 650px, retaining the brochure-style offset shadow. Increased desktop headline and formal-title sizes, balanced title wrapping, and adjusted the supporting copy measure to reduce empty space. The original main heading and full formal research title remain unchanged.

Verified the homepage at 320, 390, 768, 1024, 1100, 1280, 1440, 1600, 1799, 1800 and 1920px. All 11 layouts have no horizontal overflow, heading/copy collisions with the seal or JavaScript page errors. Reviewed the 1440px and 1920px screenshots. Compact layouts retain their existing readable treatment. No JavaScript or Laravel source changes were needed.

## Manuscript deployment copy

The previous size blocker was resolved by creating an 18,705,090-byte web copy of the 232,146,775-byte researcher-supplied manuscript. The unmodified original is retained locally at .local/manuscript-original-232mb.pdf. All 445 pages and their extracted text match; representative pages were rendered for visual review. Embedded images are downsampled in the web copy. Earlier size-blocker notes record the state at those earlier checks.

## Full HD audiovisual presentation

Added the supplied AVP immediately after the hero, with a source-frame poster, Full HD label, sound toggle, native controls/fullscreen and download. Added presentation links to all four research-library navigation menus and the homepage resources. The 82,585,617-byte web encoding retains 1920x1080, 60 FPS, 21,510 frames and 358.5 seconds; AAC audio is copied. The original 128,546,606-byte file is retained locally. See assets/videos/README.md for hashes and encoding provenance.

Viewport autoplay begins muted. Offscreen, hidden-tab and modal/drawer states pause playback; manual Pause is preserved and reduced motion disables autoplay. No loop is used. The preview server now supports MP4 byte ranges as well as PDF ranges for seeking.

Validation: all four pages at 320, 390, 768, 1024 and 1440px pass layout/navigation checks without overflow or JavaScript page errors. Autoplay, audio toggle, viewport pause/resume, manual Pause persistence, runtime reduced motion, modal pause/resume, Full HD decoding and seeking to 4:00 pass. The presentation accessibility scan detected no violations; desktop/mobile screenshots and an encoded frame were visually reviewed. The native video also plays with site JavaScript disabled. Valid and suffix byte ranges, invalid-range rejection, video MIME type and fast-start atom order pass. Submission verification passes with five HTML pages and 270 local references.
