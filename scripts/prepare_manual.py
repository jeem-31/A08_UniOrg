"""Prepare the illustrated public manual from reviewed, read-only research assets.

Requires PyMuPDF and Pillow. Original exports/screenshots remain unchanged.
Private source images never go into the submission; only redacted raster copies do.
"""
from pathlib import Path
from hashlib import sha256
from html import escape, unescape
import io
import json
import math
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / '.local/python'))
import pymupdf as fitz
from PIL import Image

ORIGINAL = Path(r'C:\Users\justine\Documents\screeshit\Capstone_Chapter-1-5')
PDF = ROOT / '.local/manuscript-complete.pdf'
OUT = ROOT / 'research/manuals'
# Manual figure -> visually verified source image beside this PDF figure.
# Several images are out of order in the manuscript; do not copy those mismatches.
PDF_IMAGES = {
    126:126, 127:125, 128:127, 129:129, 130:130, 132:132, 133:128,
    134:134, 135:133, 136:136, 137:135, 138:138, 139:139, 140:140,
    141:137, 142:142, 143:143, 144:141, 145:145, 146:146, 147:147,
    148:148, 149:149, 150:144, 151:151, 152:150, 153:153, 154:154,
    155:155, 156:156, 157:152, 158:158, 159:157, 160:159, 161:161,
    162:160, 163:163, 165:165, 166:164, 167:166, 168:168, 169:169,
    170:170, 171:171, 172:172, 173:173, 174:174, 175:175, 176:176,
    177:177, 178:178, 179:179, 180:180, 181:167, 182:181, 183:182, 184:184,
}
SUPPLEMENTS = {
    125:'user_manual/01_Auth_Login.png',
    131:'storyboard_ss/S04e_Student_Events_ListView.png',
    164:'user_manual/16_Dean_Dashboard_Home.png',
}
NOTES = {
    133:'Related screen: event details. The supplied image does not show the registration confirmation dialog described in the steps.',
    137:'Related screen: recorded ballot. The supplied image shows the result after voting, rather than the vote confirmation dialog described in the steps.',
}
# Coordinates are normalized to the original image. Cover values, not CSS layers.
MASKS = {
    127:[(.545,.315,.933,.361),(.642,.400,.734,.447)],
    128:[(.210,.380,.790,.448),(.70,.87,1,1)],
    129:[(.05,.19,.34,.26),(.71,.85,1,1)],
    130:[(.075,.875,.32,.95),(.375,.875,.70,.95)],
    135:[(.410,.435,.605,.505)],
    136:[(.44,.825,.76,1)],
    137:[(.07,.645,.96,1)],
    138:[(.065,.59,.36,1)],
    140:[(.07,.605,.33,.67),(.36,.605,.61,.67)],
    144:[(.29,.595,.955,.845)],
    148:[(.29,.525,.95,.565),(.29,.592,.79,.633),(.29,.660,.61,.704)],
    149:[(.294,.498,.94,.535),(.294,.555,.94,.592),(.294,.640,.61,.675),
         (.294,.765,.94,.802),(.294,.829,.94,.880),(.294,.905,.61,.944)],
    153:[(.075,.435,.23,.615),(.245,.435,.38,.615)],
    154:[(.078,.530,.96,1)],
    155:[(.065,.540,.96,1)],
    156:[(.105,.535,.36,.600)],
    159:[(.09,.265,.34,.65)],
    161:[(.26,.49,.73,.83)],
    162:[(.292,.306,.71,.531),(.115,.35,.28,.60)],
    163:[(.08,.650,.52,.800),(.565,.650,.77,.800)],
    164:[(.123,.177,.185,.201)],
    165:[(.081,.435,.36,1),(.391,.435,.74,1)],
    169:[(.08,.68,.23,.78)],
    170:[(.18,.628,.31,.651),(.18,.719,.31,.744),(.18,.811,.31,.838),(.18,.903,.31,.930)],
    171:[(.318,.505,.685,.557),(.21,.315,.281,.36)],
    174:[(.455,.51,.65,.665)],
    180:[(.36,.36,.77,1)],
    184:[(.475,.267,.68,.314),(.407,.531,.56,.560)],
}
STUDENT_FIGURES = set(range(128,143))
OSAS_FIGURES = set(range(173,185)) | {181}
ROLE_HEADINGS = {
    'Authentication and Account Setup':'Account setup',
    'Student Users':'Students',
    'Organization Admin and Officer Users (covers Adviser)':'Organization admins & officers',
    'Dean Users':'Deans',
    'OSAS Users':'OSAS',
    'Account Settings (All Users)':'Account settings',
}


def digest(data):
    return sha256(data).hexdigest()


