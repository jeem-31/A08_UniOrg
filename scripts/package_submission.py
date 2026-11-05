"""Build a clean local ZIP of submission files, excluding all private workspaces."""
from pathlib import Path
from hashlib import sha256
import json
import subprocess
import sys
from zipfile import ZipFile, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]
ROOT_FILES = ['index.html','README.md','SUBMISSION_CHECKLIST.md','.gitignore','.gitattributes','.nojekyll']
PUBLIC_DIRS = ['assets','research','source-code','scripts']
EXCLUDE_DIRS = {'.git','.local','node_modules','vendor','__pycache__'}


def main():
    check = subprocess.run([sys.executable, str(ROOT/'scripts/verify_submission.py')], capture_output=True, text=True)
    if check.returncode:
        print(check.stdout, check.stderr)
        raise SystemExit('Submission checks failed; no archive created.')
    paths = [ROOT / name for name in ROOT_FILES]
    for name in PUBLIC_DIRS:
        paths += [p for p in (ROOT/name).rglob('*') if p.is_file() and not any(part in EXCLUDE_DIRS for part in p.relative_to(ROOT).parts)]
    paths = sorted(set(paths))
    for p in paths:
        if p.is_symlink() or not p.resolve().is_relative_to(ROOT):
            raise SystemExit('Refusing unexpected linked or external path: '+str(p))
    source = json.loads((ROOT/'research/documentation/SOURCE_MANIFEST.json').read_text(encoding='utf-8'))
    for entry in source['files'] + source.get('added_files', []):
        p = ROOT/'source-code'/entry['file']
        expected = entry.get('packaged_sha256', entry.get('sha256'))
        if sha256(p.read_bytes()).hexdigest() != expected:
            raise SystemExit('Source changed since audit: '+entry['file'])
    contents = [{'path':p.relative_to(ROOT).as_posix(),'bytes':p.stat().st_size,'sha256':sha256(p.read_bytes()).hexdigest()} for p in paths]
    release_dir = ROOT/'.local/releases'
    release_dir.mkdir(parents=True, exist_ok=True)
    archive = release_dir/'UNIORG-Submission-2026-11-05.zip'
    revision = 2
    while archive.exists() or (ROOT/'.local/quarantine-do-not-upload'/archive.name).exists():
        archive = release_dir/f'UNIORG-Submission-2026-11-05-r{revision}.zip'
        revision += 1
    with ZipFile(archive,'x',compression=ZIP_DEFLATED,compresslevel=9) as z:
        for p in paths:
            z.write(p,p.relative_to(ROOT).as_posix())
        z.writestr('PACKAGE_CONTENTS.json',json.dumps({'date':'2026-11-05','source_commit':source['commit'],'files':contents},indent=2))
    with ZipFile(archive) as z:
        if z.testzip() is not None:
            raise SystemExit('Archive CRC validation failed.')
        manifest = json.loads(z.read('PACKAGE_CONTENTS.json'))
        for entry in manifest['files']:
            if sha256(z.read(entry['path'])).hexdigest()!=entry['sha256']:
                raise SystemExit('Archive hash mismatch: '+entry['path'])
        for name in z.namelist():
            if any(part in EXCLUDE_DIRS for part in Path(name).parts):
                raise SystemExit('Excluded directory found in archive: '+name)
    print(json.dumps({'archive':str(archive),'bytes':archive.stat().st_size,'files':len(contents)+1,'sha256':sha256(archive.read_bytes()).hexdigest(),'crc_and_hash_validation':'passed','published':False},indent=2))


if __name__=='__main__':
    main()
