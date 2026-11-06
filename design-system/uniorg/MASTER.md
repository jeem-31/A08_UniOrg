# UNIORG implementation decisions (authoritative overrides)

The following explicit project requirements override the generator recommendations below.

- Product: static university research showcase; audience: academic reviewers and university stakeholders.
- Stack: semantic HTML, CSS and vanilla JavaScript. No framework or backend. There is no exact plain-CSS stack in the skill database; use its Web UX guidance rather than adding Tailwind.
- Primary navy `#0F2A5C`, accent maroon `#7A1414`, white `#FFFFFF`. Supporting neutral surfaces `#f6f7f9`, `#edf0f5`; text `#17233b`, muted `#526078`.
- Inter variable font, self-hosted; system sans fallbacks. Inter/Inter is the verified Minimal Swiss typography match. All main prose is at least 16px; labels, provenance and table annotations may be smaller.
- Swiss Modernism result is applicable to editorial alignment, documentation and visual hierarchy. Replace its portfolio/contact pattern with the existing academic research sections; no invented social proof or contact content.
- Floating left research rail with 16px viewport insets, 28px rounded corners, layered translucent glass, edge highlights and 24px backdrop blur; floating modal side drawer at 1000px and below. All destinations remain available without JavaScript.
- Native scrolling only. Desktop browser frame rotates from 10deg X / -12deg Y to front view using GSAP ScrollTrigger; no pinning. Camera and pointer movement are disabled on compact layouts or reduced motion.
- Six real screenshots form one progressively enhanced carousel. Autoplay 5000ms; stop on focus until explicit Play, pause on hover/viewer/hidden tab/offscreen, disable autoplay for reduced motion. Full fallback gallery remains in HTML.
- Source diagrams and redacted screenshots are immutable research artifacts. Apply the new palette to their surrounding UI; never recolor source evidence.
- All research wording, figures, results, citations, names, downloads and 60 procedures remain unchanged.

## Verified skill workflow

Installed project-locally with `npx.cmd --yes --package ui-ux-pro-max-cli uipro init --ai codex`.
Read `.agents/skills/ui-ux-pro-max/SKILL.md`, including its quick reference and pre-delivery rules.
Generated with `python .agents/skills/ui-ux-pro-max/scripts/search.py "university research cinematic editorial" --design-system -p UNIORG --motion 6 --density 6 --variance 7 --persist --output-dir .`.
Focused searches: `Inter minimal sans` (Minimal Swiss), `scroll perspective reveal` (Scroll Reveal), `drawer keyboard focus navigation` (Keyboard Navigation), `auto rotation controls` (Auto-Rotating Content Controls), and `focus management modal` (Focus States). Applied reduced-motion, semantic control, contrast and readable-layout guidance.

The original machine-generated recommendations follow for traceability. The overrides above take precedence.

---

# Design System Master File

> **LOGIC:** When building a specific page, first check `design-system/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** UNIORG
**Generated:** 2026-11-06 14:27:51
**Category:** Research Lab / University Department
**Design Dials:** Variance 7/10 (Balanced / Modern) | Motion 6/10 (Standard) | Density 6/10 (Standard)

---

## Global Rules

### Color Palette

| Role | Hex | CSS Variable |
|------|-----|--------------|
| Primary | `#1E3A5F` | `--color-primary` |
| On Primary | `#FFFFFF` | `--color-on-primary` |
| Secondary | `#2563EB` | `--color-secondary` |
| On Secondary | `#FFFFFF` | `--color-on-secondary` |
| Accent/CTA | `#A16207` | `--color-accent` |
| On Accent/CTA | `#FFFFFF` | `--color-on-accent` |
| Background | `#F8FAFC` | `--color-background` |
| Foreground | `#0F172A` | `--color-foreground` |
| Card | `#FFFFFF` | `--color-card` |
| Card Foreground | `#0F172A` | `--color-card-foreground` |
| Muted | `#E9EEF5` | `--color-muted` |
| Muted Foreground | `#475569` | `--color-muted-foreground` |
| Border | `#CBD5E1` | `--color-border` |
| Destructive | `#DC2626` | `--color-destructive` |
| On Destructive | `#FFFFFF` | `--color-on-destructive` |
| Ring | `#1E3A5F` | `--color-ring` |

