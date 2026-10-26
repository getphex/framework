#!/usr/bin/env python3
"""Compare PHP and native ElephC adapters over real local HTTP."""

from contextlib import contextmanager
import http.client
import os
import signal
import sys
from pathlib import Path
import socket
import subprocess
import tempfile
import time


ROOT = Path(__file__).resolve().parents[2]


@contextmanager
def server(command):
    with socket.socket() as reservation:
        reservation.bind(("127.0.0.1", 0))
        port = reservation.getsockname()[1]
    with tempfile.TemporaryFile(dir=ROOT / "var") as log:
        process = subprocess.Popen(
            [part.replace("{port}", str(port)) for part in command],
            cwd=ROOT, stdout=log, stderr=log, start_new_session=True,
        )
        try:
            deadline = time.monotonic() + 10
            while True:
                if process.poll() is not None:
                    raise RuntimeError("The HTTP test server exited before startup.")
                try:
                    with socket.create_connection(("127.0.0.1", port), timeout=0.2):
                        break
                except OSError:
                    if time.monotonic() >= deadline:
                        raise RuntimeError("Timed out starting the HTTP test server.")
                    time.sleep(0.05)
            yield port
        except Exception:
            log.seek(0)
            print(log.read().decode(errors="replace"))
            raise
        finally:
            try:
                os.killpg(process.pid, signal.SIGTERM)
            except ProcessLookupError:
                pass
            try:
                process.wait(timeout=5)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGKILL)
                process.wait()
            finally:
                # A prefork server can leave a worker behind if its master exits early.
                try:
                    os.killpg(process.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass


def request(port: int, method: str, path: str, body=None, headers=None):
    connection = http.client.HTTPConnection("127.0.0.1", port, timeout=5)
    try:
        connection.request(method, path, body=body, headers=headers or {})
        response = connection.getresponse()
        return response.status, response.getheaders(), response.read()
    finally:
        connection.close()


def fields(headers, name: str):
    return [value for key, value in headers if key.lower() == name.lower()]


def check(condition: bool, message: str):
    if not condition:
        raise RuntimeError(message)


def check_application(port):
    for identity in ("alice", "bob", "anonymous"):
        status, headers, body = request(port, "GET", "/users/7?ignored=1", headers={} if identity == "anonymous" else {"X-Request-Id": identity})
        check((status, body) == (200, b"user-7"), "Compiled application did not serve the user route.")
        check(fields(headers, "X-Request-Id") == [identity], "Endpoint request state leaked.")
        check(fields(headers, "X-Middleware-Id") == [identity], "Middleware request state leaked.")
        check(fields(headers, "Content-Type") == ["text/plain; charset=utf-8"], "Missing content type.")
    for method, path, expected_status, expected_body in (
        ("GET", "/missing", 404, b"Not Found"),
        ("POST", "/users/7", 405, b"Method Not Allowed"),
        ("HEAD", "/users/7", 405, b""),
        ("GET", "/users/nope", 400, b"Bad Request"),
        ("GET", "/users/%ZZ", 400, b"Bad Request"),
    ):
        status, headers, body = request(port, method, path)
        check((status, body) == (expected_status, expected_body), f"Unexpected response for {method} {path}.")
        if status == 405:
            check(fields(headers, "Allow") == ["GET"], "Missing Allow header.")


def check_transport(port):
    raw = b"a\x00\xff"
    status, headers, body = request(port, "POST", "/echo?raw=a%2Fb", raw,
                                    {"X-Test": "alice", "Content-Type": "application/octet-stream"})
    check((status, body) == (418, b"POST|/echo?raw=a%2Fb|alice|" + raw), "SAPI metadata or binary body changed.")
    check(fields(headers, "Set-Cookie") == ["first=1; Path=/", "second=2; Path=/"], "Repeated fields were combined.")
    check(fields(headers, "Location") == ["/target"], "Location was lost.")
    check(fields(headers, "Content-Length") == [str(len(body))], "Incorrect buffered content length.")
    status, headers, body = request(port, "HEAD", "/echo", headers={"X-Test": "head"})
    check((status, body) == (418, b""), "HEAD emitted content or changed status.")
    check(fields(headers, "Content-Length") == [str(len(b"HEAD|/echo|head|"))], "HEAD representation length changed.")
    for path, expected_status in (("/no-content", 204), ("/reset-content", 205), ("/not-modified", 304)):
        status, headers, body = request(port, "GET", path)
        check((status, body) == (expected_status, b""), f"Unexpected content for {expected_status}.")
        expected_length = ["0"] if expected_status == 205 else []
        check(fields(headers, "Content-Length") == expected_length, "Unexpected bodyless response framing.")
    status, headers, body = request(port, "GET", "/echo")
    check((status, body) == (418, b"GET|/echo||"), "Body or header state leaked into the next request.")


def php_command(router):
    return ["php", "-d", "display_errors=0", "-d", "zlib.output_compression=0",
            "-S", "127.0.0.1:{port}", "-t", str(router.parent), str(router)]


def main():
    targets = (
        (ROOT / "examples/compiled-container/public/index.php", ROOT / "examples/compiled-container/native.php", "application", check_application),
        (ROOT / "tests/Runtime/Fixtures/sapi.php", ROOT / "tests/Runtime/Fixtures/native.php", "transport", check_transport),
    )
    with tempfile.TemporaryDirectory(prefix="http-native-", dir=ROOT / "var") as directory:
        for php_source, native_source, label, verify in targets:
            with server(php_command(php_source)) as port:
                verify(port)
            entry = Path(directory) / (label + ".php")
            subprocess.run([sys.executable, str(ROOT / "tools/builds/elephc_web.py"),
                            "--source", str(native_source), "--output", str(entry)], cwd=ROOT, check=True)
            with server([str(entry.with_suffix("")), "--listen", "127.0.0.1:{port}", "--workers", "1"]) as port:
                verify(port)
            print(f"HTTP {label} verified on PHP and ElephC.", flush=True)
    print("HTTP verified: PHP and native ElephC servers passed the same socket checks.")

if __name__ == "__main__":
    main()
