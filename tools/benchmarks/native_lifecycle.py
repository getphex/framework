#!/usr/bin/env python3
"""Local single-worker HTTP comparison with equal Caddy frontends and rotated runs."""

import argparse
from contextlib import contextmanager
from datetime import datetime, timezone
import grp
import hashlib
import json
import os
from pathlib import Path
import pwd
import re
import shutil
import signal
import socket
import statistics
import subprocess
import sys
import tarfile
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
sys.dont_write_bytecode = True
sys.path.insert(0, str(ROOT / 'tools/checks'))
from application_smoke import install_project, run
from http_smoke import check, fields, request
from benchmark_environment import runtime_environment, fpm_environment_lines

CADDY_TAG = 'v2.11.7'
CADDY_ARCHIVE = 'caddy_2.11.7_linux_amd64.tar.gz'
CADDY_CHECKSUMS = 'caddy_2.11.7_checksums.txt'


def available_port():
    with socket.socket() as reservation:
        reservation.bind(('127.0.0.1', 0))
        return reservation.getsockname()[1]


def cpu_assignment():
    allowed = os.sched_getaffinity(0)
    topology = subprocess.check_output(['lscpu', '-p=CPU,CORE,SOCKET'], text=True)
    physical = {}
    for line in topology.splitlines():
        if line and not line.startswith('#'):
            cpu, core, socket_ = map(int, line.split(','))
            if cpu in allowed:
                physical.setdefault((socket_, core), cpu)
    cpus = list(physical.values())
    check(len(cpus) >= 4, 'This benchmark needs four available physical cores for backend/proxy/client isolation.')
    return {'backend': [cpus[0]], 'proxy': [cpus[1]], 'client': cpus[2:4]}


def pinned(command, cpus):
    return ['taskset', '-c', ','.join(map(str, cpus)), *map(str, command)]


def group_cpu_seconds(group):
    ticks = 0
    for path in Path('/proc').glob('[0-9]*/stat'):
        try:
            raw = path.read_text()
        except (FileNotFoundError, ProcessLookupError, PermissionError):
            continue
        values = raw[raw.rfind(')') + 2:].split()
        if int(values[2]) == group:
            ticks += int(values[11]) + int(values[12])
    return ticks / os.sysconf('SC_CLK_TCK')


@contextmanager
def process(command, port, directory, *, environment, label, quiet=False):
    # Keep relative runtime state in the disposable workspace when HOME is unset.
    working_directory = directory / 'runtime-cwd'
    working_directory.mkdir(exist_ok=True)
    with tempfile.TemporaryFile(dir=directory) as log:
        child = subprocess.Popen(command, cwd=working_directory, env=environment, stdout=subprocess.DEVNULL if quiet else log,
                                 stderr=subprocess.DEVNULL if quiet else log, start_new_session=True)
        child.benchmark_log = log
        try:
            deadline = time.monotonic() + 15
            while True:
                check(child.poll() is None, f'{label} exited before becoming ready.')
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=0.2):
                        break
                except OSError:
                    check(time.monotonic() < deadline, f'{label} startup timed out.')
                    time.sleep(0.03)
            yield child
        except Exception:
            if not quiet:
                log.seek(max(0, log.seek(0, 2) - 8192))
                print(log.read().decode(errors='replace'), file=sys.stderr)
            raise
        finally:
            try:
                os.killpg(child.pid, signal.SIGTERM)
            except ProcessLookupError:
                pass
            try:
                child.wait(timeout=8)
            except subprocess.TimeoutExpired:
                os.killpg(child.pid, signal.SIGKILL)
                child.wait()
            finally:
                try:
                    os.killpg(child.pid, signal.SIGKILL)
                except ProcessLookupError:
                    pass


