#!/usr/bin/env python3
"""Separate compiler, immutable header removal and runtime environment improvements."""

import argparse
from contextlib import redirect_stdout
from datetime import datetime, timezone
import hashlib
import io
import json
import os
from pathlib import Path
import shutil
import statistics
import subprocess
import sys
import tempfile

sys.dont_write_bytecode = True
import native_lifecycle as bench
import request_profile as profile

ROOT = bench.ROOT


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def compiler_details(value):
    path = shutil.which(value) if '/' not in value else str(Path(value).resolve())
    bench.check(path is not None and Path(path).is_file(), 'Missing compiler: '+value)
    return {'path': path, 'version': subprocess.check_output([path, '--version'], text=True).strip(),
            'sha256': digest(Path(path)),
            'sdk_sha256': {archive.name: digest(archive) for archive in sorted(Path(path).parent.glob('libelephc_*.a'))}}


def summarize(runs, labels):
    result = {}
    for label in labels:
        rows = [row for row in runs if row['case'] == label]
        valid = [row for row in rows if row['valid']]
        result[label] = {'valid_runs': len(valid), 'failed_runs': len(rows)-len(valid),
                         'errors': sum(row['errors'] for row in rows),
                         'runtime_environment': rows[0]['runtime_environment'] if rows else None}
        if not valid:
            continue
        result[label].update({key: statistics.median(row[key] for row in valid)
                              for key in ['requests_per_second', 'p50_ms', 'p95_ms', 'p99_ms']})
        result[label].update(rps_min=min(row['requests_per_second'] for row in valid),
                             rps_max=max(row['requests_per_second'] for row in valid),
                             requests=sum(row['requests'] for row in valid),
                             backend_cpu_us=statistics.median(sum(row['cpu_microseconds_per_request']['backend'].values()) for row in valid),
                             proxy_cpu_us=statistics.median(sum(row['cpu_microseconds_per_request']['proxy'].values()) for row in valid))
    return result


