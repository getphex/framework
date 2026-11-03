#!/usr/bin/env python3
"""Install and run the portable application outside the framework checkout."""

import json
import os
from contextlib import contextmanager
from datetime import datetime
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

sys.dont_write_bytecode = True
from http_smoke import ROOT, check, fields, php_command, request, server


def run(command, project, *, env=None):
    result = subprocess.run(command, cwd=project, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, env=env)
    if result.returncode:
        raise RuntimeError(f"Command failed: {command}\n{result.stdout}")
    return result.stdout


def install_project(directory):
    package = directory / "package"
    package.mkdir()
    for name in ("src", "bin", "stubs"):
        shutil.copytree(ROOT / name, package / name, ignore=shutil.ignore_patterns("vendor", "var", "__pycache__"))
    for name in ("composer.json", "README.md", "LICENSE"):
        shutil.copy2(ROOT / name, package / name)
    project = directory / "my app's $"
    run(["php", str(ROOT / "bin/phex"), "new", str(project)], ROOT)
    manifest = json.loads((project / "composer.json").read_text())
    check("repositories" not in manifest, "The default template depends on a local checkout.")
    manifest["repositories"] = [{"type": "path", "url": str(package), "options": {"symlink": False, "versions": {"getphex/framework": "0.1.0"}}}, {"packagist.org": False}]
    (project / "composer.json").write_text(json.dumps(manifest, indent=4) + "\n")
    run(["composer", "install", "--no-interaction", "--prefer-dist", "--no-scripts", "--no-plugins"], project)
    installed = project / "vendor/getphex/framework"
    check(installed.is_dir() and not installed.is_symlink(), "The framework was not independently mirrored.")
    shutil.rmtree(package)
    return project


def correlation(headers):
    values = fields(headers, "X-Request-Id")
    check(len(values) == 1, "The response needs exactly one request ID.")
    value = values[0]
    check(len(value) == 32 and all(byte in "0123456789abcdef" for byte in value), "The host returned an invalid request ID.")
    return value


def verify_logs(contents, expected):
    records = []
    for line in contents.splitlines():
        start = line.find('{')
        if start < 0:
            continue
        try:
            record = json.loads(line[start:])
        except json.JSONDecodeError:
            continue
        if isinstance(record, dict) and "level" in record and "timestamp" in record:
            records.append(record)
    check(bool(records), "No structured application records reached the server log.")
    for record in records:
        check(record["level"] in ("info", "error"), "The application log has an unexpected level.")
        check(isinstance(record["message"], str), "The application log lost its message.")
        check(record["timestamp"].endswith('Z'), "Log timestamps must use UTC.")
        datetime.fromisoformat(record["timestamp"])
        check(set(record) <= {"timestamp", "level", "message", "request_id", "method", "status", "exception_type"},
              "Implicit request data entered the structured log.")
        check("private-log-canary" not in json.dumps(record), "Request input was logged implicitly.")
    for request_id, expected_fields in expected:
        matches = [record for record in records if record.get("request_id") == request_id]
        check(len(matches) == 1, "Each observed request needs exactly one correlated completion or error record.")
        check(all(matches[0].get(key) == value for key, value in expected_fields.items()), "Log and response correlation diverged.")


def verify_logging(port):
    expected = []
    seen = set()
    for method, path, body, request_headers, status, message in (
        ("GET", "/missing?secret=private-log-canary", None,
         {"X-Request-Id": "private-log-canary", "Authorization": "private-log-canary", "Cookie": "private-log-canary"},
         404, "http.request.completed"),
        ("POST", "/greet", b'{}', {"Content-Type": "application/json"}, 422, "http.request.completed"),
        ("GET", "/fail", None, {}, 500, "private failure"),
        ("HEAD", "/fail", None, {}, 500, "private failure"),
    ):
        actual_status, headers, response_body = request(port, method, path, body, request_headers)
        check(actual_status == status, "Logging changed the application status.")
        if method == "HEAD":
            check(response_body == b'', "Correlated HEAD errors must suppress the response body.")
        request_id = correlation(headers)
        check(request_id not in seen, "A previous request ID leaked into the next request.")
        seen.add(request_id)
        entry = {"level": "error" if status == 500 else "info", "message": message, "method": method}
        if status == 500:
            entry["exception_type"] = "RuntimeException"
        else:
            entry["status"] = status
        expected.append((request_id, entry))
    return expected


