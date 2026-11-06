"""Serve the submission on loopback while keeping private working files inaccessible."""
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlsplit
import argparse
import re

ROOT = Path(__file__).resolve().parents[1]


class SubmissionHandler(SimpleHTTPRequestHandler):
    def __init__(self, *args, **kwargs):
        super().__init__(*args, directory=str(ROOT), **kwargs)

    def send_head(self):
        self._byte_range = None
        parts = Path(unquote(urlsplit(self.path).path)).parts
        if any(part.startswith('.') or part in {'node_modules', 'vendor', '__pycache__'} for part in parts):
            self.send_error(404)
            return None
        resolved = Path(self.translate_path(self.path)).resolve()
        if not resolved.is_relative_to(ROOT) or resolved.name.startswith('.'):
            self.send_error(404)
            return None
        if resolved.is_file() and resolved.suffix.lower() in {'.pdf', '.mp4'} and self.headers.get('Range'):
            size = resolved.stat().st_size
            match = re.fullmatch(r'bytes=(\d*)-(\d*)', self.headers['Range'].strip())
            start, end = 0, size - 1
            if match and any(match.groups()):
                first, last = match.groups()
                if first:
                    start = int(first)
                    end = min(int(last), size - 1) if last else size - 1
                else:
                    start = max(0, size - int(last))
            else:
                start = size
            if start >= size or start > end:
                self.send_response(416)
                self.send_header('Content-Range', f'bytes */{size}')
                self.send_header('Content-Length', '0')
                self.end_headers()
                return None
            stream = resolved.open('rb')
            stream.seek(start)
            self._byte_range = (start, end)
            self.send_response(206)
            self.send_header('Content-Type', self.guess_type(str(resolved)))
            self.send_header('Content-Range', f'bytes {start}-{end}/{size}')
            self.send_header('Content-Length', str(end - start + 1))
            self.send_header('Last-Modified', self.date_time_string(resolved.stat().st_mtime))
            self.end_headers()
            return stream
        return super().send_head()

    def end_headers(self):
        if urlsplit(self.path).path.lower().endswith(('.pdf', '.mp4')):
            self.send_header('Accept-Ranges', 'bytes')
        super().end_headers()

    def copyfile(self, source, outputfile):
        try:
            if self._byte_range is None:
                return super().copyfile(source, outputfile)
            start, end = self._byte_range
            remaining = end - start + 1
            while remaining:
                chunk = source.read(min(65536, remaining))
                if not chunk:
                    break
                outputfile.write(chunk)
                remaining -= len(chunk)
        except (BrokenPipeError, ConnectionResetError):
            # Media viewers cancel streams after seeking or negotiating byte ranges.
            pass

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
