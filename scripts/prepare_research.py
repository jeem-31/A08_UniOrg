"""Create privacy-reviewed static derivatives from explicitly selected research sources.

Run with Python + PyMuPDF + Pillow available. Inputs stay in ignored .local or in
the read-only original research folder. No original file is modified.
"""
from pathlib import Path
from hashlib import sha256
from html import escape
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / '.local/python'))
import pymupdf as fitz
from PIL import Image

ORIGINAL = Path(r'C:\Users\justine\Documents\screeshit\Capstone_Chapter-1-5')
SOURCE_URL = 'https://docs.google.com/document/d/1sbe3vWyPPwBdHfYA2C-CvQsmWJFs848xwl2uu5pv7zo/edit?usp=sharing'


def page(title, eyebrow, intro, content):
    return f'''<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{escape(title)} — UNIORG</title><link rel="icon" href="../../assets/images/mark.svg"><link rel="stylesheet" href="../../assets/css/style.css"></head>
<body class="document-page"><a class="skip-link" href="#main">Skip to content</a><header class="site-header"><div class="container header-inner"><a class="brand" href="../../index.html"><img src="../../assets/images/mark.svg" width="32" height="32" alt=""><span>UNIORG<span class="brand-note">Research repository</span></span></a><a class="text-link" href="../../index.html#resources">Back to research ↗</a></div></header><main id="main"><section class="section"><div class="container"><div class="section-top"><span class="section-number">{escape(eyebrow)}</span><span class="small-label">RESEARCH MATERIALS</span></div><h1 style="font:normal clamp(36px,5vw,58px)/1.15 var(--serif);letter-spacing:-1.5px;margin-bottom:24px">{escape(title)}</h1><p class="lead" style="max-width:850px">{intro}</p><div class="document-content">{content}</div></div></section></main><footer><div class="container"><a href="../../index.html">UNIORG research home ↗</a><p class="source-note">Beato &amp; Gahi · College of Computer Studies · LSPU Santa Cruz Campus</p></div></footer></body></html>'''


def write(relative, text):
    (ROOT / relative).write_text(text, encoding='utf-8')


