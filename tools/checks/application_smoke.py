#!/usr/bin/env python3
"""Install and run the portable application outside the framework checkout."""

import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

sys.dont_write_bytecode = True
from http_smoke import ROOT, check, fields, php_command, request, server


def run(command, project):
    result = subprocess.run(command, cwd=project, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT)
    if result.returncode:
        raise RuntimeError(f"Command failed: {command}\n{result.stdout}")
    return result.stdout


def verify(port, home="/", greeting="world", search="/search"):
    status, headers, body = request(port, "GET", home)
    check(status == 200 and body.startswith(b'{'), f"Standalone home returned {status}: {body!r}")
    check((status, json.loads(body)) == (200, {"hello": greeting}), "The standalone home route failed.")
    check(fields(headers, "Content-Type") == ["application/json"], "Standalone JSON metadata changed.")
    check(fields(headers, "Link") == [f'<{search}>; rel="search"'], "Named route generation returned a stale search link.")
    verify_query(port, search)
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


def verify_query(port, search):
    defaults = {"page": 1, "limit": 20, "active": False, "sort": "asc"}
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


def main():
    with tempfile.TemporaryDirectory(prefix="phex-application-") as temporary:
        directory = Path(temporary)
        package = directory / "package"
        package.mkdir()
        for name in ("src", "bin", "stubs"):
            shutil.copytree(ROOT / name, package / name, ignore=shutil.ignore_patterns("vendor", "var", "__pycache__"))
        for name in ("composer.json", "README.md"):
            shutil.copy2(ROOT / name, package / name)
        project = directory / "my app's $"
        run(["php", str(ROOT / "bin/phex"), "new", str(project)], ROOT)
        manifest = json.loads((project / "composer.json").read_text())
        check("repositories" not in manifest, "The default template depends on a local checkout.")
        manifest["repositories"] = [{"type": "path", "url": str(package), "options": {"symlink": False, "versions": {"getphex/phex": "0.1.0"}}}, {"packagist.org": False}]
        (project / "composer.json").write_text(json.dumps(manifest, indent=4) + "\n")
        run(["composer", "install", "--no-interaction", "--prefer-dist", "--no-scripts", "--no-plugins"], project)
        installed = project / "vendor/getphex/phex"
        check(installed.is_dir() and not installed.is_symlink(), "The framework was not independently mirrored.")
        shutil.rmtree(package)
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
        routes.write_text(routes.read_text().replace("return [", "return [\n    new EndpointRoute('GET', '/fail', \\App\\Http\\Action\\FailAction::class),"))

        with server(php_command(project / "public/index.php"), error_log_marker="private failure") as port:
            verify(port)
            routes = project / "routes/web.php"
            routes.write_text(routes.read_text().replace("'/'", "'/hello'").replace("'/search'", "'/find'"))
            support = project / "app/Support"
            support.mkdir()
            (support / "ZGreeting.php").write_text("<?php namespace App\\Support; interface ZGreeting { public function greeting(): string; }\n")
            (support / "YGreetingTrait.php").write_text("<?php namespace App\\Support; trait YGreetingTrait { public function greeting(): string { return 'universe'; } }\n")
            (support / "ZBase.php").write_text("<?php namespace App\\Support; class ZBase {}\n")
            (support / "AGreeting.php").write_text("<?php namespace App\\Support; final class AGreeting extends ZBase implements ZGreeting { use YGreetingTrait; }\n")
            action = project / "app/Http/Action/HomeAction.php"
            action.write_text(action.read_text().replace("'world'", "(new \\App\\Support\\AGreeting())->greeting()"))
            verify(port, "/hello", "universe", "/find")
            check(request(port, "GET", "/")[0] == 404, "Development retained an old route.")
        check(not (project / "var/generated").exists(), "Development wrote build artifacts.")
        print("Standalone PHP verified without artifacts or the source package, including live route/class edits.", flush=True)

        run(["composer", "build"], project)
        generated = project / "var/generated"
        snapshots = {path.name: path.read_bytes() for path in generated.iterdir()}
        run(["composer", "build"], project)
        check(snapshots == {path.name: path.read_bytes() for path in generated.iterdir()}, "Application generation is not deterministic.")
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
        phpstan.write_text("parameters:\n    level: max\n    phpVersion: 80300\n    paths:\n        - app\n        - config\n        - routes\n        - bootstrap\n        - public\n        - native\n        - var/generated\n    bootstrapFiles:\n        - vendor/autoload.php\n    tmpDir: var/cache/phpstan\n    parallel:\n        maximumNumberOfProcesses: 1\n")
        run([str(ROOT / "tools/phpstan/vendor/bin/phpstan"), "analyse", "--configuration", str(phpstan), "--no-progress"], project)
        fake = directory / "compiler"
        fake.mkdir()
        fake_binary = fake / "elephc"
        fake_binary.write_text("#!/bin/sh\nexit 9\n")
        fake_binary.chmod(0o755)
        build = project / "var/build"
        build.mkdir()
        (build / "server").write_text("stale executable")
        (build / "server.s").write_text("stale assembly")
        environment = dict(os.environ, PATH=str(fake) + os.pathsep + os.environ["PATH"])
        failed = subprocess.run(["php", "vendor/bin/phex", "compile", "config/build.php"], cwd=project, env=environment, capture_output=True)
        check(failed.returncode != 0 and not (build / "server").exists() and not (build / "server.s").exists(), "Failed compilation retained a stale executable or assembly.")
        shutil.rmtree(fake)
        run(["composer", "build:native"], project)
        moved = directory / "relocated application"
        project.rename(moved)
        project = moved
        with server(php_command(project / "public/compiled.php"), error_log_marker="private failure") as port:
            verify(port, "/hello", "universe", "/find")
        with server([str(project / "var/build/server"), "--listen", "127.0.0.1:{port}", "--workers", "1"], error_log_marker="private failure") as port:
            verify(port, "/hello", "universe", "/find")
        print("Installed build/native CLI, PHPStan max, declaration order and project relocation verified.", flush=True)

        installed = project / "vendor/getphex/phex"
        for relative in ("src/Build", "src/Runtime/Development", "src/Console", "stubs"):
            shutil.rmtree(installed / relative)
        for relative in ("config", "routes", "bootstrap", "vendor/composer"):
            shutil.rmtree(project / relative)
        (project / "vendor/autoload.php").unlink()
        (project / "public/index.php").unlink()
        (project / "var/build/server").unlink()
        run(["elephc", "--web", "--strict-php", str(project / "var/build/server.php")], project)
        with server(php_command(project / "public/compiled.php"), error_log_marker="private failure") as port:
            verify(port, "/hello", "universe", "/find")
        with server([str(project / "var/build/server"), "--listen", "127.0.0.1:{port}", "--workers", "1"], error_log_marker="private failure") as port:
            verify(port, "/hello", "universe", "/find")
        print("Standalone compiled PHP/native verified without Composer loading, development/build code or configuration.", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
