# Illustrated UNIORG user manual

Open [the illustrated manual](index.html) for all 60 procedures (Figures 125-184), with a screenshot alongside each procedure, role navigation and an enlargeable screenshot viewer. The separate downloadable text file preserves the original procedure wording and captions.

## Verified image sources

The latest researcher-supplied manuscript appendix provides the procedures and 57 embedded screen images. Three matching original research screenshots supplement the login, student event list and dean dashboard. Some appendix image positions do not match their captions; the public manual aligns images by the actual visible feature. Original files remain unchanged. Each source, image hash, crop and redaction box is recorded in [the image manifest](images-manifest.json).

Two supplied images show related states rather than the named modal: Figure 133 shows event details, and Figure 137 shows the recorded ballot after voting. Each is explicitly identified in its caption note. No missing dialog or screen has been fabricated.

## Privacy

Names, account identities, student IDs, emails, payment references, personal document previews and complaint records are covered where present. Opaque masks are baked into exported image pixels; the public files contain no hidden original image/PDF layers or EXIF/ICC metadata. The source PDF and unredacted screenshots stay outside the public package.

The older 22-image manual document and the TUGON reference PDF are not used as the procedure source. The text edition contains no images; use the HTML edition and its images/ directory for the illustrated manual.

## Rebuild

After installing PyMuPDF and Pillow, run `python scripts/prepare_manual.py` from the repository root. The script requires the ignored, verified manuscript PDF and the original research screenshot folder. It checks the source PDF hash, matches the 60 captions, applies the reviewed masks, preserves every existing procedure list and writes only submission derivatives. Browser visual/interactivity checks remain documented separately from image review and file checks.