@contextmanager
def application_server(command, **options):
    expected = []
    with server(command, log_check=lambda contents: verify_logs(contents, expected), **options) as port:
        yield port
        expected.extend(verify_logging(port))


def verify(port, home="/", greeting="world", search="/search", page_size=20):
    status, headers, body = request(port, "GET", home)
    check(status == 200 and body.startswith(b'{'), f"Standalone home returned {status}: {body!r}")
    check((status, json.loads(body)) == (200, {"hello": greeting}), "The standalone home route failed.")
    check(fields(headers, "Content-Type") == ["application/json"], "Standalone JSON metadata changed.")
    check(fields(headers, "Link") == [f'<{search}>; rel="search"'], "Named route generation returned a stale search link.")
    verify_query(port, search, page_size)
    status, headers, body = request(port, "POST", "/greet", b'{"name":"Ada"}', {"Content-Type": "application/json"})
    check((status, body) == (200, b'{"hello":"Ada"}'), "The standalone typed POST failed.")
    for payload, content_type, expected in ((b'{', "application/json", 400), (b'{}', "application/json", 422), (b'{}', "text/plain", 415)):
        status, headers, body = request(port, "POST", "/greet", payload, {"Content-Type": content_type})
        check(status == expected and "error" in json.loads(body), "Standalone JSON errors changed.")
    status, headers, body = request(port, "GET", "/missing")
    check(status == 404, "Standalone missing route failed.")
    status, headers, body = request(port, "GET", "/greet")
    check(status == 405 and fields(headers, "Allow") == ["POST"], "Standalone method routing failed.")
    status, headers, body = request(port, "GET", "/fail")
    check((status, body) == (500, b"Internal Server Error"), "Application failures escaped the shared host policy.")
    check(fields(headers, "Content-Type") == ["text/plain; charset=utf-8"], "Server errors lost the default content type.")


def verify_query(port, search, page_size):
    defaults = {"page": 1, "limit": page_size, "active": False, "sort": "asc"}
    for path, expected in (
        ("/search", defaults),
        ("/search?%70age=0002&limit=10&active=true&sort=desc", {"page": 2, "limit": 10, "active": True, "sort": "desc"}),
        ("/search?page=3&active=0", dict(defaults, page=3)),
    ):
        status, headers, body = request(port, "GET", path.replace("/search", search, 1))
        check((status, json.loads(body)) == (200, expected), "Standalone typed query decoding failed.")
        check(fields(headers, "Content-Type") == ["application/json"], "Query output lost its content type.")
    for query, expected_status, code in (
        ("page=one", 422, "invalid_input"),
        ("page=+2", 422, "invalid_input"),
        ("page=%2B2", 422, "invalid_input"),
        ("page=9223372036854775808", 422, "invalid_input"),
        ("page=1&page=2", 422, "invalid_input"),
        ("page=&page=2", 422, "invalid_input"),
        ("page=0", 422, "invalid_input"),
        ("limit=101", 422, "invalid_input"),
        ("active=yes", 422, "invalid_input"),
        ("sort=", 422, "invalid_input"),
        ("sort=descending", 422, "invalid_input"),
        ("unknown=1", 422, "invalid_input"),
        ("%FF=1", 422, "invalid_input"),
        ("page=%", 400, "malformed_query"),
        ("page=%2", 400, "malformed_query"),
        ("page=%GG", 400, "malformed_query"),
        ("%=1", 400, "malformed_query"),
        ("sort=%00", 400, "malformed_query"),
        ("&".join(["page=1"] * 101), 400, "query_limit_exceeded"),
    ):
        status, headers, body = request(port, "GET", search + "?" + query)
        result = json.loads(body)
        check((status, result["error"]) == (expected_status, code), "Standalone query error policy changed.")
        check(fields(headers, "Content-Type") == ["application/json"], "Query errors lost their content type.")
        if status == 422:
            check(bool(result["errors"]), "Query validation errors need field details.")
    status, _, body = request(port, "GET", search)
    check((status, json.loads(body)) == (200, defaults), "Query state leaked after rejected input.")