def download_caddy(directory):
    subprocess.run(['gh', 'release', 'download', CADDY_TAG, '--repo', 'caddyserver/caddy',
                    '--pattern', CADDY_ARCHIVE, '--pattern', CADDY_CHECKSUMS, '--dir', str(directory)], check=True)
    checksums = (directory / CADDY_CHECKSUMS).read_text()
    expected = next(line.split()[0] for line in checksums.splitlines() if line.split()[-1] == CADDY_ARCHIVE)
    archive_bytes = (directory / CADDY_ARCHIVE).read_bytes()
    actual = hashlib.sha256(archive_bytes).hexdigest()
    check(hashlib.sha512(archive_bytes).hexdigest() == expected, 'Caddy release SHA-512 checksum mismatch.')
    with tarfile.open(directory / CADDY_ARCHIVE) as archive:
        member = next(item for item in archive.getmembers() if item.name == 'caddy' and item.isfile())
        binary = archive.extractfile(member)
        check(binary is not None, 'Caddy archive contains no executable.')
        target = directory / 'caddy'
        with binary, target.open('wb') as output:
            shutil.copyfileobj(binary, output)
    target.chmod(0o755)
    return target, actual


def proxy_config(path, port, backend_port, project, script=None):
    transport = ''
    if script is not None:
        transport = '\n'.join(['transport fastcgi {', 'root '+json.dumps(str(project / 'public')),
                               'env SCRIPT_FILENAME '+json.dumps(str(script)), '}'])
    path.write_text('''{
    admin off
    auto_https off
    persist_config off
    log {
        level ERROR
    }
}
http://127.0.0.1:''' + str(port) + ''' {
    reverse_proxy 127.0.0.1:''' + str(backend_port) + ' {\n' + transport + '\n}\n}\n')


def fpm_config(path, port, forwarded_names=(), *, workers=1):
    check(workers > 0, 'FPM worker count must be positive.')
    path.write_text(f'''[global]
daemonize = no
error_log = /dev/null
[phex]
user = {pwd.getpwuid(os.getuid()).pw_name}
group = {grp.getgrgid(os.getgid()).gr_name}
listen = 127.0.0.1:{port}
listen.backlog = 256
pm = static
pm.max_children = {workers}
pm.max_requests = 0
clear_env = yes
catch_workers_output = yes
decorate_workers_output = no
php_admin_value[error_log] = /dev/null
php_admin_flag[log_errors] = on
php_admin_flag[display_errors] = off
'''+ '\n'.join(fpm_environment_lines(forwarded_names))+'\n')


def load(hey, port, duration, concurrency, environment, cpus, request_headers=()):
    header_args = [argument for value in request_headers for argument in ['-H', value]]
    command = pinned([hey, '-z', str(duration)+'s', '-c', str(concurrency), '-cpus', '2', '-t', '5',
                      '-disable-compression', '-disable-redirects', *header_args, 'http://127.0.0.1:'+str(port)+'/'], cpus)
    result = subprocess.run(command, capture_output=True, text=True, env=environment, timeout=duration + 20)
    check(result.returncode == 0, 'hey failed: '+result.stderr)
    raw = result.stdout
    codes = {int(code): int(count) for code, count in re.findall(r'\[(\d{3})\]\s+(\d+) responses', raw)}
    valid = set(codes) == {200} and codes[200] > 0 and 'Error distribution:' not in raw
    network_errors = raw.partition('Error distribution:')[2]
    error_count = sum(count for code, count in codes.items() if code != 200)
    error_count += sum(int(count) for count in re.findall(r'\[(\d+)\]', network_errors))

    def metric(pattern):
        match = re.search(pattern, raw)
        check(match is not None or not valid, 'Cannot parse hey output:\n'+raw)
        return float(match.group(1)) if match else 0

    return {'requests_per_second': metric(r'Requests/sec:\s+([\d.]+)'),
            'total_seconds': metric(r'Total:\s+([\d.]+) secs'),
            'average_ms': metric(r'Average:\s+([\d.]+) secs') * 1000,
            'p50_ms': metric(r'50%+\s+in\s+([\d.]+) secs') * 1000,
            'p95_ms': metric(r'95%+\s+in\s+([\d.]+) secs') * 1000,
            'p99_ms': metric(r'99%+\s+in\s+([\d.]+) secs') * 1000,
            'requests': codes.get(200, 0), 'errors': error_count, 'valid': valid, 'status_codes': codes, 'raw_hey': raw}


