# GitHub and GitHub Pages

The researchers selected [jeem-31/A08_UniOrg](https://github.com/jeem-31/A08_UniOrg) as the submission repository and explicitly approved uploading the reviewed package. The initial accepted main commit, `d4809ed4fa8b769a66d1fda0bf3e16bf0d28e609`, was verified on GitHub on 2026-11-05. They then enabled Pages from main/root. No build had started, so a build was explicitly requested during troubleshooting; [the first deployment succeeded](https://github.com/jeem-31/A08_UniOrg/actions/runs/42046589407).

The verified website URL is **https://jeem-31.github.io/A08_UniOrg/**. All 32 checked deployed pages/assets/downloads returned HTTP 200 and matched the local files. See PAGES_VERIFICATION.json. Desktop/mobile visual and interaction checks remain pending, as do collaborator access and the submission form.

## Before publication

1. Finish pending requirements in ../../SUBMISSION_CHECKLIST.md, including remaining backend and browser validation. The confirmed latest source snapshot is included.
2. Review all public files and downloads. GitHub Pages content is normally public even when repository access is restricted; verify visibility appropriate to the account plan.
3. Run `python scripts/verify_submission.py` and review the report. Keep `.local/` excluded. It contains temporary original exports and the superseded code checkout.
4. The repository owner/name is confirmed as jeem-31/A08_UniOrg. Record the actual deployed GitHub Pages URL in the checklist after publication.

## Publish from main, root

Perform these steps only after the user authorizes pushing and publishing.

1. Use the existing researcher-supplied repository, `https://github.com/jeem-31/A08_UniOrg.git`. Check its current branches before pushing; do not force-push over existing work.
2. Review files with `git status --short` and `git diff --check`. Stage only the intended submission files, review the staged diff, then create the first commit.
3. Set `origin` to the actual repository URL, verify `git remote -v`, and push `main` after authorization.
4. In GitHub: **Settings → Pages → Build and deployment → Source: Deploy from a branch → Branch: main → Folder: / (root) → Save**.
5. Keep `index.html` and `.nojekyll` at the root. The website uses relative asset/download paths and needs no Node build step.
6. Wait for the Pages deployment, then record the actual URL returned by GitHub. The usual project-site pattern is `https://OWNER.github.io/REPOSITORY/`; this is a template, not a verified URL.
7. Recheck navigation, screenshots, PDF downloads and external links at the deployed project path on desktop and mobile.
8. Grant the GitHub account **teamccs-git** repository access only after the user authorizes the invitation. Confirm acceptance/access in the submission checklist.
9. Submit the repository URL, actual Pages URL and required details through the designated Google Form only after the link is provided and submission is explicitly authorized.

## Website preview

```powershell
python scripts/preview.py
```

Open `http://127.0.0.1:4173/`. The local preview serves only submission files, denies hidden paths and prevents access to `.local/` and Git metadata. Stop with Ctrl+C. The static website can also be opened directly via index.html; HTTP preview best matches Pages paths.

The research website is static. Deploying it does not deploy the Laravel system in source-code/.
