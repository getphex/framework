#!/usr/bin/env python3
"""Exercise an installed PDO CRUD app across PHP and native request lifetimes."""

import argparse
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import time

from application_smoke import install_project, run, correlation
from http_smoke import ROOT, check, fields, php_command, request, server


def install_example(directory):
    project = install_project(directory)
    shutil.rmtree(project/'app')
    source = ROOT/'examples/database'
    for name in ['app', 'config', 'routes', 'public', 'native']:
        target = project/name
        if target.exists():
            shutil.rmtree(target)
        shutil.copytree(source/name, target)
    shutil.copy2(source/'init.php', project/'init.php')
    for path in project.rglob('*.php'):
        if 'vendor' in path.relative_to(project).parts:
            continue
        text = path.read_text().replace('Phex\\\\Examples\\\\Database\\\\', 'App\\\\')
        text = text.replace('Phex\\Examples\\Database\\', 'App\\')
        text = text.replace('namespace Phex\\Examples\\Database;', 'namespace App;')
        text = text.replace("__DIR__.'/../../../src/Database/bootstrap.php'", "__DIR__.'/../vendor/getphex/phex/src/Database/bootstrap.php'")
        text = text.replace("__DIR__.'/../../../vendor/autoload.php'", "__DIR__.'/../vendor/autoload.php'")
        text = text.replace("__DIR__.'/../../vendor/autoload.php'", "__DIR__.'/vendor/autoload.php'")
        path.write_text(text)
    probe = project/'app/DatabaseProbe.php'
    probe.write_text(r'''<?php
declare(strict_types=1);
namespace App;
use Phex\Contracts\Routing\Endpoint;
use Phex\Database\Parameters as Bindings;
use Phex\Database\Session;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;
final readonly class DatabaseProbe implements Endpoint {
    public function __construct(private Session $database) {}
    public function handle(Request $request, Parameters $parameters): Response {
        if ($request->method === 'GET') {
            return JsonResponse::create(['pid' => getmypid(), 'last_insert_id' => $this->database->lastInsertId(), 'transaction' => $this->database->inTransaction()]);
        }
        $this->database->beginTransaction();
        $this->database->execute('INSERT INTO users (name) VALUES (:name)', new Bindings(['name' => 'uncommitted-canary']));
        if ($request->uri === '/db-fail') { throw new \RuntimeException('database fixture failure'); }
        return JsonResponse::create(['pending' => true]);
    }
}
''')
    services = project/'config/services.php'
    services.write_text(services.read_text().replace('return new Container([',
        'return new Container([\n    Service::constructor(\\App\\DatabaseProbe::class, Lifetime::Request),'))
    routes = project/'routes/web.php'
    routes.write_text(routes.read_text().replace('return [', '''return [
    new EndpointRoute('GET', '/db-info', \\App\\DatabaseProbe::class),
    new EndpointRoute('POST', '/db-fail', \\App\\DatabaseProbe::class),
    new EndpointRoute('POST', '/db-pending', \\App\\DatabaseProbe::class),'''))
    return project


def verify(port, retained):
    seen = set()

    def call(method, path, expected_status, payload=None):
        data = json.dumps(payload).encode() if payload is not None else None
        headers = {'Content-Type': 'application/json', 'X-Request-Id': 'client-untrusted'} if data else {}
        status, response_headers, body = request(port, method, path, data, headers)
        check(status == expected_status, f'{method} {path}: expected {expected_status}, got {status}: {body!r}')
        identity = correlation(response_headers)
        check(identity not in seen and identity != 'client-untrusted', 'Database requests reused their correlation ID.')
        seen.add(identity)
        return response_headers, body

    _, body = call('GET', '/users', 200)
    check(json.loads(body) == [], 'The example must start empty.')
    _, body = call('GET', '/db-info', 200)
    worker = json.loads(body)['pid']
    name = "Ada'); DROP TABLE users; --"
    headers, body = call('POST', '/users', 201, {'name': name})
    created = json.loads(body)
    check(created['name'] == name and isinstance(created['id'], int), 'Prepared insertion changed the value.')
    path = '/users/'+str(created['id'])
    check(fields(headers, 'Location') == [path], 'Created resource lost its named URL.')
    _, body = call('GET', '/db-info', 200)
    info = json.loads(body)
    check(info['pid'] == worker and info['transaction'] is False, 'The worker changed or leaked a transaction.')
    expected_identity = str(created['id']) if retained else '0'
    check(info['last_insert_id'] == expected_identity, 'Physical connection lifetime does not match the selected lifecycle.')
    _, body = call('GET', path, 200)
    check(json.loads(body) == created, 'Created user did not persist.')
    _, body = call('PUT', path, 200, {'name': 'updated'})
    updated = dict(created, name='updated')
    check(json.loads(body) == updated, 'Update did not persist.')
    call('POST', '/users', 422, {'name': ''})
    call('PUT', '/users/999999', 404, {'name': 'missing'})
    call('POST', '/db-pending', 200)
    _, body = call('GET', '/users', 200)
    check(json.loads(body) == [updated], 'Request cleanup committed an unfinished transaction.')
    _, body = call('POST', '/db-fail', 500)
    check(body == b'Internal Server Error', 'A database error exposed private details.')
    _, body = call('GET', '/db-info', 200)
    check(json.loads(body)['transaction'] is False, 'Error cleanup left an open transaction.')
    _, body = call('GET', '/users', 200)
    check(json.loads(body) == [updated], 'Failed request writes leaked into the next request.')
    call('DELETE', path, 204)
    call('GET', path, 404)
    _, body = call('GET', '/users', 200)
    check(json.loads(body) == [], 'Delete did not persist.')


