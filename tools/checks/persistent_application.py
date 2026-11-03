#!/usr/bin/env python3
"""Verify optional persistent lifetime through the installed Phex CLI and standalone launcher."""

import json
import os
from contextlib import redirect_stdout
from io import StringIO
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import time

sys.dont_write_bytecode = True
from application_smoke import install_project, run, correlation
from http_smoke import ROOT, check, php_command, request, server


def add_probe(project):
    source = project / "app/Probe"
    source.mkdir()
    files = {
        "State.php": r'''<?php
declare(strict_types=1);
namespace App\Probe;
use Phex\Contracts\Kernel\ScopeResource;
final class State implements ScopeResource {
    public int $requests = 0;
    public string $previous = '';
    public function __construct(public readonly string $file) { $this->event('boot'); }
    public function event(string $event): void { file_put_contents($this->file, $event.' '.getmypid().' '.$this->requests."\n", FILE_APPEND); }
    public function close(): void { $this->event('shutdown'); }
}
''',
        "StateFactory.php": r'''<?php
declare(strict_types=1);
namespace App\Probe;
use Phex\Config\Environment;
final class StateFactory {
    public static function create(Environment $environment): State { return new State($environment->nonEmptyString('PHEX_TEST_LIFECYCLE_FILE')); }
}
''',
        "RequestResource.php": r'''<?php
declare(strict_types=1);
namespace App\Probe;
use Phex\Contracts\Kernel\ScopeResource;
final readonly class RequestResource implements ScopeResource {
    public function __construct(private string $file, private int $request) {}
    public function close(): void { file_put_contents($this->file, 'release '.getmypid().' '.$this->request."\n", FILE_APPEND); }
}
''',
        "Action.php": r'''<?php
declare(strict_types=1);
namespace App\Probe;
use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Http\Query\QueryString;
use Phex\Http\Query\QueryParameters;
use Phex\Kernel\Scope\RequestScope;
use Phex\Routing\Parameter\Parameters;
final readonly class Action implements Endpoint {
    public function __construct(private State $state, private RequestScope $scope) {}
    public function handle(Request $request, Parameters $parameters): Response {
        $this->state->requests++;
        $previous = $this->state->previous;
        $this->state->previous = $request->uri;
        $this->scope->own(new RequestResource($this->state->file, $this->state->requests));
        $query = QueryParameters::decode(QueryString::raw($request));
        if ($query->get('fail', '') === '1') { throw new \RuntimeException('persistent private failure'); }
        return JsonResponse::create(['pid' => getmypid(), 'counter' => $this->state->requests, 'previous' => $previous,
            'body' => $request->body, 'value' => $query->get('value', 'absent'),
            'test' => $request->headers->values('X-Test')[0] ?? '', 'method' => $request->method]);
    }
}
''',
    }
    for name, body in files.items():
        (source / name).write_text(body)
    services = project / "config/services.php"
    services.write_text(services.read_text().replace("return new Container([", r'''return new Container([
    Service::factory(\App\Probe\State::class, Lifetime::Application, \App\Probe\StateFactory::class, 'create'),
    Service::constructor(\App\Probe\Action::class, Lifetime::Transient),'''))
    routes = project / "routes/web.php"
    routes.write_text(routes.read_text().replace("return [", r'''return [
    new EndpointRoute('GET', '/worker', \App\Probe\Action::class),
    new EndpointRoute('POST', '/worker', \App\Probe\Action::class),'''))


def environment(events, **overrides):
    values = dict(os.environ)
    for name in ("PHEX_LISTEN", "PHEX_WORKERS", "PHEX_MAX_REQUESTS", "PHEX_GREETING", "PHEX_PAGE_SIZE", "PHEX_UPPERCASE_GREETING"):
        values.pop(name, None)
    values.update(PHEX_TEST_LIFECYCLE_FILE=str(events))
    values.update(overrides)
    return values


def events_at(path):
    return [tuple(line.split()) for line in path.read_text().splitlines()]


def response(port, method="GET", path="/worker", body=None, headers=None, *, retry=False):
    deadline = time.monotonic() + 8
    while True:
        try:
            status, headers_, payload = request(port, method, path, body, headers)
            check(status == 200, f"Probe returned {status}: {payload!r}")
            return json.loads(payload), correlation(headers_)
        except ConnectionRefusedError:
            if not retry or time.monotonic() >= deadline:
                raise
            time.sleep(0.05)


