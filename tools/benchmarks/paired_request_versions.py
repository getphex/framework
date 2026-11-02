#!/usr/bin/env python3
"""Confirm request changes with adjacent ABBA blocks and already-built binaries."""

import argparse
from contextlib import ExitStack
from datetime import datetime, timezone
import hashlib
import json
import math
import os
from pathlib import Path
import shutil
import statistics
import sys
import tempfile

sys.dont_write_bytecode = True
import native_lifecycle as bench
from request_profile import cpu_snapshot

STUDIES = [
    ('compiler_only', 'native_old_before', 'native_new_before'),
    ('phex_only', 'native_new_before', 'native_new_after'),
    ('environment_only', 'native_new_inherited', 'native_new_after'),
    ('native_vs_fpm', 'php_after', 'native_new_after'),
    ('all_changes', 'native_old_inherited', 'native_new_after'),
    ('native_inherited_vs_fpm', 'php_after', 'native_new_inherited'),
]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--comparison', type=Path, required=True)
    parser.add_argument('--blocks', type=int, default=8)
    parser.add_argument('--seconds', type=int, default=1)
    parser.add_argument('--studies', nargs='+', choices=[row[0] for row in STUDIES], default=[row[0] for row in STUDIES])
    args = parser.parse_args()
    bench.check(args.blocks > 0 and args.seconds > 0, 'Sizes must be positive.')
    original = json.loads(args.comparison.read_text())
    bench.check(original['state'] == 'complete', 'The broad comparison must be complete.')
    for definition in original['cases'].values():
        if 'binary' in definition:
            bench.check(hashlib.sha256(Path(definition['binary']).read_bytes()).hexdigest() == definition['binary_sha256'],
                        'A comparison binary changed: '+definition['binary'])
    output = args.comparison.parent
    stem = output / ('paired-'+datetime.now(timezone.utc).strftime('%H%M%S'))
    source = dict(os.environ)
    affinity = original['affinity']
    result = {'comparison': str(args.comparison.resolve()), 'comparison_sha256': hashlib.sha256(args.comparison.read_bytes()).hexdigest(),
              'blocks': args.blocks, 'seconds': args.seconds,
              'concurrency': original['concurrency'], 'affinity': affinity,
              'method': 'Warm both backends once, then alternate A B B A without restarting; one load process at a time. Each block compares the geometric mean of its two B throughput values with the geometric mean of its two A values. Report the median of block ratios and their range.',
              'studies': {}, 'state': 'preparing'}

    def save():
        stem.with_suffix('.json').write_text(json.dumps(result, indent=2)+'\n')

    save()
    with tempfile.TemporaryDirectory(prefix='paired-request-work-', dir=bench.ROOT / 'var') as temporary:
        workspace = Path(temporary)
        caddy, checksum = bench.download_caddy(workspace)
        result['caddy_archive_sha256'] = checksum
        project = bench.install_project(workspace)
        bench.run(['composer', 'build'], project, env=source)
        for title, first, second in STUDIES:
            if title not in args.studies:
                continue
            print('Starting '+title, flush=True)
            study = {'A': first, 'B': second, 'runs': [], 'blocks': []}
            result['studies'][title] = study
            save()
            with ExitStack() as stack:
                targets = {}
                for label in [first, second]:
                    definition = original['cases'][label]
                    runtime = bench.runtime_environment('inherited' if label.endswith('_inherited') else 'controlled', source=source)
                    backend_port, port = bench.available_port(), bench.available_port()
                    if 'binary' in definition:
                        command = [definition['binary'], '--listen', '127.0.0.1:'+str(backend_port), '--workers', '1', '--max-requests', '0']
                        script = None
                    else:
                        config = workspace / (label+'.fpm.conf')
                        bench.fpm_config(config, backend_port)
                        command = [shutil.which('php-fpm'), '-F', '-y', str(config), '-d', 'opcache.enable=1',
                                   '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0',
                                   '-d', 'opcache.preload='+str(project / 'var/generated/php-bootstrap.php')]
                        script = project / 'public/compiled.php'
                    backend = stack.enter_context(bench.process(bench.pinned(command, affinity['backend']), backend_port, workspace,
                                                                environment=runtime.values, label=label, quiet=True))
                    config = workspace / (label+'.Caddyfile')
                    bench.proxy_config(config, port, backend_port, project, script)
                    proxy = stack.enter_context(bench.process(bench.pinned([caddy, 'run', '--config', config, '--adapter', 'caddyfile'], affinity['proxy']),
                                                              port, workspace, environment=runtime.values, label='Caddy '+label))
                    status, headers, payload = bench.request(port, 'GET', '/')
                    bench.check(status == 200 and json.loads(payload) == {'hello': 'world'} and bool(bench.fields(headers, 'X-Request-Id')), 'Invalid paired response.')
                    if script:
                        probe = project / 'public/paired-status.php'
                        probe.write_text("<?php $s=opcache_get_status(false); echo json_encode([$s['opcache_enabled']??false,isset($s['preload_statistics']),$s['jit']['enabled']??false]);")
                        probe_config = workspace / 'probe.Caddyfile'
                        probe_port = bench.available_port()
                        bench.proxy_config(probe_config, probe_port, backend_port, project, probe)
                        with bench.process(bench.pinned([caddy, 'run', '--config', probe_config, '--adapter', 'caddyfile'], affinity['proxy']),
                                           probe_port, workspace, environment=runtime.values, label='FPM status'):
                            status, _, payload = bench.request(probe_port, 'GET', '/')
                            bench.check(status == 200 and json.loads(payload) == [True, True, False], 'Unexpected PHP paired profile.')
                    warmup = bench.load(shutil.which('hey'), port, 2, result['concurrency'], runtime.values, affinity['client'])
                    bench.check(warmup['valid'], 'Paired warm-up failed.')
                    targets[label] = (port, runtime, backend, proxy)
                pids = {label: cpu_snapshot(target[2].pid)['pids'] for label, target in targets.items()}
                result['state'] = 'running'
                for block in range(args.blocks):
                    values = {first: [], second: []}
                    for label in [first, second, second, first]:
                        port, runtime, backend, proxy = targets[label]
                        before = {name: cpu_snapshot(child.pid) for name, child in [('backend', backend), ('proxy', proxy)]}
                        row = bench.load(shutil.which('hey'), port, args.seconds, result['concurrency'], runtime.values, affinity['client'])
                        after = {name: cpu_snapshot(child.pid) for name, child in [('backend', backend), ('proxy', proxy)]}
                        row.update(case=label, block=block+1, runtime_environment=runtime.describe(),
                                   cpu_microseconds_per_request={name: 1e6*sum(after[name][clock]-before[name][clock] for clock in ['user','system'])/row['requests']
                                                                for name in ['backend','proxy']})
                        study['runs'].append(row)
                        save()
                        bench.check(row['valid'] and after['backend']['pids'] == pids[label], 'Paired errors or worker replacement.')
                        values[label].append(row['requests_per_second'])
                    ratio = math.sqrt(math.prod(values[second])/math.prod(values[first]))
                    study['blocks'].append({'block': block+1, 'ratio': ratio, 'rps': values})
                    save()
                    print(f'{title} block {block+1}: {100*(ratio-1):+.1f}%', flush=True)
            ratios = [block['ratio'] for block in study['blocks']]
            study['summary'] = {'median_change_percent': 100*(statistics.median(ratios)-1),
                                'min_change_percent': 100*(min(ratios)-1), 'max_change_percent': 100*(max(ratios)-1),
                                'A_median_rps': statistics.median(row['requests_per_second'] for row in study['runs'] if row['case'] == first),
                                'B_median_rps': statistics.median(row['requests_per_second'] for row in study['runs'] if row['case'] == second),
                                'errors': sum(row['errors'] for row in study['runs'])}
            save()
    result['state'] = 'complete'
    save()
    print(json.dumps({name: study['summary'] for name,study in result['studies'].items()}, indent=2), flush=True)
    print('Results: '+str(stem.with_suffix('.json')), flush=True)


if __name__ == '__main__':
    main()
