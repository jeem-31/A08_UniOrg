"""Standard-library checks for a local, static research submission."""
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit
import json
import os
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
EXCLUDED = {'.local', '.git', 'notes', '__pycache__'}


class Page(HTMLParser):
    def __init__(self, path):
        super().__init__()
        self.path, self.links, self.ids, self.images = path, [], set(), []
        self.feed(path.read_text(encoding='utf-8'))

    def handle_starttag(self, tag, attrs):
        a = dict(attrs)
        if 'id' in a:
            if a['id'] in self.ids:
                errors.append(f'Duplicate id: {self.path}: {a["id"]}')
            self.ids.add(a['id'])
        for attr in ('href', 'src'):
            if a.get(attr):
                self.links.append(a[attr])
        if tag == 'img':
            self.images.append(a)


errors, checked_links = [], 0
files = []
for current, directories, names in os.walk(ROOT, followlinks=False):
    folder = Path(current)
    for name in list(directories):
        path = folder / name
        if path.is_symlink():
            errors.append(f'Unexpected linked directory: {path.relative_to(ROOT)}')
            directories.remove(name)
        elif name == '.git':
            if folder != ROOT:
                errors.append(f'Nested Git directory: {path.relative_to(ROOT)}')
            directories.remove(name)
        elif name in {'.local', 'notes', '__pycache__'}:
            directories.remove(name)
    files.extend(folder / name for name in names)
pages = {p: Page(p) for p in files if p.suffix == '.html' and 'source-code' not in p.relative_to(ROOT).parts}
for path, page in pages.items():
    for link in page.links:
        u = urlsplit(link)
        if u.scheme or u.netloc:
            continue
        target = (path.parent / unquote(u.path)).resolve() if u.path else path
        if target.is_dir():
            target = target / 'index.html'
        checked_links += 1
        if not target.is_relative_to(ROOT) or not target.is_file():
            errors.append(f'Broken local link: {path.relative_to(ROOT)} -> {link}')
        elif u.fragment and target in pages and unquote(u.fragment) not in pages[target].ids:
            errors.append(f'Broken anchor: {path.relative_to(ROOT)} -> {link}')
    for img in page.images:
        if 'alt' not in img:
            errors.append(f'Image missing alt: {path.relative_to(ROOT)} {img.get("src")}')
        if img.get('src') and (not img.get('width') or not img.get('height')):
            errors.append(f'Image dimensions missing: {path.relative_to(ROOT)} {img["src"]}')

required = ['index.html','README.md','SUBMISSION_CHECKLIST.md','.gitignore','.nojekyll',
            'assets/css','assets/js','assets/images','research/manuscript','research/documentation',
            'research/diagrams','research/screenshots','research/manuals','source-code']
for item in required:
    if not (ROOT / item).exists():
        errors.append(f'Missing required path: {item}')

for p in files:
    rel = p.relative_to(ROOT)
    name = p.name.lower()
    if 'node_modules' in rel.parts or 'vendor' in rel.parts or ('.git' in rel.parts and len(rel.parts) > 1):
        errors.append(f'Forbidden directory: {rel}')
    if p.is_symlink():
        errors.append(f'Unexpected linked file: {rel}')
    if (name.startswith('.env') and name != '.env.example') or name in {'token.json','credentials.json','auth.json'} or (name.startswith('client_secret') and name.endswith('.json')):
        errors.append(f'Forbidden credential file: {rel}')
    if p.suffix.lower() in {'.sqlite','.sqlite3','.pem','.key'}:
        errors.append(f'Forbidden private data/key file: {rel}')
    if p.stat().st_size > 95 * 1024 * 1024:
        errors.append(f'File too large for ordinary GitHub upload: {rel}')
    data = p.read_bytes()
    if b'\0' not in data:
        text = data.decode('utf-8', errors='ignore')
        # Match strong credential signatures only; not generic documentation placeholders.
        if re.search(r'AIza[0-9A-Za-z_-]{30,}|AQ\.[A-Za-z0-9_.-]{30,}|gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|gsk_[A-Za-z0-9]{30,}|AKIA[A-Z0-9]{16}|eyJ[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{15,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----', text):
            errors.append(f'Possible embedded secret: {rel}')

report = {'html_pages':len(pages),'checked_local_links':checked_links,'public_files':len(files),
          'errors':errors,'external_links':'not checked by this offline script',
          'source_code':'Modification_based_Mia at 1cbf524b77947010041bf3fdbdbbfd7412e06176'}
print(json.dumps(report, indent=2))
sys.exit(bool(errors))