def verify_retained(port):
    first, first_id = response(port, "POST", "/worker?value=one", b'first\x00body', {"X-Test": "first", "X-Request-Id": "client"})
    second, second_id = response(port)
    check(first["counter"] == 1 and first["previous"] == '' and first["body"] == 'first\x00body', "Initial application state or owned body changed.")
    check(first["value"] == 'one' and first["test"] == 'first' and first["method"] == 'POST', "Initial request metadata changed.")
    check(second["pid"] == first["pid"] and second["counter"] == 2 and second["previous"] == '/worker?value=one', "Application state did not survive the request.")
    check(second["body"] == '' and second["value"] == 'absent' and second["test"] == '' and second["method"] == 'GET', "Request data leaked across persistent dispatch.")
    status, headers, body = request(port, "GET", "/worker?fail=1")
    check((status, body) == (500, b'Internal Server Error'), "Unhandled failure escaped the Phex boundary.")
    failed_id = correlation(headers)
    fourth, fourth_id = response(port, path="/worker?value=four")
    check(fourth["pid"] == first["pid"] and fourth["counter"] == 4 and fourth["previous"] == '/worker?fail=1', "The application did not recover after a failed request.")
    check(fourth["body"] == '' and fourth["test"] == '' and fourth["value"] == 'four', "Failure cleanup leaked request data.")
    check(len({first_id, second_id, failed_id, fourth_id}) == 4, "Request correlation was retained in shared state.")
    return str(first["pid"])


def check_events(path, pid, count):
    events = events_at(path)
    check(events.count(('boot', pid, '0')) == 1, "The application booted more than once per worker.")
    check(events.count(('shutdown', pid, str(count))) == 1, "Application shutdown did not close resources exactly once.")
    expected = [('release', pid, str(index)) for index in range(1, count + 1)]
    check([entry for entry in events if entry[0] == 'release' and entry[1] == pid] == expected, "Request cleanup did not release every request, including the failure.")
    check(events.index(('release', pid, str(count))) < events.index(('shutdown', pid, str(count))), "Application shutdown preceded request cleanup.")