def verify_recycling_workers(port):
    def read(path):
        deadline = time.monotonic() + 5
        while True:
            try:
                return request(port, 'GET', path)
            except (ConnectionRefusedError, ConnectionResetError):
                # All quota-limited workers can briefly be between generations.
                if time.monotonic() >= deadline:
                    raise
                time.sleep(0.05)

    status, _, body = request(port, 'POST', '/users', b'{"name":"shared across workers"}', {'Content-Type': 'application/json'})
    check(status == 201, f'Worker insertion failed: {body!r}')
    created = json.loads(body)
    workers = set()
    for _ in range(24):
        status, _, body = read('/db-info')
        check(status == 200, f'Worker database session failed after recycling: {body!r}')
        info = json.loads(body)
        workers.add(info['pid'])
        check(info['transaction'] is False, 'A recycled worker inherited an open transaction.')
        status, _, body = read('/users')
        check(status == 200 and json.loads(body) == [created], 'File-backed rows did not survive worker changes.')
    check(len(workers) >= 3, 'The database check did not exercise worker recycling.')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php-only', action='store_true', help='Verify PHP development and compiled PHP without native compilation')
    args = parser.parse_args()
    compiler = os.environ.get('PHEX_ELEPHC', shutil.which('elephc'))
    check(args.php_only or compiler is not None, 'ElephC is required for database compatibility checks.')
    supports_application = not args.php_only and '--web-lifecycle' in subprocess.check_output([compiler, '--help'], text=True)
    with tempfile.TemporaryDirectory(prefix='phex-database-smoke-', dir=ROOT/'var') as temporary:
        directory = Path(temporary)
        project = install_example(directory)
        build_environment = dict(os.environ)
        if compiler is not None:
            build_environment['PHEX_ELEPHC'] = compiler
        build_environment.pop('PHEX_DATABASE_DSN', None)
        run(['composer', 'build'], project, env=build_environment)

        def environment(label):
            values = dict(build_environment, PHEX_DATABASE_DSN='sqlite:'+str(directory/(label+'.sqlite')))
            run(['php', 'init.php'], project, env=values)
            return values

        for label, entry in [('development', 'index.php'), ('compiled', 'compiled.php')]:
            with server(php_command(project/'public'/entry), env=environment(label), error_log_marker='database fixture failure') as port:
                verify(port, False)
        if args.php_only:
            print('Installed database CRUD and request rollback verified on PHP development/compiled targets.', flush=True)
            return 0
        run(['composer', 'build:native'], project, env=build_environment)
        binary = project/'var/build/server'
        with server([str(binary), '--listen', '127.0.0.1:{port}', '--workers', '1', '--max-requests', '0'],
                    env=environment('native-request'), error_log_marker='database fixture failure') as port:
            verify(port, False)
        if supports_application:
            configuration = project/'config/server.php'
            configuration.write_text("<?php\nreturn new \\Phex\\Build\\Config\\NativeServerConfig(lifecycle: \\Phex\\Build\\Config\\NativeLifecycle::Application);\n")
            run(['composer', 'build:native'], project, env=build_environment)
            with server([str(binary), '--listen', '127.0.0.1:{port}', '--workers', '1', '--max-requests', '0'],
                        env=environment('native-application'), error_log_marker='database fixture failure') as port:
                verify(port, True)
            with server([str(binary), '--listen', '127.0.0.1:{port}', '--workers', '2', '--max-requests', '8'],
                        env=environment('native-workers')) as port:
                verify_recycling_workers(port)
        print('Database CRUD, bindings, DTOs, connection lifetimes, request rollback and error recovery verified on installed PHP/native targets.', flush=True)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
