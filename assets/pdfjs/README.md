# PDF.js reader

Self-hosted Mozilla PDF.js 6.5.438, obtained from the official distribution at https://github.com/mozilla/pdf.js/releases/tag/v6.5.438. Original Apache 2.0 license is retained in LICENSE. Source maps and the sample PDF were omitted.

UNIORG integration changes: no default sample document; page-fit default zoom; byte-range loading with streaming/automatic full-file prefetch disabled; viewer preferences disabled so these loading options remain consistent; embedded document scripting and annotation editing disabled; viewport allows browser zoom; descriptive document title; duplicate alt-text-settings button-container ID made unique; reader application state exposed as UNIORG_PDF_VIEWER for integration/QA. The complete source PDF is unmodified.

The main site creates the dialog and iframe on demand. No external CDN, npm install, backend, or external document service is needed. The preview's single-range PDF responses are a local HTTP optimization, not an application backend.