def configuration_environment(**overrides):
    environment = dict(os.environ)
    for name in ("PHEX_GREETING", "PHEX_PAGE_SIZE", "PHEX_UPPERCASE_GREETING"):
        environment.pop(name, None)
    environment.update(overrides)
    return environment


def verify_environment(command):
    for values, greeting, page_size in (
        ({"PHEX_GREETING": "runtime-native", "PHEX_PAGE_SIZE": "9", "PHEX_UPPERCASE_GREETING": "true"}, "RUNTIME-NATIVE", 9),
        ({"PHEX_GREETING": "0", "PHEX_PAGE_SIZE": "1", "PHEX_UPPERCASE_GREETING": "0"}, "0", 1),
    ):
        with application_server(command, env=configuration_environment(**values), error_log_marker="private failure") as port:
            verify(port, "/hello", greeting, "/find", page_size)
    for name, value in (
        ("PHEX_PAGE_SIZE", "do-not-print-config-value"),
        ("PHEX_PAGE_SIZE", "101"),
        ("PHEX_UPPERCASE_GREETING", "yes"),
        ("PHEX_GREETING", ""),
    ):
        expected = []
        with server(command, env=configuration_environment(**{name: value}), error_log_marker=name,
                    forbidden_log_marker="do-not-print-config-value", log_check=lambda contents: verify_logs(contents, expected)) as port:
            status, headers, body = request(port, "GET", "/hello")
            check((status, body) == (500, b"Internal Server Error"), "Invalid startup configuration escaped the host error policy.")
            check(fields(headers, "Content-Type") == ["text/plain; charset=utf-8"], "Configuration errors lost their content type.")
            expected.append((correlation(headers), {"level": "error", "exception_type": "Phex\\Config\\InvalidConfiguration"}))


