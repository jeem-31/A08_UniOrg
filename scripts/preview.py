"""Serve the submission on loopback while keeping private working files inaccessible."""
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlsplit
import argparse

ROOT = Path(__file__).resolve().parents[1]


class SubmissionHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def send_head(self):
        parts = Path(unquote(urlsplit(self.path).path)).parts
        if any(part.startswith('.') or part in {'node_modules', 'vendor', '__pycache__'} for part in parts):
            self.send_error(404)
            return None
        resolved = Path(self.translate_path(self.path)).resolve()
        if not resolved.is_relative_to(ROOT) or resolved.name.startswith('.'):
            self.send_error(404)
            return None
        return super().send_head()

    def list_directory(self, path):
        self.send_error(404, 'Directory listing is disabled; open a named file.')
        return None


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--port', type=int, default=4173)
    args = parser.parse_args()
    with ThreadingHTTPServer(('127.0.0.1', args.port), SubmissionHandler) as server:
        print(f'UNIORG local preview: http://127.0.0.1:{args.port}/', flush=True)
        try:
            server.serve_forever()
        except KeyboardInterrupt:
            pass