def main():
    expected = json.loads((ROOT/'research/documentation/SOURCE_HASHES.json').read_text())['pdf_export_sha256']
    if digest(PDF.read_bytes()) != expected:
        raise SystemExit('PDF changed; verify source revision before proceeding.')
    doc = fitz.open(PDF)
    sources = {}
    for index in range(368,403):
        p = doc[index]
        nums = [int(n) for n in re.findall(r'Figure\s+(1[2-8]\d)\.',p.get_text()) if 125 <= int(n) <= 184]
        if not nums:
            continue
        images = sorted(p.get_image_info(xrefs=True),key=lambda x:x['bbox'][1])
        if len(nums)!=len(images):
            raise SystemExit('Ambiguous manual page: '+str(index+1))
        for number,image in zip(nums,images):
            cap = p.search_for('Figure '+str(number)+'.')
            if len(cap)!=1 or image['bbox'][3] > cap[0].y0+1:
                raise SystemExit('Ambiguous figure/image placement: '+str(number))
            sources[number]={'pdf_page':index+1,'xref':image['xref']}
    if set(sources)!=set(range(125,185)):
        raise SystemExit('Manual figures are incomplete.')
    html = (OUT/'index.html').read_text(encoding='utf-8')
    original_steps = re.findall(r'<ol>.*?</ol>',html,re.S)
    captions={int(n):unescape(c) for n,c in re.findall(r'<h3(?: id="[^"]+")?>Figure (\d+)\. (.*?)</h3>',html)}
    if set(captions)!=set(range(125,185)):
        raise SystemExit('Expected all 60 original manual captions.')
    (OUT/'images').mkdir(exist_ok=True)
    records=[]
    for number in range(125,185):
        source_figure = PDF_IMAGES.get(number)
        if source_figure:
            info=sources[source_figure]
            data=doc.extract_image(info['xref'])['image']
            provenance={'kind':'latest_manuscript_embedded_image','original_figure_position':source_figure,**info}
        else:
            relative=SUPPLEMENTS[number]
            data=(ORIGINAL/relative).read_bytes()
            provenance={'kind':'researcher_original_screenshot','relative_file':relative}
        with Image.open(io.BytesIO(data)) as original:
            width,height=original.size
        masks=list(MASKS.get(number,[]))
        if number in STUDENT_FIGURES:
            masks.append((.82,0,1,.105))
        if number in OSAS_FIGURES:
            masks.append((.79,0,.95,.075))
        # Use document redaction to remove underlying image pixels, then render.
        # No unredacted PDF/image objects are included in the public output.
        scratch=fitz.open()
        page=scratch.new_page(width=width,height=height)
        page.insert_image(page.rect,stream=data)
        boxes=[]
        for x0,y0,x1,y1 in masks:
            box=(math.floor(x0*width),math.floor(y0*height),math.ceil(x1*width),math.ceil(y1*height))
            boxes.append(box)
            page.add_redact_annot(fitz.Rect(box),fill=(.102,.204,.380),cross_out=False)
        if boxes:
            page.apply_redactions(images=2,graphics=0,text=0)
        clip=fitz.Rect(26,130,1362,1004) if number==125 else page.rect
        pix=page.get_pixmap(matrix=fitz.Matrix(1,1),clip=clip,alpha=False)
        destination=OUT/'images'/f'figure-{number}.webp'
        result=Image.frombytes('RGB',(pix.width,pix.height),pix.samples)
        result.save(destination,format='WEBP',lossless=True,method=6,exif=b'',icc_profile=None)
        scratch.close()
        records.append({'figure':number,'caption':captions[number],'image':'images/'+destination.name,
                        'source':provenance,'source_image_sha256':digest(data),
                        'sha256':digest(destination.read_bytes()),'dimensions':[pix.width,pix.height],
                        'redactions_source_xyxy':boxes,'source_dimensions':[width,height],
                        'crop_source_xyxy':list(clip) if number==125 else None,
                        'note':NOTES.get(number),'metadata':'No EXIF or ICC metadata retained'})
    # Idempotent augmentation; preserve the already verified text and all steps.
    html=re.sub(r'<figure class="manual-screen".*?</figure>','',html,flags=re.S)
    html=re.sub(r'<nav class="manual-nav".*?</nav>','',html,flags=re.S)
    html=html.replace('document-page"','document-page manual-page"')
    if 'assets/css/manual.css' not in html:
        html=html.replace('</head>','<link rel="stylesheet" href="../../assets/css/manual.css"><script src="../../assets/js/main.js" defer></script></head>')
    html=html.replace('<span class="tag">Text edition</span>','<span class="tag">Illustrated edition</span>')
    intro='<p class="manual-edition-note">All 60 procedures include verified UNIORG screen images. Blue masks permanently hide personal details. Images are aligned with the matching feature; two related screen states are explicitly identified below. Select any screenshot to enlarge it. Follow the actual permissions granted to your account.</p>'
    html=re.sub(r'<p>This public manual preserves.*?</p>',intro,html,count=1,flags=re.S)
    html=re.sub(r'<p class="manual-edition-note">.*?</p>',lambda _:intro,html,count=1,flags=re.S)
    by_number={r['figure']:r for r in records}
    def illustrate(match):
        number=int(match[1]);caption=unescape(match[2]);record=by_number[number]
        width,height=record['dimensions']
        note=record['note'] or 'Personal details are masked where present.'
        return (f'<h3 id="figure-{number}">Figure {number}. {escape(caption)}</h3>'
                f'<figure class="manual-screen" aria-labelledby="manual-caption-{number}">'
                f'<a href="{record["image"]}" data-viewer data-caption="Figure {number}. {escape(caption,quote=True)} {escape(note,quote=True)}">'
                f'<img src="{record["image"]}" alt="{escape(caption,quote=True)} {escape(note,quote=True)}" width="{width}" height="{height}" loading="lazy" decoding="async">'
                '<span class="manual-enlarge">Enlarge screenshot &#8599;</span></a>'
                f'<figcaption id="manual-caption-{number}">{escape(note)}</figcaption></figure>')
    html=re.sub(r'<h3(?: id="[^"]+")?>Figure (\d+)\. (.*?)</h3>',illustrate,html)
    def heading(match):
        label=unescape(match[1]);slug='manual-'+re.sub(r'[^a-z0-9]+','-',label.lower()).strip('-')
        return f'<h2 id="{slug}">{escape(label)}</h2>'
    html=re.sub(r'<h2(?: id="manual-[^"]+")?>(.*?)</h2>',heading,html)
    nav=['<nav class="manual-nav" aria-label="User manual sections"><strong>Jump to your role</strong><ul>']
    for label,short in ROLE_HEADINGS.items():
        slug='manual-'+re.sub(r'[^a-z0-9]+','-',label.lower()).strip('-')
        nav.append(f'<li><a href="#{slug}">{escape(short)}</a></li>')
    nav.append('</ul></nav>')
    html=html.replace('<h2 id="manual-general-information">',''.join(nav)+'<h2 id="manual-general-information">',1)
    if '<dialog class="viewer"' not in html:
        viewer=re.search(r'<dialog class="viewer".*?</dialog>',(ROOT/'index.html').read_text(encoding='utf-8'),re.S).group()
        html=html.replace('</body>',viewer+'</body>')
    assert len(re.findall('class="manual-screen"',html))==60
    assert re.findall(r'<ol>.*?</ol>',html,re.S)==original_steps, 'Procedure text changed unexpectedly.'
    (OUT/'index.html').write_text(html,encoding='utf-8')
    manifest={'source_manuscript_url':'https://docs.google.com/document/d/1sbe3vWyPPwBdHfYA2C-CvQsmWJFs848xwl2uu5pv7zo/edit?usp=sharing',
              'source_pdf_sha256':expected,'figures':60,'mapping_review':'Images aligned by visible feature, not blindly by source caption placement.',
              'privacy':'Opaque redactions are baked into exported raster pixels. No source images, PDF layers or metadata are packaged.',
              'images':records}
    (OUT/'images-manifest.json').write_text(json.dumps(manifest,indent=2),encoding='utf-8')
    (OUT/'README.md').write_text('''# Illustrated UNIORG user manual

Open [the illustrated manual](index.html) for all 60 procedures (Figures 125-184), with a screenshot alongside each procedure, role navigation and an enlargeable screenshot viewer. The separate downloadable text file preserves the original procedure wording and captions.

## Verified image sources

The latest researcher-supplied manuscript appendix provides the procedures and 57 embedded screen images. Three matching original research screenshots supplement the login, student event list and dean dashboard. Some appendix image positions do not match their captions; the public manual aligns images by the actual visible feature. Original files remain unchanged. Each source, image hash, crop and redaction box is recorded in [the image manifest](images-manifest.json).

Two supplied images show related states rather than the named modal: Figure 133 shows event details, and Figure 137 shows the recorded ballot after voting. Each is explicitly identified in its caption note. No missing dialog or screen has been fabricated.

## Privacy

Names, account identities, student IDs, emails, payment references, personal document previews and complaint records are covered where present. Opaque masks are baked into exported image pixels; the public files contain no hidden original image/PDF layers or EXIF/ICC metadata. The source PDF and unredacted screenshots stay outside the public package.

The older 22-image manual document and the TUGON reference PDF are not used as the procedure source. The text edition contains no images; use the HTML edition and its images/ directory for the illustrated manual.

## Rebuild

After installing PyMuPDF and Pillow, run `python scripts/prepare_manual.py` from the repository root. The script requires the ignored, verified manuscript PDF and the original research screenshot folder. It checks the source PDF hash, matches the 60 captions, applies the reviewed masks, preserves every existing procedure list and writes only submission derivatives. Browser visual/interactivity checks remain documented separately from image review and file checks.
''',encoding='utf-8')
    print(json.dumps({'manual_images':len(records),'from_latest_manuscript':len(PDF_IMAGES),
                      'supplemental_original_screenshots':len(SUPPLEMENTS),'related_screen_notes':len(NOTES),
                      'image_bytes':sum((OUT/r['image']).stat().st_size for r in records)},indent=2))


if __name__=='__main__':
    main()