def source_digest():
    digest = hashlib.sha256()
    for base in [ROOT / 'src', ROOT / 'stubs/application']:
        for path in sorted(base.rglob('*')):
            if path.is_file() and path.suffix in {'.php', '.stub'} and 'var' not in path.relative_to(base).parts:
                digest.update(str(path.relative_to(ROOT)).encode())
                digest.update(path.read_bytes())
    return digest.hexdigest()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--compiler', default=os.environ.get('PHEX_ELEPHC', 'elephc'))
    parser.add_argument('--rounds', type=int, default=3)
    parser.add_argument('--seconds', type=int, default=5)
    parser.add_argument('--warmup', type=int, default=2)
    parser.add_argument('--concurrency', type=int, default=16)
    parser.add_argument('--modes', nargs='+', choices=['php_fpm', 'native_request', 'native_application'], default=['php_fpm', 'native_request', 'native_application'])
    parser.add_argument('--capture-native-logs', action='store_true', help='Diagnostic run: capture native logs instead of discarding them.')
    parser.add_argument('--runtime-environment', choices=['controlled', 'inherited'], default='controlled',
                        help='Controlled runtime by default; inherited reproduces the original benchmark environment.')
    parser.add_argument('--pass-env', action='append', default=[], metavar='NAME',
                        help='Forward a set variable to both native workers and the FPM pool; values are not reported.')
    parser.add_argument('--output', type=Path, default=ROOT / 'var/benchmarks')
    args = parser.parse_args()
    check(args.rounds > 0 and args.seconds > 0 and args.warmup > 0 and args.concurrency > 0, 'All benchmark sizes must be positive.')
    hey = shutil.which('hey')
    fpm = shutil.which('php-fpm')
    check(hey is not None and fpm is not None and shutil.which('taskset') is not None, 'hey, PHP-FPM and taskset are required.')
    compiler = shutil.which(args.compiler) if '/' not in args.compiler else str(Path(args.compiler).resolve())
    check(compiler is not None, 'Compiler executable missing.')
    check('--web-lifecycle' in subprocess.check_output([compiler, '--help'], text=True), 'Compiler lacks application lifecycle support.')
    affinity = cpu_assignment()
    build_environment = dict(os.environ)
    try:
        runtime = runtime_environment(args.runtime_environment, args.pass_env)
    except ValueError as error:
        parser.error(str(error))
    environment = runtime.values
    metadata = {'started_utc': datetime.now(timezone.utc).isoformat(), 'git_head': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
                'dirty': bool(subprocess.check_output(['git', 'status', '--porcelain'], cwd=ROOT, text=True)),
                'source_sha256': source_digest(), 'native_compiler': subprocess.check_output([compiler, '--version'], text=True).strip(),
                'php_fpm': subprocess.check_output([fpm, '-v'], text=True).splitlines()[0], 'cpu': subprocess.check_output(['lscpu'], text=True),
                'load_average_start': os.getloadavg(), 'affinity': affinity, 'workers': 1, 'max_requests': 0, 'concurrency': args.concurrency,
                'warmup_seconds': args.warmup, 'measured_seconds': args.seconds, 'rounds': args.rounds,
                'endpoint': 'GET / -> {"hello":"world"}', 'logging': 'Phex JSON logging enabled; native output captured in a temporary file for diagnosis' if args.capture_native_logs else 'Phex JSON logging enabled; output discarded to /dev/null',
                'native_isolation': 'worker for both lifecycles',
                'proxy': 'identical Caddy HTTP frontend; FastCGI for PHP-FPM and HTTP for both native lifecycles',
                'php_profile': 'compiled.php, OPcache and preload enabled, timestamp validation off, JIT off',
                'runtime_environment': runtime.describe(),
                'build_environment': {'policy': 'inherited build host; separate from runtime', 'entries': len(build_environment)}, 'runs': []}
    args.output.mkdir(parents=True, exist_ok=True)
    stem = args.output / ('native-lifecycle-'+datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S'))
    metadata['state'] = 'preparing'
    stem.with_suffix('.json').write_text(json.dumps(metadata, indent=2)+'\n')
    with tempfile.TemporaryDirectory(prefix='http-benchmark-', dir=ROOT / 'var') as temporary:
        directory = Path(temporary)
        print('Preparing installed project and temporary Caddy proxy...', flush=True)
        caddy, checksum = download_caddy(directory)
        metadata['caddy'] = subprocess.check_output([str(caddy), 'version'], text=True).strip()
        metadata['caddy_archive_sha256'] = checksum
        project = install_project(directory)
        binaries = {}
        for lifecycle in ['request', 'application']:
            if 'native_'+lifecycle not in args.modes:
                continue
            config = project / 'config/server.php'
            if lifecycle == 'application':
                config.write_text(config.read_text().replace('NativeLifecycle::Request', 'NativeLifecycle::Application'))
            run(['composer', 'build:native'], project, env=dict(build_environment, PHEX_ELEPHC=compiler))
            binary = directory / ('native-'+lifecycle)
            shutil.copy2(project / 'var/build/server', binary)
            binaries['native_'+lifecycle] = binary
        if not binaries:
            run(['composer', 'build'], project, env=build_environment)
        probe = project / 'public/benchmark-status.php'
        probe.write_text('''<?php
header('Content-Type: application/json');
$status = opcache_get_status(false);
echo json_encode(['opcache' => $status['opcache_enabled'] ?? false,
    'preload' => isset($status['preload_statistics']),
    'kernel_preloaded' => class_exists('Phex\\\\Kernel\\\\Application', false),
    'jit' => $status['jit']['enabled'] ?? false]);
''')
        labels = args.modes
        print(json.dumps({key: metadata[key] for key in ['native_compiler', 'php_fpm', 'affinity', 'concurrency', 'rounds']}), flush=True)
        for round_ in range(args.rounds):
            order = labels[round_ % len(labels):] + labels[:round_ % len(labels)]
            for label in order:
                backend_port = available_port()
                if label == 'php_fpm':
                    config = directory / 'fpm.conf'
                    fpm_config(config, backend_port, runtime.forwarded_names)
                    command = [fpm, '-F', '-y', str(config), '-d', 'opcache.enable=1', '-d', 'opcache.validate_timestamps=0',
                               '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0',
                               '-d', 'opcache.preload='+str(project / 'var/generated/php-bootstrap.php')]
                else:
                    command = [str(binaries[label]), '--listen', '127.0.0.1:'+str(backend_port), '--workers', '1', '--max-requests', '0']
                with process(pinned(command, affinity['backend']), backend_port, directory, environment=environment, label=label, quiet=label != 'php_fpm' and not args.capture_native_logs) as backend:
                    if label == 'php_fpm':
                        probe_port = available_port()
                        config = directory / 'Caddyfile-probe'
                        proxy_config(config, probe_port, backend_port, project, probe)
                        with process(pinned([caddy, 'run', '--config', config, '--adapter', 'caddyfile'], affinity['proxy']), probe_port, directory,
                                     environment=environment, label='Caddy probe'):
                            status, _, payload = request(probe_port, 'GET', '/')
                            check(status == 200, 'PHP-FPM diagnostic request failed: '+repr(payload))
                            profile = json.loads(payload)
                            check(profile['opcache'] and profile['preload'] and profile['kernel_preloaded'] and not profile['jit'], 'Unexpected PHP profile: '+repr(profile))
                            metadata['php_verified_profile'] = profile
                    proxy_port = available_port()
                    config = directory / 'Caddyfile'
                    proxy_config(config, proxy_port, backend_port, project, project / 'public/compiled.php' if label == 'php_fpm' else None)
                    with process(pinned([caddy, 'run', '--config', config, '--adapter', 'caddyfile'], affinity['proxy']), proxy_port, directory,
                                 environment=environment, label='Caddy') as proxy:
                        status, headers, payload = request(proxy_port, 'GET', '/')
                        check(status == 200 and json.loads(payload) == {'hello': 'world'} and bool(fields(headers, 'X-Request-Id')), 'Response mismatch for '+label+': '+repr(payload))
                        warmup = load(hey, proxy_port, args.warmup, args.concurrency, environment, affinity['client'])
                        if warmup['valid']:
                            cpu_before = {name: group_cpu_seconds(child.pid) for name, child in [('backend', backend), ('proxy', proxy)]}
                            result = load(hey, proxy_port, args.seconds, args.concurrency, environment, affinity['client'])
                            result['cpu_percent_one_core'] = {name: 100 * (group_cpu_seconds(child.pid) - cpu_before[name]) / result['total_seconds']
                                                              for name, child in [('backend', backend), ('proxy', proxy)]}
                            result['phase'] = 'measurement'
                        else:
                            result = dict(warmup, phase='warmup')
                        result['warmup'] = warmup
                        if not result['valid']:
                            result.pop('cpu_percent_one_core', None)
                            result['backend_exit_status'] = backend.poll()
                            log = backend.benchmark_log
                            log.seek(0)
                            contents = log.read().decode(errors='replace')
                            result['backend_log_tail'] = contents[-8192:]
                            events = []
                            for line in contents.splitlines():
                                try:
                                    record = json.loads(line)
                                except json.JSONDecodeError:
                                    record = None
                                if not isinstance(record, dict) or record.get('message') != 'http.request.completed':
                                    events.append(line)
                            result['backend_events'] = events[-100:]
                        result.update(mode=label, round=round_ + 1, load_average=os.getloadavg(),
                                      runtime_environment=runtime.describe())
                        metadata['runs'].append(result)
                        metadata['state'] = 'running'
                        stem.with_suffix('.json').write_text(json.dumps(metadata, indent=2)+'\n')
                        if result['valid']:
                            print(f"round {round_+1} {label}: {result['requests_per_second']:.1f} req/s, p50 {result['p50_ms']:.1f} ms, p95 {result['p95_ms']:.1f} ms, errors {result['errors']}", flush=True)
                        else:
                            print(f"round {round_+1} {label}: INVALID during {result['phase']}, HTTP {result['status_codes']}, network/HTTP errors {result['errors']}", flush=True)
    summary = {}
    for label in labels:
        all_runs = [row for row in metadata['runs'] if row['mode'] == label]
        runs = [row for row in all_runs if row['valid']]
        if not runs:
            summary[label] = {'valid_runs': 0, 'failed_runs': len(all_runs), 'errors': sum(row['errors'] for row in all_runs)}
            continue
        summary[label] = {key: statistics.median(row[key] for row in runs) for key in ['requests_per_second', 'p50_ms', 'p95_ms', 'p99_ms', 'average_ms']}
        summary[label]['valid_runs'] = len(runs)
        summary[label]['failed_runs'] = len(all_runs) - len(runs)
        summary[label]['rps_min'] = min(row['requests_per_second'] for row in runs)
        summary[label]['rps_max'] = max(row['requests_per_second'] for row in runs)
        summary[label]['requests'] = sum(row['requests'] for row in runs)
        summary[label]['errors'] = sum(row['errors'] for row in all_runs)
    metadata['summary'] = summary
    metadata['load_average_end'] = os.getloadavg()
    metadata['state'] = 'complete'
    stem.with_suffix('.json').write_text(json.dumps(metadata, indent=2)+'\n')
    table = ['| Mode | Median req/s | p50 ms | p95 ms | Req/s range |', '|---|---:|---:|---:|---:|']
    for label, row in summary.items():
        if not row['valid_runs']:
            table.append(f"| {label} | invalid: {row['failed_runs']} failed runs | n/a | n/a | n/a |")
            continue
        table.append(f"| {label} | {row['requests_per_second']:.0f} | {row['p50_ms']:.1f} | {row['p95_ms']:.1f} | {row['rps_min']:.0f}-{row['rps_max']:.0f} |")
    report = '\n'.join(table)+'\n\nLocal closed-loop benchmark: one backend worker, same Caddy frontend, '+str(args.concurrency)+' concurrent clients, '+str(args.rounds)+' rotated rounds of '+str(args.seconds)+' seconds after '+str(args.warmup)+' seconds warm-up. Runtime environment: '+runtime.policy+' ('+str(len(environment))+' entries); build environment is separate. PHP configuration variables are preserved and named application variables are forwarded explicitly to both backends. PHP-FPM uses OPcache/preload and no JIT. Both native variants use the same compiler and worker isolation. '+metadata['logging']+'. GET / has no database or external I/O. Failed warm-up or measurement runs are excluded from performance figures and retained in JSON. Percentile values are medians of per-run hey summaries, with 0.1 ms source resolution. The workstation is shared; these are local full-stack measurements, not a general production capacity claim.\n'
    stem.with_suffix('.md').write_text(report)
    print(report, flush=True)
    print('Results: '+str(stem.with_suffix('.json')), flush=True)


if __name__ == '__main__':
    main()