**Color Notes:** Institutional navy + research accent + serif headings

### Typography

- **Heading Font:** EB Garamond
- **Body Font:** Crimson Text
- **Mood:** academic, old-school, university, research, serious, traditional
- **Google Fonts:** [EB Garamond + Crimson Text](https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700;800&display=swap)

**CSS Import:**
```css
@import url('https://fonts.googleapis.com/css2?family=Crimson+Text:wght@400;600;700&family=EB+Garamond:wght@400;500;600;700;800&display=swap');
```

### Spacing Variables

*Density: 6/10 — Standard*

| Token | Value | Usage |
|-------|-------|-------|
| `--space-xs` | `4px` / `0.25rem` | Tight gaps |
| `--space-sm` | `8px` / `0.5rem` | Icon gaps, inline spacing |
| `--space-md` | `16px` / `1rem` | Standard padding |
| `--space-lg` | `24px` / `1.5rem` | Section padding |
| `--space-xl` | `32px` / `2rem` | Large gaps |
| `--space-2xl` | `48px` / `3rem` | Section margins |
| `--space-3xl` | `64px` / `4rem` | Hero padding |

### Shadow Depths

| Level | Value | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | Subtle lift |
| `--shadow-md` | `0 4px 6px rgba(0,0,0,0.1)` | Cards, buttons |
| `--shadow-lg` | `0 10px 15px rgba(0,0,0,0.1)` | Modals, dropdowns |
| `--shadow-xl` | `0 20px 25px rgba(0,0,0,0.15)` | Hero images, featured cards |

---

## Component Specs

### Buttons

```css
/* Primary Button */
.btn-primary {
  background: #A16207;
  color: white;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}

.btn-primary:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

/* Secondary Button */
.btn-secondary {
  background: transparent;
  color: #1E3A5F;
  border: 2px solid #1E3A5F;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}
```

### Cards

```css
.card {
  background: #F8FAFC;
  border-radius: 12px;
  padding: 24px;
  box-shadow: var(--shadow-md);
  transition: all 200ms ease;
  cursor: pointer;
}

.card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}
```

### Inputs

```css
.input {
  padding: 12px 16px;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  font-size: 16px;
  transition: border-color 200ms ease;
}

.input:focus {
  border-color: #1E3A5F;
  outline: none;
  box-shadow: 0 0 0 3px #1E3A5F20;
}
```

### Modals

```css
.modal-overlay {
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

.modal {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: var(--shadow-xl);
  max-width: 500px;
  width: 90%;
}
```

---

## Style Guidelines

**Style:** Swiss Modernism 2.0

**Keywords:** Grid system, Helvetica, modular, asymmetric, international style, rational, clean, mathematical spacing

**Best For:** Corporate sites, architecture, editorial, SaaS, museums, professional services, documentation

**Key Effects:** display: grid, grid-template-columns: repeat(12 1fr), gap: 1rem, mathematical ratios, clear hierarchy

### Page Pattern

**Pattern Name:** Portfolio Grid

- **Conversion Strategy:** Visuals first. Filter by category. Fast loading essential.
- **CTA Placement:** Project Card Hover + Footer Contact
- **Section Order:** Hero (Name/Role) > Project Grid (Masonry) > About/Philosophy > Contact

---

## Motion

**Stagger List** (Standard) — Trigger: load or scroll | Duration: 300-450ms | Easing: `back.out(1.4)`

```js
gsap.from('.grid-item', { opacity: 0, scale: 0.92, y: 16, duration: 0.4, stagger: { each: 0.06, from: 'start', grid: 'auto' }, ease: 'back.out(1.4)' });
```