def report(metadata):
    lines = ['| Case | Median req/s | Range | p95 ms | Backend CPU us/request | Proxy CPU us/request |',
             '|---|---:|---:|---:|---:|---:|']
    for label, row in metadata['summary'].items():
        if row['valid_runs']:
            lines.append(f"| {label} | {row['requests_per_second']:.1f} | {row['rps_min']:.1f}-{row['rps_max']:.1f} | {row['p95_ms']:.1f} | {row['backend_cpu_us']:.1f} | {row['proxy_cpu_us']:.1f} |")
        else:
            lines.append(f"| {label} | invalid | | | | |")
    lines += ['', '| Controlled change | Throughput change |', '|---|---:|']
    for name, before, after in [
        ('Compiler only, controlled environment', 'native_old_before', 'native_new_before'),
        ('Phex header guard only, new compiler', 'native_new_before', 'native_new_after'),
        ('Controlled environment only, old compiler', 'native_old_inherited', 'native_old_before'),
        ('Controlled environment only, new compiler', 'native_new_inherited', 'native_new_after'),
        ('All changes versus old inherited environment', 'native_old_inherited', 'native_new_after'),
        ('New native Request versus PHP-FPM', 'php_after', 'native_new_after'),
    ]:
        first, second = metadata['summary'][before], metadata['summary'][after]
        if first['valid_runs'] and second['valid_runs']:
            change = 100*(second['requests_per_second']/first['requests_per_second']-1)
            lines.append(f'| {name} | {change:+.1f}% |')
    lines += ['', 'One worker, worker isolation, no recycling, identical Caddy frontend, OPcache/preload with JIT disabled for PHP-FPM. '
              f"GET / returns the same JSON, with logging enabled to /dev/null. {metadata['rounds']} rotated rounds, "
              f"{metadata['seconds']} measured seconds after {metadata['warmup_seconds']} warm-up seconds, {metadata['concurrency']} clients. "
              'Each round starts fresh workers; PHP-FPM is restarted after switching the header source. '
              'Runtime environment policies are recorded per case. Build tools inherit the separate build environment. '
              'All application and framework sources other than Headers.php remain identical. '
              'These are local full-stack measurements on a shared workstation; range and raw runs are retained.', '']
    lines.append(f"Measured successful responses: {sum(row['requests'] for row in metadata['runs'])}; "
                 f"HTTP/network errors: {sum(row['errors'] for row in metadata['runs'])}. "
                 'Worker PID sets are checked before and after every measured run.')
    return '\n'.join(lines)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--before-compiler', required=True)
    parser.add_argument('--after-compiler', required=True)
    parser.add_argument('--before-ref', help='Source revision or release identifier for provenance')
    parser.add_argument('--after-ref', help='Source revision or release identifier for provenance')
    parser.add_argument('--before-headers', type=Path, required=True)
    parser.add_argument('--rounds', type=int, default=5)
    parser.add_argument('--seconds', type=int, default=5)
    parser.add_argument('--warmup', type=int, default=2)
    parser.add_argument('--concurrency', type=int, default=16)
    parser.add_argument('--output', type=Path, default=ROOT / 'var/benchmarks')
    args = parser.parse_args()
    args.output = args.output.resolve()
    bench.check(min(args.rounds, args.seconds, args.warmup, args.concurrency) > 0, 'Benchmark sizes must be positive.')
    for name in ['php-fpm', 'hey', 'taskset', 'composer', 'gh']:
        bench.check(shutil.which(name) is not None, 'Missing tool: '+name)
    compilers = {'before': compiler_details(args.before_compiler), 'after': compiler_details(args.after_compiler)}
    for label, revision in [('before', args.before_ref), ('after', args.after_ref)]:
        compilers[label]['source_ref'] = revision
    before = args.before_headers.read_bytes()
    after = (ROOT / 'src/Http/Message/Headers.php').read_bytes()
    bench.check(before != after, 'Before and after header sources must differ.')
    args.output.mkdir(parents=True, exist_ok=True)
    output = args.output / ('request-comparison-'+datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S'))
    output.mkdir()
    (output / 'binaries').mkdir()
    (output / 'headers-before.php').write_bytes(before)
    (output / 'headers-after.php').write_bytes(after)
    build_environment = dict(os.environ)
    metadata = {'state': 'preparing', 'started_utc': datetime.now(timezone.utc).isoformat(), 'compilers': compilers,
                'git_head': subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip(),
                'source_sha256': bench.source_digest(), 'headers_sha256': {'before': hashlib.sha256(before).hexdigest(), 'after': hashlib.sha256(after).hexdigest()},
                'build_environment': {'policy': 'inherited build host; separate from runtime', 'entries': len(build_environment)},
                'php_fpm': subprocess.check_output(['php-fpm', '-v'], text=True).splitlines()[0],
                'cpu': subprocess.check_output(['lscpu'], text=True), 'affinity': bench.cpu_assignment(),
                'native_compile_flags': {'request': ['--web', '--strict-php'],
                                         'application': ['--web', '--strict-php', '--web-isolation=worker', '--web-lifecycle=application']},
                'workers': 1, 'max_requests': 0,
                'rounds': args.rounds, 'seconds': args.seconds, 'warmup_seconds': args.warmup, 'concurrency': args.concurrency,
                'runs': [], 'load_average_start': os.getloadavg()}
    checkpoint = output / 'comparison.json'

    def save():
        checkpoint.write_text(json.dumps(metadata, indent=2)+'\n')

    save()
    with tempfile.TemporaryDirectory(prefix='request-comparison-work-', dir=ROOT / 'var') as temporary:
        workspace = Path(temporary)
        caddy, checksum = bench.download_caddy(workspace)
        metadata.update(caddy=subprocess.check_output([caddy, 'version'], text=True).strip(), caddy_archive_sha256=checksum)
        project = bench.install_project(workspace)
        headers = project / 'vendor/getphex/phex/src/Http/Message/Headers.php'
        server_config = project / 'config/server.php'
        original_config = server_config.read_text()
        definitions = {}
        for label, compiler, contents, application in [
            ('native_old_before', 'before', before, False),
            ('native_new_before', 'after', before, False),
            ('native_new_after', 'after', after, False),
            ('native_application_after', 'after', after, True),
        ]:
            headers.write_bytes(contents)
            server_config.write_text(original_config.replace('NativeLifecycle::Request', 'NativeLifecycle::Application') if application else original_config)
            print('Building '+label+'...', flush=True)
            bench.run(['composer', 'build:native'], project, env=dict(build_environment, PHEX_ELEPHC=compilers[compiler]['path']))
            target = output / 'binaries' / label
            shutil.copy2(project / 'var/build/server', target)
            definitions[label] = {'binary': str(target), 'binary_sha256': digest(target)}
        headers.write_bytes(after)
        server_config.write_text(original_config)
        definitions.update(php_before={'script': str(project / 'public/compiled.php')},
                           php_after={'script': str(project / 'public/compiled.php')},
                           native_old_inherited=dict(definitions['native_old_before']),
                           native_new_inherited=dict(definitions['native_new_after']))
        labels = ['native_old_before', 'native_new_before', 'native_new_after', 'php_before', 'php_after',
                  'native_old_inherited', 'native_new_inherited', 'native_application_after']
        metadata.update(state='running', cases=definitions)
        state = {'output': str(output), 'project': str(project), 'cases': definitions,
                 'environment': build_environment, 'affinity': metadata['affinity']}
        save()
        print('Results: '+str(checkpoint), flush=True)
        for round_ in range(args.rounds):
            # Rotate starting cases and reverse alternate rounds to limit ordering effects.
            order = labels[round_ % len(labels):]+labels[:round_ % len(labels)]
            if round_ % 2:
                order.reverse()
            for label in order:
                headers.write_bytes(before if label == 'php_before' else after)
                policy = 'inherited' if label.endswith('_inherited') else 'controlled'
                with redirect_stdout(io.StringIO()):
                    result = profile.execute(workspace, state, label, seconds=args.seconds, concurrency=args.concurrency,
                                             runtime_policy=policy, warmup_seconds=args.warmup)
                result['round'] = round_+1
                metadata['runs'].append(result)
                save()
                bench.check(result['valid'], 'Invalid run: '+result['artifact'])
                print(f"round {round_+1} {label}: {result['requests_per_second']:.1f} req/s, p95 {result['p95_ms']:.1f} ms, errors {result['errors']}", flush=True)
    metadata.update(state='complete', summary=summarize(metadata['runs'], labels), load_average_end=os.getloadavg())
    save()
    text = report(metadata)
    (output / 'report.md').write_text(text)
    print(text, flush=True)
    print('Results: '+str(checkpoint), flush=True)


if __name__ == '__main__':
    main()