def main():
    compiler = os.environ.get('PHEX_ELEPHC') or shutil.which('elephc')
    check(bool(compiler), "Persistent tests require ElephC.")
    help_ = subprocess.run([compiler, '--help'], capture_output=True, text=True, check=True)
    check('--web-lifecycle' in help_.stdout, "Persistent tests require --web-lifecycle support; set PHEX_ELEPHC to a supported compiler.")
    print(run([compiler, '--version'], ROOT).strip(), flush=True)
    with tempfile.TemporaryDirectory(prefix='persistent-application-', dir=ROOT / 'var') as temporary:
        directory = Path(temporary)
        project = install_project(directory)
        add_probe(project)
        events = directory / 'events'
        with server(php_command(project / 'public/index.php'), env=environment(events)) as port:
            for _ in range(2):
                value, _ = response(port)
                check(value['counter'] == 1 and value['previous'] == '', "PHP request lifecycle unexpectedly retained state.")
        events.unlink()
        build_environment = environment(events, PHEX_ELEPHC=compiler)
        run(['composer', 'build:native'], project, env=build_environment)
        launcher = project / 'var/build/start'
        with server([str(launcher), '--listen', '127.0.0.1:{port}', '--workers', '1', '--max-requests', '0'], env=environment(events)) as port:
            for _ in range(2):
                value, _ = response(port)
                check(value['counter'] == 1 and value['previous'] == '', "Native Request lifecycle unexpectedly retained state.")
        check(len([event for event in events_at(events) if event[0] == 'shutdown']) == 2, "Request lifecycle did not close each container.")
        events.unlink()
        config = project / 'config/server.php'
        config.write_text(config.read_text().replace('NativeLifecycle::Request', 'NativeLifecycle::Application'))
        run(['composer', 'build:native'], project, env=build_environment)
        binary = project / 'var/build/server'
        snapshot = binary.read_bytes()
        startup_log = StringIO()
        startup_failure = None
        with redirect_stdout(startup_log):
            try:
                with server([str(launcher), '--listen', '127.0.0.1:{port}', '--workers', '1'], env=environment(events, PHEX_PAGE_SIZE='invalid-before-bootstrap')):
                    pass
            except RuntimeError as error:
                startup_failure = str(error)
        check(startup_failure is not None and ('before startup' in startup_failure or 'Timed out starting' in startup_failure),
              'An invalid application bootstrap accepted traffic.')
        check('PHEX_PAGE_SIZE' in startup_log.getvalue(), 'The native host did not report the bootstrap configuration failure.')
        if events.exists():
            events.unlink()
        with server([str(launcher), '--listen', '127.0.0.1:{port}', '--workers', '1', '--max-requests', '0'], env=environment(events), error_log_marker='persistent private failure') as port:
            pid = verify_retained(port)
        check_events(events, pid, 4)
        events.unlink()
        with server([str(launcher), '--listen', '127.0.0.1:{port}', '--workers', '1', '--max-requests', '2'], env=environment(events)) as port:
            one, _ = response(port)
            two, _ = response(port)
            three, _ = response(port, retry=True)
            check(one['pid'] == two['pid'] and one['counter'] == 1 and two['counter'] == 2, "The first worker did not retain its application.")
            check(three['pid'] != two['pid'] and three['counter'] == 1 and three['previous'] == '', "Worker recycling did not bootstrap a fresh application.")
        check_events(events, str(one['pid']), 2)
        check_events(events, str(three['pid']), 1)
        events.unlink()
        with server([str(launcher), '--listen', '127.0.0.1:{port}', '--workers', '2', '--max-requests', '0'], env=environment(events)) as port:
            counters = {}
            for _ in range(64):
                value, _ = response(port)
                expected = counters.get(value['pid'], 0) + 1
                check(value['counter'] == expected, "Workers shared application state.")
                counters[value['pid']] = expected
                if len(counters) == 2:
                    break
            check(len(counters) == 2, "The worker startup override was not applied.")
        for pid, count in counters.items():
            check_events(events, str(pid), count)
        check(binary.read_bytes() == snapshot, "Startup tuning recompiled the executable.")
        events.unlink()
        phpstan = project / 'phpstan.neon'
        phpstan.write_text("parameters:\n    level: max\n    phpVersion: 80300\n    paths: [app, config, routes, native, var/generated]\n    scanFiles: [vendor/getphex/framework/src/Runtime/Elephc/analysis.stub]\n    bootstrapFiles: [vendor/autoload.php]\n    tmpDir: var/cache/phpstan\n    parallel:\n        maximumNumberOfProcesses: 1\n")
        run([str(ROOT / 'tools/phpstan/vendor/bin/phpstan'), 'analyse', '--configuration', str(phpstan), '--no-progress'], project)
        moved = directory / "relocated app's $"
        project.rename(moved)
        project = moved
        installed = project / 'vendor/getphex/framework'
        for path in ['src/Build', 'src/Runtime/Development', 'src/Console', 'stubs']:
            shutil.rmtree(installed / path)
        for path in ['config', 'routes', 'bootstrap', 'vendor/composer']:
            shutil.rmtree(project / path)
        (project / 'vendor/autoload.php').unlink()
        (project / 'var/build/server').unlink()
        run([compiler, '--web', '--strict-php', '--web-isolation=worker', '--web-lifecycle=application', str(project / 'var/build/server.php')], project)
        launcher = project / 'var/build/start'
        tools = directory / 'startup-tools'
        tools.mkdir()
        dirname = shutil.which('dirname')
        check(dirname is not None, 'POSIX dirname is required by the generated launcher.')
        (tools / 'dirname').symlink_to(dirname)
        check(shutil.which('php', path=str(tools)) is None, 'The standalone startup fixture includes PHP.')
        with server([str(launcher), '--listen', '127.0.0.1:{port}', '--workers', '1', '--max-requests', '0'], env=environment(events, PATH=str(tools)), error_log_marker='persistent private failure') as port:
            pid = verify_retained(port)
        check_events(events, pid, 4)
        print('Persistent native verified: configured build/launcher, failed-bootstrap rejection, retained values, fresh requests, error recovery, shutdown, recycling, worker overrides and stripped relocation without PHP startup.', flush=True)
    return 0


if __name__ == '__main__':
    sys.exit(main())
