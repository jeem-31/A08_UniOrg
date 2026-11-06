"""Copy the user-confirmed source revision while excluding private/runtime data.

The checkout is a read-only input. Sanitization is applied only to the submission
copy, with a per-file hash and change log. Never silently overwrite source files.
"""
from pathlib import Path
from hashlib import sha256
import json
import os
import re
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / '.local/latest-source'
DST = ROOT / 'source-code'
OMIT_DIRS = {'.git','.codex','.kiro','.skills','node_modules','vendor','SOCCSyncs','video_remotion','video_trial','__pycache__'}
OMIT_FILES = {'AGENTS.md','opencode.json','.graphifyignore','TEST-ACCOUNTS.md','DATABASE_BACKUP.md','APPLY_AUTOFILL_SOP.md','RENEW_AUTOFILL_SOP.md','TESTING-RESULTS.md'}
changes, excluded, hashes = [], [], []


def main():
    revision = subprocess.check_output(['git','-C',str(SRC),'rev-parse','HEAD'],text=True).strip()
    if revision != '1cbf524b77947010041bf3fdbdbbfd7412e06176':
        raise SystemExit('Source revision changed; review before packaging.')
    if (ROOT / 'research/documentation/SOURCE_MANIFEST.json').exists():
        raise SystemExit('Source already packaged; refuse to overwrite without review.')
    for current, dirs, names in os.walk(SRC):
        rel_dir = Path(current).relative_to(SRC)
        for d in list(dirs):
            if d in OMIT_DIRS or (rel_dir == Path('public') and d == 'build'):
                excluded.append((rel_dir/d).as_posix()+'/'); dirs.remove(d)
        for name in names:
            rel = rel_dir / name
            p = SRC / rel
            if (name in OMIT_FILES or name.startswith('.env') or p.suffix.lower() in {'.sqlite','.sqlite3','.mp4','.webm','.pyc','.pem','.key'}
                or (rel.parts[0]=='storage' and name!='.gitignore')
                or (rel.parts[:2]==('bootstrap','cache') and name!='.gitignore')
                or name in {'token.json','client_secret.json','credentials.json'}):
                excluded.append(rel.as_posix()); continue
            out = DST / rel; out.parent.mkdir(parents=True,exist_ok=True)
            data = p.read_bytes()
            text = None
            if b'\0' not in data:
                try: text = data.decode('utf-8')
                except UnicodeError: pass
            original = text
            if rel.as_posix() == 'resources/js/env-config.js':
                text = re.sub(r"(VITE_PUSHER_APP_KEY:\s*)'[^']*'",r"\1''",text)
                text = re.sub(r"(VITE_SUPABASE_URL:\s*)'[^']*'",r"\1''",text)
                text = re.sub(r"(VITE_SUPABASE_ANON_KEY:\s*)'[^']*'",r"\1''",text)
                text = text.replace('with built-in defaults','from environment variables').replace('Keeping defaults in source means the frontend build works everywhere','Deployment-specific values are intentionally omitted from this submission.\n * Set VITE_* variables before building')
            if rel.as_posix() in {'database/seeders/DatabaseSeeder.php','database/seeders/OsasAdminSeeder.php'}:
                text = re.sub(r"Hash::make\('[^']+'\)",lambda m: "Hash::make(\\Illuminate\\Support\\Str::random(40))",text)
                text = re.sub(r"^.*\$this->command->info\('Password:.*\n",'',text,flags=re.M)
            if rel.as_posix() == 'app/Console/Commands/CreateOsasAdmin.php':
                text = text.replace('Enter password (leave empty for default)','Enter a password of at least 12 characters')
                text = re.sub(r"if \(empty\(\$password\)\) \{.*?\n        \}", "if (empty($password) || strlen($password) < 12) {\n            $this->error('A password of at least 12 characters is required.');\n            return 1;\n        }",text, count=1,flags=re.S)
                text = text.replace("                ['Password', $password],\n",'')
            if rel.as_posix() == 'app/Console/Commands/TestEmailNotifications.php':
                text = re.sub(r'\{email=[^}]+\}', '{email : Explicit test recipient}',text)
                text = re.sub(r'\$password = "[^"]+";',r'$password = \\Illuminate\\Support\\Str::random(32);',text)
                text = re.sub(r'\$requesterName = "[^"]+";', '$requesterName = "Demo Requester";',text)
            if rel.as_posix() in {'app/Console/Commands/TestSubmissionCommand.php','resources/views/welcome.blade.php'}:
                text = re.sub(r'[A-Za-z0-9._%+-]+@(?:gmail|yahoo|hotmail|outlook)\.com','demo.contact@example.invalid',text,flags=re.I)
            if rel.as_posix() == 'docs/AI-SYSTEM-SUMMARY.txt':
                text = re.sub(r'^(GOOGLE_GEMINI_API_KEY|GOOGLE_CLOUD_PROJECT_ID|GOOGLE_CLOUD_KEY_FILE|GOOGLE_APPLICATION_CREDENTIALS|GOOGLE_CLOUD_SERVICE_ACCOUNT_KEY)=[^\r\n]*',r'\1=',text,flags=re.M)
            if text is not None and text != original:
                changes.append({'file':rel.as_posix(),'reason':'Remove deployment identifiers, fixed bootstrap credentials or private test recipient; see SOURCE_PROVENANCE.md.'})
                data=text.encode('utf-8')
            if text is not None and re.search(r'AIza[0-9A-Za-z_-]{30,}|AQ\.[A-Za-z0-9_.-]{30,}|gh[pousr]_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|gsk_[A-Za-z0-9]{30,}|AKIA[A-Z0-9]{16}|eyJ[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{15,}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----',text):
                raise SystemExit('Review possible token before copying '+rel.as_posix())
            if out.exists() and out.read_bytes() not in (data, p.read_bytes()):
                raise SystemExit('Existing file differs from reviewed input: '+rel.as_posix())
            out.write_bytes(data)
            hashes.append({'file':rel.as_posix(),'upstream_sha256':sha256(p.read_bytes()).hexdigest(),'packaged_sha256':sha256(data).hexdigest()})
    env = '''# Sanitized local configuration. Fill secrets privately; never commit .env.
APP_NAME=UniOrg
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
LOG_CHANNEL=stack
LOG_LEVEL=debug
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=uniorg_local
DB_USERNAME=uniorg_local
DB_PASSWORD=
CACHE_DRIVER=file
CONTENT_CACHE_TTL=300
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=false
FILESYSTEM_DISK=public
QUEUE_CONNECTION=sync
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
REDIS_CACHE_DB=1
BROADCAST_CONNECTION=log
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@example.invalid
MAIL_FROM_NAME=UniOrg
GMAIL_SENDER=
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REFRESH_TOKEN=
RESEND_KEY=
TURNSTILE_ENABLED=false
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
TURNSTILE_HOSTNAMES=127.0.0.1,localhost
GOOGLE_CLOUD_VISION_ENABLED=false
GOOGLE_CLOUD_NLP_ENABLED=false
GOOGLE_GEMINI_API_KEY=
GROQ_API_KEY=
GROQ_API_KEYS=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=auto
AWS_BUCKET=
AWS_ENDPOINT=
AWS_URL=
AWS_USE_PATH_STYLE_ENDPOINT=true
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=ap1
PUSHER_SCHEME=https
PUSHER_PORT=443
VITE_APP_NAME=UniOrg
VITE_PUSHER_APP_KEY=
VITE_PUSHER_APP_CLUSTER=ap1
VITE_SUPABASE_URL=
VITE_SUPABASE_ANON_KEY=
'''
    (DST/'.env.example').write_text(env,encoding='utf-8')
    report={'repository':'https://github.com/zorojuro0101/-UniOrg','branch':'main','branch_scope':'Official repository reference; commit records the imported snapshot, not the current main tip.','commit':revision,'confirmed_by':'researcher in chat, 2026-11-05','copied_files':len(hashes),'excluded':excluded,'sanitized_changes':changes,'files':hashes}
    (ROOT/'research/documentation/SOURCE_MANIFEST.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
    print(json.dumps({'revision':revision,'copied_files':len(hashes),'excluded_items':len(excluded),'sanitized_files':changes},indent=2))


if __name__=='__main__': main()