def main():
    baseline_environment = configuration_environment()
    build_canary = "phex-build-environment-canary-6f891b40"
    build_environment = configuration_environment(PHEX_GREETING=build_canary, PHEX_PAGE_SIZE="invalid-during-build", PHEX_UPPERCASE_GREETING="invalid-during-build")
    with tempfile.TemporaryDirectory(prefix="phex-application-") as temporary:
        directory = Path(temporary)
        project = install_project(directory)
        installed = project / "vendor/getphex/framework"
        run(["php", "vendor/bin/phex", "--help"], project)
        local = directory / "local project"
        run(["php", "vendor/bin/phex", "new", str(local), "--local"], project)
        local_manifest = json.loads((local / "composer.json").read_text())
        check(local_manifest["repositories"][0]["url"] == str(installed), "Installed CLI found the wrong template/package root.")
        original = (project / "composer.json").read_bytes()
        rejected = subprocess.run(["php", "vendor/bin/phex", "new", str(project)], cwd=project, capture_output=True)
        check(rejected.returncode != 0 and (project / "composer.json").read_bytes() == original, "Existing project was overwritten.")
        failure = project / "app/Http/Action/FailAction.php"
        failure.write_text("<?php namespace App\\Http\\Action; final class FailAction implements \\Phex\\Contracts\\Routing\\Endpoint { public function handle(\\Phex\\Http\\Message\\Request $request, \\Phex\\Routing\\Parameter\\Parameters $parameters): \\Phex\\Http\\Message\\Response { throw new \\RuntimeException('private failure'); } }\n")
        services = project / "config/services.php"
        services.write_text(services.read_text().replace("return new Container([", "return new Container([\n    Service::constructor(\\App\\Http\\Action\\FailAction::class, Lifetime::Transient),"))
        routes = project / "routes/web.php"
        routes.write_text(routes.read_text().replace("return [", "return [\n    new EndpointRoute('GET', '/fail', \\App\\Http\\Action\\FailAction::class),\n    new EndpointRoute('HEAD', '/fail', \\App\\Http\\Action\\FailAction::class),"))

        with application_server(php_command(project / "public/index.php"), error_log_marker="private failure", env=baseline_environment) as port:
            verify(port)
            routes = project / "routes/web.php"
            routes.write_text(routes.read_text().replace("'/'", "'/hello'").replace("'/search'", "'/find'"))
            support = project / "app/Support"
            support.mkdir()
            (support / "ZGreeting.php").write_text("<?php namespace App\\Support; interface ZGreeting { public function greeting(): string; }\n")
            (support / "YGreetingTrait.php").write_text("<?php namespace App\\Support; trait YGreetingTrait { public function greeting(): string { return 'universe'; } }\n")
            (support / "ZBase.php").write_text("<?php namespace App\\Support; class ZBase {}\n")
            (support / "AGreeting.php").write_text("<?php namespace App\\Support; final class AGreeting extends ZBase implements ZGreeting { use YGreetingTrait; }\n")
            settings_factory = project / "app/Config/SettingsFactory.php"
            settings_factory.write_text(settings_factory.read_text().replace("'world'", "(new \\App\\Support\\AGreeting())->greeting()"))
            verify(port, "/hello", "universe", "/find")
            check(request(port, "GET", "/")[0] == 404, "Development retained an old route.")
        check(not (project / "var/generated").exists(), "Development wrote build artifacts.")
        print("Standalone PHP verified without artifacts or the source package, including live route/class edits.", flush=True)
        verify_environment(php_command(project / "public/index.php"))

        run(["composer", "build"], project, env=build_environment)
        generated = project / "var/generated"
        snapshots = {path.name: path.read_bytes() for path in generated.iterdir()}
        run(["composer", "build"], project, env=baseline_environment)
        check(snapshots == {path.name: path.read_bytes() for path in generated.iterdir()}, "Application generation is not deterministic.")
        check(all(build_canary.encode() not in contents for contents in snapshots.values()), "Build environment values entered generated PHP artifacts.")
        native_bootstrap = (generated / "native-bootstrap.php").read_text()
        check(str(ROOT) not in native_bootstrap and str(project) not in native_bootstrap, "Generated includes contain absolute checkout paths.")
        check(native_bootstrap.index("/Support/ZGreeting.php") < native_bootstrap.index("/Support/AGreeting.php"), "Interface declaration order is incorrect.")
        check(native_bootstrap.index("/Support/YGreetingTrait.php") < native_bootstrap.index("/Support/AGreeting.php"), "Trait declaration order is incorrect.")
        check(native_bootstrap.index("/Support/ZBase.php") < native_bootstrap.index("/Support/AGreeting.php"), "Parent declaration order is incorrect.")
        check(native_bootstrap.index("/Input/GreetingInput.php") < native_bootstrap.index("/Input/GreetingDecoder.php"), "Override return-type declaration order is incorrect.")
        route_file = project / "routes/web.php"
        valid_routes = route_file.read_text()
        route_file.write_text("<?php return [new stdClass()];\n")
        invalid = subprocess.run(["php", "vendor/bin/phex", "build", "config/build.php"], cwd=project, capture_output=True)
        check(invalid.returncode != 0, "Invalid route entries were accepted.")
        check(snapshots == {path.name: path.read_bytes() for path in generated.iterdir()}, "Invalid configuration changed existing artifacts.")
        route_file.write_text(valid_routes)
        duplicate_name = valid_routes.replace("return [", "return [\n    new EndpointRoute('GET', '/duplicate-name', \\App\\Http\\Action\\HomeAction::class, name: 'home'),")
        route_file.write_text(duplicate_name)
        invalid = subprocess.run(["php", "vendor/bin/phex", "build", "config/build.php"], cwd=project, capture_output=True)
        check(invalid.returncode != 0, "Duplicate route names were accepted.")
        check(snapshots == {path.name: path.read_bytes() for path in generated.iterdir()}, "Duplicate route names changed existing artifacts.")
        route_file.write_text(valid_routes)
        broken = project / "app/Broken.php"
        broken.write_text("<?php namespace App;\n")
        invalid = subprocess.run(["php", "vendor/bin/phex", "build", "config/build.php"], cwd=project, capture_output=True)
        check(invalid.returncode != 0, "A missing PSR-4 declaration was accepted.")
        check(snapshots == {path.name: path.read_bytes() for path in generated.iterdir()}, "Invalid source discovery changed existing artifacts.")
        broken.unlink()
        phpstan = project / "phpstan.neon"
        phpstan.write_text("parameters:\n    level: max\n    phpVersion: 80300\n    paths:\n        - app\n        - config\n        - routes\n        - bootstrap\n        - public\n        - native\n        - var/generated\n    scanFiles:\n        - vendor/getphex/framework/src/Runtime/Elephc/analysis.stub\n    bootstrapFiles:\n        - vendor/autoload.php\n    tmpDir: var/cache/phpstan\n    parallel:\n        maximumNumberOfProcesses: 1\n")
        run([str(ROOT / "tools/phpstan/vendor/bin/phpstan"), "analyse", "--configuration", str(phpstan), "--no-progress"], project)
        fake = directory / "compiler"
        fake.mkdir()
        fake_binary = fake / "elephc"
        fake_binary.write_text("#!/bin/sh\nexit 9\n")
        fake_binary.chmod(0o755)
        build = project / "var/build"
        build.mkdir(exist_ok=True)
        (build / "server").write_text("stale executable")
        (build / "server.s").write_text("stale assembly")
        environment = dict(os.environ, PATH=str(fake) + os.pathsep + os.environ["PATH"], PHEX_ELEPHC=str(fake_binary))
        failed = subprocess.run(["php", "vendor/bin/phex", "compile", "config/build.php"], cwd=project, env=environment, capture_output=True)
        check(failed.returncode != 0 and not (build / "server").exists() and not (build / "server.s").exists(), "Failed compilation retained a stale executable or assembly.")
        shutil.rmtree(fake)
        run(["composer", "build:native"], project, env=build_environment)
        binary_snapshot = (project / "var/build/server").read_bytes()
        check(build_canary.encode() not in binary_snapshot, "Build environment values entered the native executable.")
        moved = directory / "relocated application"
        project.rename(moved)
        project = moved
        with application_server(php_command(project / "public/compiled.php"), error_log_marker="private failure", env=baseline_environment) as port:
            verify(port, "/hello", "universe", "/find")
        native_command = [str(project / "var/build/server"), "--listen", "127.0.0.1:{port}", "--workers", "1"]
        with application_server(native_command, error_log_marker="private failure", env=baseline_environment) as port:
            verify(port, "/hello", "universe", "/find")
        print("Installed build/native CLI, PHPStan max, declaration order and project relocation verified.", flush=True)
        verify_environment(php_command(project / "public/compiled.php"))
        verify_environment(native_command)
        check((project / "var/build/server").read_bytes() == binary_snapshot, "Changing runtime configuration replaced the native executable.")
        del binary_snapshot
        print("One native executable verified with different runtime environments and invalid bootstrap configuration, without embedding build values.", flush=True)

        installed = project / "vendor/getphex/framework"
        for relative in ("src/Build", "src/Runtime/Development", "src/Console", "src/Database", "src/Contracts/Database", "stubs"):
            shutil.rmtree(installed / relative)
        for relative in ("config", "routes", "bootstrap", "vendor/composer"):
            shutil.rmtree(project / relative)
        (project / "vendor/autoload.php").unlink()
        (project / "public/index.php").unlink()
        (project / "var/build/server").unlink()
        run(["elephc", "--web", "--strict-php", str(project / "var/build/server.php")], project, env=build_environment)
        with application_server(php_command(project / "public/compiled.php"), error_log_marker="private failure", env=baseline_environment) as port:
            verify(port, "/hello", "universe", "/find")
        with application_server([str(project / "var/build/server"), "--listen", "127.0.0.1:{port}", "--workers", "1"], error_log_marker="private failure", env=baseline_environment) as port:
            verify(port, "/hello", "universe", "/find")
        print("Standalone compiled PHP/native verified without Composer loading, development/build code or configuration.", flush=True)
        verify_environment(php_command(project / "public/compiled.php"))
        verify_environment([str(project / "var/build/server"), "--listen", "127.0.0.1:{port}", "--workers", "1"])
    return 0


if __name__ == "__main__":
    sys.exit(main())