def main():
    text = (ROOT / '.local/current-manuscript.txt').read_text(encoding='utf-8')
    start = text.index('CHAPTER I\nINTRODUCTION')
    end = text.index('\nAPPENDICES', start)
    body = text[start:end]
    private_start = body.index('3.4.1 Profile of System Testers')
    private_end = body.index('3.5 Respondents for User Evaluation', private_start)
    body = body[:private_start] + '3.4.1 Profile of System Testers\n[Personal tester profiles and Table 3.1 omitted from this public text derivative.]\n\n' + body[private_end:]
    preface = '''UNIORG MANUSCRIPT — PUBLIC TEXT DERIVATIVE

UNIORG: A Configurable University-Wide Student Organization Management Platform with OCR and NLP for Document Processing and Auto-Tagging
Symon Kiel G. Beato; Justine Mark R. Gahi
Adviser: Rachiel R. Rivano, PhD
College of Computer Studies, LSPU Santa Cruz Campus
BSIT — Web and Mobile Application Development
Title-page date: October 2026. Export retrieved: 2026-11-05.

This reading copy contains the main research text (Chapters I–V and literature cited), as exported from the researcher-supplied Google Doc. Personal tester profiles, embedded images, private appendices, signatures and CVs are excluded. Exported table cell text is retained in reading order; use the original document for formatted tables, figures and pagination. Source wording, including unresolved revisions, has not been silently corrected. See CONTENT_SOURCES.md for verification notes.

Original document: ''' + SOURCE_URL + '\n\n'
    write('research/manuscript/UNIORG-Manuscript-Public-Text.txt', preface + body)
    write('research/manuscript/README.md', '# Manuscript\n\nThe latest manuscript is the [researcher-supplied Google Doc](' + SOURCE_URL + '). Its October 2026 title page, identity details, objectives, methodology and results were verified on 2026-11-05.\n\nUNIORG-Manuscript-Public-Text.txt contains the main research text and literature cited. This is a public text derivative, not the signed or paginated original. Personal tester profiles, all embedded images, interviews, communication letters, signed forms, CVs and private appendices are excluded. Table cell text remains in export order. The original full PDF export is retained only in ignored .local/ and must not be uploaded.\n\nThe local FINAL_RC.pdf is superseded and excluded. For original formatting and full authorized access use Google Docs; no sharing permission was changed.\n')

    # Bake opaque redactions into actual screenshot pixels, preserving all other content.
    configs = [
        ('O01_OSAS_Dashboard_Operations_Overview.png','osas-dashboard', [(1515,6,1815,84)]),
        ('O04_OSAS_Accreditation_Renewal_List.png','accreditation', [(1515,6,1815,84),(892,579,1105,613),(892,665,1105,699)]),
        ('O10_OSAS_MasterCalendar_MonthView.png','master-calendar', [(1515,6,1815,84)]),
        ('O17_OSAS_DocLibrary_SOCCS_Documents.png','document-library', [(1500,6,1800,85)]),
        ('S08_Student_Library_List.png','e-library', [(1170,0,1366,66)]),
        ('S09_Student_EduShare_List.png','edushare', [(1170,0,1366,66)]),
    ]
    manifest = []
    for filename, slug, boxes in configs:
        src = ORIGINAL / 'storyboard_ss' / filename
        im = Image.open(src).convert('RGB')
        for x0,y0,x1,y1 in boxes:
            im.paste((23,54,46),(x0,y0,x1,y1))
        dst = ROOT / f'research/screenshots/{slug}.webp'
        im.save(dst, format='WEBP', lossless=True, method=6, exif=b'', icc_profile=None)
        manifest.append({'file':dst.relative_to(ROOT).as_posix(),'source':'storyboard_ss/'+filename,
                         'source_sha256':sha256(src.read_bytes()).hexdigest(),
                         'sha256':sha256(dst.read_bytes()).hexdigest(),'dimensions':list(im.size),
                         'redactions_xyxy':boxes,'metadata':'No EXIF or ICC metadata retained'})
    write('research/screenshots/manifest.json',json.dumps(manifest, indent=2))

    # Render verified diagram-only regions. PDF coordinates are in points.
    doc = fitz.open(ROOT / '.local/manuscript-complete.pdf')
    diagrams = [
        ('conceptual-framework',26,(100,422,555,675),'Figure 1.1. Conceptual framework (Input–Process–Output).'),
        ('system-context',155,(100,90,535,415),'Figure 4.1. System context diagram.'),
        ('system-architecture',155,(113,500,534,730),'Figure 4.2. System architecture.'),
        ('proposed-accreditation',335,(180,95,460,713),'Figure 67. Proposed organization accreditation process flowchart.'),
        ('use-case',336,(88,143,543,721),'Figure 68. Proposed UNIORG use case diagram.'),
    ]
    diagram_html = []
    for slug, index, box, caption in diagrams:
        path = ROOT / f'research/diagrams/{slug}.png'
        doc[index].get_pixmap(matrix=fitz.Matrix(2,2),clip=fitz.Rect(box)).save(path)
        im = Image.open(path)
        diagram_html.append(f'<figure><a href="{slug}.png"><img src="{slug}.png" width="{im.width}" height="{im.height}" alt="{escape(caption)}"></a><figcaption>{escape(caption)} Source: latest manuscript, PDF export page {index+1}. Select to open the full-size image.</figcaption></figure>')
    write('research/diagrams/index.html',page('Research diagrams','DIAGRAM COLLECTION','Original diagrams extracted from the researcher-supplied manuscript. These are research artifacts; the current implementation must be verified against the latest source code.',''.join(diagram_html)))
    write('research/diagrams/README.md','# Research diagrams\n\nExtracted from the latest Google Doc PDF export on 2026-11-05. Original wording and graphical content preserved. Coordinates and source pages are recorded in scripts/prepare_research.py.\n\n- Figure 1.1: export page 27\n- Figure 4.1 and 4.2: export page 156\n- Figure 67: export page 336\n- Figure 68: export page 337\n\nImages are cropped to the original diagram regions only; no private respondent data is included. Diagrams document the manuscript design and do not prove the pending latest source matches every component.\n')

    # Complete manual procedures from the current manuscript; unsafe embedded screenshots are omitted.
    manual_start = text.rindex("User's Manual")
    manual_end = text.index('Proof of Survey', manual_start)
    manual_text = text[manual_start:manual_end].strip()
    lines = [line.strip() for line in manual_text.splitlines() if line.strip()]
    role_headings = {'GENERAL INFORMATION','System Overview','Authorized Use Permission','Points of Contact (Help Desk)',
                     'Authentication and Account Setup','Student Users','Organization Admin and Officer Users (covers Adviser)','Dean Users','OSAS Users','Account Settings (All Users)'}
    content = ['<p><span class="tag">Text edition</span><span class="tag">Latest manuscript appendix</span></p><p>This public manual preserves the manuscript procedures and figure captions. Private embedded screenshots are omitted. Selected redacted interface screens are available in the <a href="../../index.html#system">system gallery</a>. Follow the actual permissions granted to your account. Interface details may change in the pending current source.</p><p><a href="UNIORG-User-Manual-Public-Text.txt" download>Download the complete text manual ↓</a> · <a href="'+SOURCE_URL+'" target="_blank" rel="noopener noreferrer">Original manuscript ↗</a></p>']
    in_steps = False
    for line in lines:
        if line.startswith('Step '):
            if not in_steps: content.append('<ol>'); in_steps=True
            cleaned = re.sub(r'^Step\s+\d+\.\s*','',line)
            content.append('<li>'+escape(cleaned)+'</li>')
            continue
        if in_steps: content.append('</ol>'); in_steps=False
        if line in role_headings: content.append('<h2>'+escape(line)+'</h2>')
        elif re.match(r'^Figure\s+\d+',line): content.append('<h3>'+escape(line)+'</h3>')
        elif line in {"User's Manual","USER'S MANUAL"} or line.startswith('UNIORG: A CONFIGURABLE'): continue
        else: content.append('<p>'+escape(line)+'</p>')
    if in_steps: content.append('</ol>')
    write('research/manuals/UNIORG-User-Manual-Public-Text.txt','UNIORG USER MANUAL — PUBLIC TEXT DERIVATIVE\nSource: latest manuscript appendix, retrieved 2026-11-05. Embedded screenshots omitted for privacy; procedures and source figure captions preserved.\n'+SOURCE_URL+'\n\n'+manual_text)
    write('research/manuals/index.html',page('UNIORG user manual','ROLE-BASED USER GUIDE','Account setup and procedures for students, organization administrators, deans, and OSAS personnel.',''.join(content)))
    write('research/manuals/README.md','# User manual\n\nThe HTML and downloadable text editions are derived from the latest manuscript manual appendix, retrieved 2026-11-05. They preserve all exported procedure text and captions while omitting embedded screenshots containing personal data. Selected redacted screenshots appear on the research website.\n\nThe older local User_Manual_UniOrg_v2.docx contains only 22 images and was not substituted for the latest appendix. USERMANUAL FINAL.pdf is a TUGON reference and is excluded. The original full exports remain ignored and are not public submission assets.\n')

    docs = '''<h2>Research and evidence</h2><ul><li><a href="CONTENT_SOURCES.md">Content verification and source register</a></li><li><a href="RESULTS.md">Reported results and limitations</a></li><li><a href="../manuscript/UNIORG-Manuscript-Public-Text.txt" download>Download public manuscript text</a> — Chapters I–V and literature cited; personal profiles, embedded images and private appendices omitted.</li><li><a href="MATERIALS_INVENTORY.json">Original materials inventory</a></li></ul>
<h2>Platform scope</h2><p>UNIORG is a configurable Laravel web platform for OSAS and student organizations. Its manuscript describes organization workspaces, accreditation, events and approvals, payment tracking, elections, academic resources, communication, role-based access and OCR/NLP document processing. The research covers LSPU Santa Cruz Campus.</p>
<h2>Installation and current source</h2><p>The previously recorded main branch was superseded by the researcher-confirmed branch. The researcher-confirmed Modification_based_Mia branch is included at revision 1cbf524. Follow <a href="../../source-code/README.md">the actual installation and deployment guide</a>, and review <a href="SOURCE_PROVENANCE.md">source provenance and sanitization</a>. Backend runtime validation remains pending the required local extensions and database.</p><p><a href="LEGACY_SOURCE_REVIEW.md">Read the provisional old-code review</a>. It records actual dependency manifests and conditional commands from the old source. It is retained only as historical comparison; use the confirmed snapshot guide for current setup.</p>
<h2>Website deployment</h2><p>The research website is static HTML, CSS and JavaScript, and needs no build. GitHub Pages can serve main from the root folder. The Laravel application requires separate backend hosting.</p><p><a href="PUBLISHING.md">GitHub Pages and submission procedure</a> · <a href="../../SUBMISSION_CHECKLIST.md">Submission checklist</a></p>
<h2>Verification</h2><p>Run <code>python scripts/verify_submission.py</code> from the repository root. See <a href="VERIFICATION.md">the verification record</a> for browser checks, downloads, privacy handling, external links and remaining limitations.</p>'''
    write('research/documentation/index.html',page('Research & system documentation','DOCUMENTATION HUB','Research evidence, platform scope, source-code status, and publication guidance for the submission package.',docs))
    write('research/documentation/SOURCE_HASHES.json', json.dumps({
        'retrieved':'2026-11-05','manuscript_url':SOURCE_URL,
        'text_export_sha256':sha256((ROOT/'.local/current-manuscript.txt').read_bytes()).hexdigest(),
        'pdf_export_sha256':sha256((ROOT/'.local/manuscript-complete.pdf').read_bytes()).hexdigest(),
        'pdf_pages':len(doc),'manual_figure_captions':len(re.findall(r'^\s*Figure\s+\d+',manual_text,re.M)),
        'private_original_exports_included':False},indent=2))
    print('Prepared manuscript text, manual, six redacted screenshots, five diagram extracts, and documentation pages.')


if __name__ == '__main__': main()
