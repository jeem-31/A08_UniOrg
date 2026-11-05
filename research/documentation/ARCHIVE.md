# Clean local submission archive

Archives made before the GitHub push-protection review are superseded and moved into ignored `.local/quarantine-do-not-upload/`. They contain the unredacted upstream documentation key and must not be shared. Only use a fresh archive generated after the expanded credential scan passes.

Generate the package from the repository root with:

```powershell
python scripts/package_submission.py
```

The ZIP is written under ignored `.local/releases/UNIORG-Submission-2026-11-05.zip`. It contains the website, research derivatives, complete sanitized application source, root submission documents and helper scripts. It excludes private `.local/`, Git metadata, internal working notes, vendor and node_modules.

`PACKAGE_CONTENTS.json` inside the ZIP records every packaged file's SHA-256 hash and byte size. The packager runs submission checks, validates the source snapshot against its provenance manifest, then verifies ZIP CRCs and all archived file hashes. It preserves existing release archives and creates an `-r2`, `-r3`, or later revision when the original filename already exists.

This is a local review artifact. It has not been uploaded, published or submitted. Browser visual/interactivity verification and backend runtime limitations remain as documented in VERIFICATION.md and the submission checklist. A ZIP does not resolve those pending checks.
