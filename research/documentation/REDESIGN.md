# Visual redesign audit and implementation

## Audit

The previous site used a blue/gold palette (#26488C / #C4973B), a generic U mark, top navigation, serif section headings, many 11-14px content blocks and a static two-column screenshot grid. Supporting pages shared the old shell. Its research material and redacted images were already organized and have been retained.

The supplied two-page A4 brochure was rendered and visually reviewed. Navy/white panels, maroon accents, strong sans-serif headings, university seal, compact feature rows and deliberate visual hierarchy informed the redesign. No claims or URLs were copied from the brochure; its displayed live-system address has not been independently established as an official current deployment.

## Implementation

- Shared left navigation, progress and section tracking; accessible compact drawer with focus containment, Escape, backdrop close, inert background and focus restoration.
- Inter, responsive typography, official LSPU seal, navy/maroon/white tokens, restrained surfaces and alternating editorial compositions.
- Self-hosted GSAP 3.14.2 and ScrollTrigger: native-scroll 3D browser frame, subtle pointer tilt, screenshot panel sequence and short section staggers. No pinned or artificial scroll distances.
- Six original screenshots, five-second carousel, visible pause/play, position controls, swipe/keyboard support, focus stability, preserved captions, redactions and native image links.
- Full-size native dialog on homepage, manual and diagrams; arrow navigation, Escape, focus restoration and a direct full-resolution link.
- Reduced-motion and no-JavaScript fallbacks. Procedural images remain in original reading order.
- Laravel snapshot under source-code is outside the redesign scope.

## Skill and dependency provenance

Used [UI UX Pro Max](https://github.com/nextlevelbuilder/ui-ux-pro-max-skill), installed locally through its documented CLI. Design-system output and explicit brand overrides are in `design-system/uniorg/MASTER.md`. Local skill files are ignored by Git and do not run in the website.

[GSAP ScrollTrigger documentation](https://gsap.com/docs/v3/Plugins/ScrollTrigger/) and [gsap.matchMedia](https://gsap.com/docs/v3/GSAP/gsap.matchMedia()/) informed animation setup and preference cleanup. Carousel behavior follows the [WAI carousel pattern](https://www.w3.org/WAI/ARIA/apg/patterns/carousel/), including stopping rotation after focus until an explicit restart.

Inter is self-hosted with its SIL Open Font License in assets/fonts/OFL.txt. GSAP and ScrollTrigger retain upstream copyright/license headers; see https://gsap.com/standard-license/ for licensing. No CDN or external runtime service is needed.

## Preview

```powershell
python scripts/verify_submission.py
python scripts/preview.py
```

Open http://127.0.0.1:4173/. Browser validation and screenshots are recorded separately in REDESIGN_QA.md and REDESIGN_QA.json after testing.