**Framework notes:** grid: 'auto' lets GSAP infer rows/columns from a CSS grid layout for a natural wave stagger; Use matchMedia('(prefers-reduced-motion: reduce)') to skip non-essential motion and render the final state immediately

- ✅ Combine with from: 'center' for a bento-grid layout to draw the eye inward first
- ❌ Don't use back.out on dense data tables; the overshoot reads as sloppy on informational UI
- ⚡ Group DOM writes; avoid interleaving layout reads (getBoundingClientRect) between staggered tweens

---

## Anti-Patterns (Do NOT Use)

- ❌ Low hierarchy
- ❌ no publication filtering
- ❌ cluttered visuals

### Additional Forbidden Patterns

- ❌ **Emojis as icons** — Use SVG icons (Heroicons, Lucide, Simple Icons)
- ❌ **Missing cursor:pointer** — All clickable elements must have cursor:pointer
- ❌ **Layout-shifting hovers** — Avoid scale transforms that shift layout
- ❌ **Low contrast text** — Maintain 4.5:1 minimum contrast ratio
- ❌ **Instant state changes** — Always use transitions (150-300ms)
- ❌ **Invisible focus states** — Focus states must be visible for a11y

---

## Pre-Delivery Checklist

Before delivering any UI code, verify:

- [ ] No emojis used as icons (use SVG instead)
- [ ] All icons from consistent icon set (Heroicons/Lucide)
- [ ] `cursor-pointer` on all clickable elements
- [ ] Hover states with smooth transitions (150-300ms)
- [ ] Light mode: text contrast 4.5:1 minimum
- [ ] Focus states visible for keyboard navigation
- [ ] `prefers-reduced-motion` respected
- [ ] Responsive: 375px, 768px, 1024px, 1440px
- [ ] No content hidden behind fixed navbars
- [ ] No horizontal scroll on mobile

Sidebar refinement: adaptive light/dark glass keeps labels readable. Native sidebar scrollbars are hidden while overflow scrolling and focus navigation remain enabled. The decorative scroll-progress strip and label were removed at the user's request.

Manuscript follow-up: the Research manuscript resource uses an accessible native modal with the supplied 445-page PDF in a self-hosted reader. On-demand loading, direct PDF fallback, iframe Escape handling and focus restoration are required. The homepage retains two result highlights; the aggregate test-total statistic and pass-rate wording were removed at the researchers' request. Original PDF and procedural screenshots remain unchanged.

Resource follow-up: live system points to the researcher-supplied Render login. No unavailable demo placeholder or broken upstream promotion appears in the resource grid. Local Markdown opens in a shared formatted document modal with contents, nested navigation, Back, Download, sanitized GFM rendering and keyboard-scrollable code/tables. Source application files remain immutable; the reader formats their existing content without rewriting it.

Local glass refinement: never switch the entire desktop sidebar from a viewport midpoint. Complementary masks must follow actual navy section edges each native-scroll frame, covering the matching material and foreground pixels. Retain one semantic navigation set; the dark visual paint copy is aria-hidden, inert, ID-free and contains no interactive elements. Mirror active, hover, focus and internal scroll states. The compact modal drawer stays dark over its full-screen scrim.

Brochure hero refinement: from 1100px upward, reserve the upper-right corner for the supplied seal cropped at the page edge and a hard offset circular shadow; do not add vertical UNIORG lettering. Keep the left research text/actions clear; preserve the original seal bytes/aspect ratio. Treat this repeat branding as decorative and omit the corner ornament on compact layouts.

Logo-size follow-up: the corner ornament contains only the official seal. Scale its square bounds with clamp(430px, 40vw, 650px), retain the offset shadow, and increase the desktop headline/formal-title hierarchy to fill the composition. Keep the existing main UNIORG heading and complete formal research title. Reserve a clear left column; widen it only from 1800px.
