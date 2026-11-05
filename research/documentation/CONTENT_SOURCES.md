# Content verification and source register

Reviewed 2026-11-05 (Asia/Manila). The original research folder was read only; source documents were not modified.

## Authoritative manuscript

The researchers supplied [the latest Chapter 1–5 Google Doc](https://docs.google.com/document/d/1sbe3vWyPPwBdHfYA2C-CvQsmWJFs848xwl2uu5pv7zo/edit?usp=sharing). A read-only text export was retrieved and inspected. The title page is dated OCTOBER 2026. The locally available FINAL_RC.pdf is superseded and has not been used for final website findings.

| Website content | Manuscript evidence | Verification |
| --- | --- | --- |
| Full research title | Title page and approval sheet | Matches user-supplied title |
| Symon Kiel G. Beato; Justine Mark R. Gahi | Title page and approval sheet | Verified |
| Rachiel R. Rivano, PhD | Approval sheet; manuscript uses Ph.D | Verified, punctuation normalized |
| LSPU Santa Cruz Campus; College of Computer Studies | Title page, locale and approval sheet | Verified |
| BSIT, Web and Mobile Application Development | Approval sheet | Verified; website expands the program name |
| OSAS client and 39 recognized organizations | §§1.1, 1.4.1, 1.5.1, 3.2 | Research scope, not a current live organization census |
| Problems | §1.1 Project Context | Summarized without invented improvement estimates |
| General and specific objectives | §§1.2.1–1.2.2 | Three objectives, ten feature areas, six evaluation criteria |
| Descriptive-developmental design | §3.2 | Verified |
| Six Agile sprints | §3.9 | Verified; website does not claim actual deployment is complete |
| 428 respondents and role counts | Table 3.2, §3.6, Figure 4.47 | 200 + 161 + 56 + 9 + 2 = 428 |
| 670 passed checks, zero failed, ten cases | Table 4.54 and §4.3.2 | Manuscript-reported controlled testing results |
| 4.64 overall mean, SD 0.59 | Table 4.62 | Manuscript-reported aggregate values |
| Six acceptance criterion means and SDs | Tables 4.55–4.62 | Transcribed from Table 4.62 |
| Five-point scale; Strongly Agree = 4.21–5.00 | Table 3.3 | Verified |
| Actual screenshot capabilities | Supplied storyboard_ss PNGs, §4.2.5, manual appendix | Screenshot values are not research statistics |

## Interpretation and unresolved manuscript issues

- The manuscript contains both five-construct descriptions in Chapter III and six criteria (including Quality Factors) in the objectives and Chapter IV. The website identifies the six reported evaluation criteria, preserving the results table. Researchers should reconcile the manuscript wording.
- Chapter III describes total enumeration of deans and OSAS administrators; the Figure 4.47 discussion describes dean sampling differently. The website gives the verified role counts without asserting a conflicting sampling detail.
- Table 4.54 describes ten personnel/stakeholder evaluators, while other sections call them IT testers. The website reports the ten cases and 670 checks without inventing evaluator qualifications.
- The reported overall SD of 0.59 is transcribed. It has not been recomputed from individual responses; no private respondent data is included.
- The manuscript's Chapter I/III stack includes Groq, while parts of Chapter IV mention Gemini. Avoid claiming an exact current model/provider without checking enabled runtime configuration. The confirmed branch includes both service implementations.
- Text export contains residual wording from revisions and some broken sentences. The manuscript is not silently edited in this package. Summaries use supported facts and retain qualifications.
- No independent OCR accuracy, processing-time reduction, load benchmark or security guarantee is claimed. TAM ratings are acceptance measures.
- §3.13.2 states that no Cronbach alpha or other reliability testing was performed. The website does not imply psychometric reliability was established.

## Other sources

- Original local materials: `Capstone_Chapter-1-5`, inventory in MATERIALS_INVENTORY.json. The inventory lists assets, not their private contents.
- `user_manual/User_Manual_UniOrg_v2.docx` is UNIORG, but older than the current manuscript appendix. The filename `USERMANUAL FINAL.pdf` is misleading: its content is TUGON and is excluded.
- `BLACK_BOX_TESTING_SHEET_UNIORG (1).pdf` contains blank test forms. It is not evidence of executed test results. Findings come from the latest manuscript instead.
- [AgeSense website](https://somarjez.github.io/agesense-pages/) and [its repository](https://github.com/somarjez/agesense-pages): inspected as presentation references only. Research progression, readable sections, captioned images, and resource grouping informed the site structure. No AgeSense findings, assets, code, or branding were reused.
- [Confirmed source branch](https://github.com/zorojuro0101/-UniOrg/tree/Modification_based_Mia): user confirmed Modification_based_Mia; accessible at commit `1cbf524b77947010041bf3fdbdbbfd7412e06176`. Complete sanitized application copied with provenance/hashes. The superseded main revision `93062eb` stays ignored for comparison only.

## Public file policy

The illustrated manual retains all 60 original procedure lists/captions and includes 57 images from the verified manuscript PDF plus three matching original research PNGs. Images are aligned to the actual feature rather than copied from mismatched appendix positions. Event registration (Figure 133) and voting (Figure 137) have explicit notes identifying the supplied related screen states. See ../manuals/images-manifest.json for provenance and permanent raster redactions. No missing UI or dialog is fabricated.

Do not copy original appendices wholesale: they can contain signatures, private profiles, receipts, CORs, interview records and photographs. Public downloads must be privacy-reviewed derivatives. The linked Google Doc retains the author's existing sharing controls; this task does not change access.
