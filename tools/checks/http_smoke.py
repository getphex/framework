#!/usr/bin/env python3
"""Compare PHP and native ElephC adapters over real local HTTP."""

from contextlib import contextmanager
import http.client
import json
import os
import shutil
import signal
import sys
from pathlib import Path
import socket
import subprocess
import tempfile
import time


ROOT = Path(__file__).resolve().parents[2]


@contextmanager
def server(command, *, error_log_marker=None, forbidden_log_marker=None, env=None, log_check=None):
    with socket.socket() as reservation:
        reservation.bind(("127.0.0.1", 0))
        port = reservation.getsockname()[1]
    with tempfile.TemporaryFile(dir=ROOT / "var") as log:
        process = subprocess.Popen(
            [part.replace("{port}", str(port)) for part in command],
            cwd=ROOT, stdout=log, stderr=log, start_new_session=True, env=env,
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
            if error_log_marker is not None or forbidden_log_marker is not None or log_check is not None:
                log.seek(0)
                contents = log.read()
                if error_log_marker is not None and error_log_marker.encode() not in contents:
                    raise RuntimeError('The application error was not reported to the server log.')
                if forbidden_log_marker is not None and forbidden_log_marker.encode() in contents:
                    raise RuntimeError('The server log exposed the configuration value.')
                if log_check is not None:
                    log_check(contents.decode(errors="replace"))
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
    check_create_user(port)


def check_create_user(port):
    for media in ("application/json", "Application/JSON; charset=utf-8", "application/vnd.phex.user+json"):
        status, headers, body = request(port, "POST", "/users", b'{"name":"  Ada  "}',
                                        {"Content-Type": media, "X-Request-Id": "creator"})
        check((status, body) == (201, b'{"id":1,"name":"Ada"}'), "Typed JSON creation failed.")
        check(fields(headers, "Content-Type") == ["application/json"], "JSON content type was overwritten.")
        check(fields(headers, "Location") == ["/users/1"], "Created resource location was lost.")
        check(fields(headers, "Content-Length") == [str(len(body))], "JSON byte length is incorrect.")
        check(fields(headers, "X-Request-Id") == ["creator"], "Creation endpoint used the wrong request session.")
        check(fields(headers, "X-Middleware-Id") == ["creator"], "Creation middleware used the wrong session.")
    for payload, media, expected_status, code in (
        (b'{', "application/json", 400, "malformed_json"),
        (b'{"name":"\xff"}', "application/json", 400, "malformed_json"),
        (b'{}', "application/json", 422, "invalid_input"),
        (b'[]', "application/json", 422, "invalid_input"),
        (b'{"name":42}', "application/json", 422, "invalid_input"),
        (b'{"name":92233720368547758080}', "application/json", 422, "invalid_input"),
        (b'{"name":"Ada","role":"admin"}', "application/json", 422, "invalid_input"),
        (b'{"name":"Ada"}', "text/plain", 415, "unsupported_media_type"),
        (b'{"name":"Ada"}', None, 415, "unsupported_media_type"),
    ):
        status, headers, body = request(port, "POST", "/users", payload,
                                        {} if media is None else {"Content-Type": media})
        decoded = json.loads(body)
        check((status, decoded["error"]) == (expected_status, code), "JSON input failure changed across runtimes.")
        check(fields(headers, "Content-Type") == ["application/json"], "JSON error has the wrong content type.")
        check(fields(headers, "X-Middleware-Id") == ["anonymous"], "JSON error retained an earlier request session.")
        if status == 422:
            check(bool(decoded["errors"]), "Validation response has no field errors.")
    status, headers, body = request(port, "POST", "/users", b'{"name":"Caf\\u00e9"}',
                                    {"Content-Type": "application/json"})
    check((status, json.loads(body)) == (201, {"id": 1, "name": "Caf\u00e9"}), "Unicode creation failed after rejected inputs.")
    status, headers, body = request(port, "GET", "/users")
    check(status == 405 and fields(headers, "Allow") == ["POST"], "POST route method metadata is incorrect.")


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
    return ["php", "-d", "display_errors=0", "-d", "log_errors=1", "-d", "zlib.output_compression=0", "-d", "opcache.enable=0",
            "-S", "127.0.0.1:{port}", "-t", str(router.parent), str(router)]


def check_development():
    # Use real relocated Composer dependencies, with no generated directory at all.
    with tempfile.TemporaryDirectory(prefix="http-development-", dir=ROOT / "var") as directory:
        project = Path(directory)
        for name in ("src", "vendor", "examples/compiled-container"):
            shutil.copytree(ROOT / name, project / name)
        for name in ("composer.json", "composer.lock"):
            shutil.copy2(ROOT / name, project / name)
        example = project / "examples/compiled-container"
        with server(php_command(example / "public/index.php")) as port:
            check_application(port)
            config = example / "container.php"
            original = config.read_text()
            changed = original.replace("'/users/{id}'", "'/people/{id}'")
            check(changed != original, "Route edit did not change the development fixture.")
            config.write_text(changed)
            check(request(port, "GET", "/users/7")[0] == 404, "Removed route remained cached in development.")
            check(request(port, "GET", "/people/7")[0:3:2] == (200, b"user-7"), "New route required a build.")
            (example / "DevelopmentSessionFactory.php").write_text(
                "<?php\ndeclare(strict_types=1);\n"
                "namespace Phex\\Examples\\CompiledContainer;\n"
                "final class DevelopmentSessionFactory {\n"
                "    public static function create(): RequestSession { return new RequestSession('changed'); }\n"
                "}\n"
            )
            replacement = changed.replace(
                "Service::factory(RequestSession::class, Lifetime::Request, Factories::class, 'session')",
                "Service::factory(RequestSession::class, Lifetime::Request, "
                "\\Phex\\Examples\\CompiledContainer\\DevelopmentSessionFactory::class, 'create')",
            )
            check(replacement != changed, "Service edit did not change the development fixture.")
            config.write_text(replacement)
            status, headers, body = request(port, "GET", "/people/7")
            check((status, body) == (200, b"user-7"), "Changed provider required a build.")
            check(fields(headers, "X-Request-Id") == ["changed"], "Endpoint did not receive the changed provider.")
            check(fields(headers, "X-Middleware-Id") == ["changed"], "Middleware did not share the changed provider.")
        check(not (project / "var/generated").exists(), "Development wrote generated artifacts.")
    print("PHP development verified without artifacts, including route and provider edits without a restart.", flush=True)


def check_native_isolation():
    # Compile a self-contained distribution where development/build sources cannot be loaded.
    with tempfile.TemporaryDirectory(prefix="http-isolation-", dir=ROOT / "var") as directory:
        project = Path(directory)
        shutil.copytree(ROOT / "src", project / "src", ignore=shutil.ignore_patterns("Development", "Build"))
        for name in ("hello-world", "json-api", "request-lifecycle", "compiled-container"):
            shutil.copytree(ROOT / "examples" / name, project / "examples" / name,
                            ignore=shutil.ignore_patterns("public", "container.php"))
        artifact = Path("var/generated/example/ApplicationContainer.php")
        (project / artifact).parent.mkdir(parents=True)
        shutil.copy2(ROOT / artifact, project / artifact)
        (project / "composer.json").write_text(json.dumps({"autoload": {"psr-4": {"Phex\\": "src/"}}}))
        entry = project / "server.php"
        entry.write_text("<?php\ndeclare(strict_types=1);\nrequire __DIR__.'/examples/compiled-container/native.php';\n")
        check(not (project / "src/Runtime/Development").exists(), "Native fixture includes the development runtime.")
        check(not (project / "src/Build").exists(), "Native fixture includes build analysis.")
        check(not (project / "vendor").exists(), "Native fixture includes Composer dependencies.")
        subprocess.run(["elephc", "--web", "--strict-php", str(entry)], cwd=project, check=True)
        with server([str(entry.with_suffix("")), "--listen", "127.0.0.1:{port}", "--workers", "1"]) as port:
            check_application(port)
    print("Native graph isolation verified without development runtime, build sources, configuration, or vendor.", flush=True)


def main():
    check_development()
    targets = (
        (ROOT / "examples/compiled-container/public/compiled.php", ROOT / "examples/compiled-container/native.php", "application", check_application),
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
    check_native_isolation()
    print("HTTP verified: PHP and native ElephC servers passed the same socket checks.")

if __name__ == "__main__":
    main()
