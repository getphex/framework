#!/usr/bin/env python3
"""Measure direct native HTTP, the same backend through Caddy, and Caddy/FPM."""

import argparse
from contextlib import ExitStack
from datetime import datetime, timezone
import json
import math
import os
from pathlib import Path
import resource
import shutil
import statistics
import sys
import tempfile

sys.dont_write_bytecode = True
import web_mode_comparison as web

SUBJECTS = ['php_fpm', 'native_proxy', 'native_direct']
PAIRS = [('native_direct', 'php_fpm'), ('native_proxy', 'php_fpm'),
         ('native_direct', 'native_proxy')]


def summarize(study):
    result = {'errors': sum(row['errors'] for row in study['runs'])}
    for subject in SUBJECTS:
        rows = [row for row in study['runs'] if row['subject'] == subject]
        result[subject] = {key: statistics.median(row[key] for row in rows)
                           for key in ['requests_per_second', 'p95_ms', 'p99_ms']}
        for component in ['backend', 'proxy']:
            result[subject][component+'_cpu_us'] = statistics.median(
                row['cpu'][component]['total_us'] for row in rows)
        result[subject]['proxy_used'] = subject != 'native_direct'
    result['comparisons'] = {}
    for numerator, denominator in PAIRS:
        ratios = [block['ratios'][numerator+'/'+denominator] for block in study['blocks']]
        result['comparisons'][numerator+'/'+denominator] = {
            'median_percent': 100*(statistics.median(ratios)-1),
            'min_percent': 100*(min(ratios)-1),
            'max_percent': 100*(max(ratios)-1),
            'positive_blocks': sum(ratio > 1 for ratio in ratios), 'blocks': len(ratios)}
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--matrix', type=Path, required=True,
                        help='Complete pinned matrix supplying verified native binaries and protocol')
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--resume', action='store_true')
    parser.add_argument('--blocks', type=int, default=8)
    parser.add_argument('--seconds', type=int, default=1)
    parser.add_argument('--workers', nargs='+', type=int, default=[1, 8])
    parser.add_argument('--runtime-environments', nargs='+', choices=['controlled', 'inherited'], default=['inherited'])
    parser.add_argument('--modes', nargs='+', choices=['worker_request', 'worker_application'],
                        default=['worker_request', 'worker_application'])
    args = parser.parse_args()
    resource.setrlimit(resource.RLIMIT_CORE, (0, 0))
    web.bench.check(min([args.blocks, args.seconds, *args.workers]) > 0, 'Sizes must be positive.')
    matrix_path = args.matrix.resolve()
    metadata = json.loads(matrix_path.read_text())
    web.bench.check(metadata['state'] == 'complete', 'Input matrix must be complete.')
    web.bench.check(metadata['source_sha256'] == web.bench.source_digest(), 'Application sources changed.')
    cpus = sorted(os.sched_getaffinity(0))
    web.bench.check(cpus == metadata['allowed_cpus'], 'Available CPUs changed.')
    for mode in args.modes:
        definition = metadata['binaries'][mode]
        web.bench.check(web.digest(Path(definition['path'])) == definition['sha256'], 'Native binary changed: '+mode)
    output = args.output.resolve()
    output.mkdir(exist_ok=True)
    protocol = {'input_matrix': str(matrix_path), 'input_sha256': web.digest(matrix_path),
                'source_sha256': metadata['source_sha256'], 'compiler_source': metadata['compiler_source'],
                'compiler_sha256': metadata['compiler_sha256'], 'binaries': metadata['binaries'],
                'allowed_cpus': cpus, 'concurrency': metadata['concurrency'],
                'proxy_threads': metadata['proxy_threads'], 'blocks': args.blocks, 'seconds': args.seconds,
                'workers': args.workers, 'runtime_environments': args.runtime_environments,
                'modes': args.modes, 'caddy_archive_sha256': metadata['caddy_archive_sha256']}
    result = {'state': 'preparing', 'started_utc': datetime.now(timezone.utc).isoformat(),
              'protocol': protocol, 'method': 'One native backend serves both direct HTTP and proxied HTTP. '
              'FPM has matched static capacity and verified OPcache/preload, JIT disabled. All three targets '
              'stay running. One active load at a time, each block visits all subjects then reverses the '
              'order; the starting subject rotates each block. Percentages use within-block geometric '
              'mean ratios; rate columns use marginal medians. All rates are instance totals.', 'studies': []}
    if args.resume:
        result = json.loads((output/'results.json').read_text())
        web.bench.check(result['protocol'] == protocol, 'Resume protocol changed.')
        result.setdefault('resumptions', []).append(datetime.now(timezone.utc).isoformat())
        result['state'] = 'preparing'

    def save():
        (output/'results.json').write_text(json.dumps(result, indent=2)+'\n')

    save()
    print('Results: '+str(output/'results.json'), flush=True)
    source = dict(os.environ)
    with tempfile.TemporaryDirectory(prefix='frontend-web-work-', dir=web.bench.ROOT/'var') as temporary:
        workspace = Path(temporary)
        caddy, checksum = web.bench.download_caddy(workspace)
        web.bench.check(checksum == protocol['caddy_archive_sha256'], 'Proxy archive changed.')
        project = web.bench.install_project(workspace)
        web.bench.run(['composer', 'build'], project, env=source)
        for policy in args.runtime_environments:
            runtime = web.bench.runtime_environment(policy, source=source)
            for workers in args.workers:
                for mode in args.modes:
                    label = f'{policy}/{mode}/w{workers}'
                    if any(study['label'] == label and 'summary' in study for study in result['studies']):
                        print('Preserving completed '+label, flush=True)
                        continue
                    print('Starting '+label, flush=True)
                    definition = {'mode': mode, 'isolation': 'worker', 'lifecycle': web.MODES[mode][1],
                                  'capacity': workers, 'web_workers': workers, 'handlers_per_worker': 1}
                    study = {'label': label, 'definition': definition, 'runtime_environment': runtime.describe(),
                             'runs': [], 'blocks': [], 'warmups': {}}
                    result['studies'].append(study)
                    save()
                    study['preflight'] = web.preflight(workspace, output, project, caddy, metadata['binaries'],
                        definition, runtime, protocol['proxy_threads'], label)
                    save()
                    web.bench.check(study['preflight']['valid'], 'Native preflight failed.')
                    fpm_definition = dict(definition, mode='php_fpm', isolation='fpm', lifecycle='request')
                    with ExitStack() as stack:
                        native = stack.enter_context(web.target(workspace, project, caddy, metadata['binaries'],
                            definition, runtime, protocol['proxy_threads']))
                        fpm = stack.enter_context(web.target(workspace, project, caddy, metadata['binaries'],
                            fpm_definition, runtime, protocol['proxy_threads']))
                        direct_port = int(native['command'][native['command'].index('--listen')+1].rsplit(':', 1)[1])
                        targets = {'php_fpm': dict(fpm), 'native_proxy': dict(native),
                                   'native_direct': dict(native, port=direct_port)}
                        study['start_commands'] = {'native': native['command'], 'php_fpm': fpm['command']}
                        study['ports'] = {name: target['port'] for name, target in targets.items()}
                        study['verified_php'] = fpm['verified_php']
                        for subject, current in targets.items():
                            web.verify_response(current['port'])
                            study['warmups'][subject] = web.bench.load(shutil.which('hey'), current['port'], 2,
                                protocol['concurrency'], runtime.values, cpus)
                            save()
                            web.bench.check(study['warmups'][subject]['valid'], subject+' warm-up failed.')
                        owners = {name: web.process_owners(current) for name, current in targets.items()}
                        study['initial_process_owners'] = owners
                        result['state'] = 'running'
                        for block in range(args.blocks):
                            rotated = SUBJECTS[block % 3:]+SUBJECTS[:block % 3]
                            order = rotated+list(reversed(rotated))
                            values = {subject: [] for subject in SUBJECTS}
                            for subject in order:
                                current = targets[subject]
                                start = {name: web.cpu_snapshot(current[name].pid) for name in ['backend', 'proxy']}
                                row = web.bench.load(shutil.which('hey'), current['port'], args.seconds,
                                    protocol['concurrency'], runtime.values, cpus)
                                end = {name: web.cpu_snapshot(current[name].pid) for name in ['backend', 'proxy']}
                                current_owners = {name: web.process_owners(target) for name, target in targets.items()}
                                exits = {name: {component: target[component].poll() for component in ['backend', 'proxy']}
                                         for name, target in targets.items()}
                                row.update(subject=subject, block=block+1, process_owners=current_owners,
                                           persistent_processes_stable=current_owners == owners,
                                           host_load=os.getloadavg(), cpu_before=start, cpu_after=end,
                                           cpu={}, process_exits=exits, proxy_used=subject != 'native_direct')
                                row['valid'] = row['valid'] and row['persistent_processes_stable'] and all(
                                    value is None for components in exits.values() for value in components.values())
                                if row['requests']:
                                    for name in ['backend', 'proxy']:
                                        total = sum(end[name][key]-start[name][key] for key in ['user', 'system'])
                                        row['cpu'][name] = {'total_us': 1e6*total/row['requests'],
                                                            'cores': total/row['total_seconds']}
                                study['runs'].append(row)
                                if not row['valid']:
                                    study['failure'] = 'Invalid HTTP/process result: '+repr(exits)
                                    result['state'] = 'failed'
                                    for name, target in [('native', native), ('php_fpm', fpm)]:
                                        log = target['proxy'].benchmark_log
                                        log.seek(0)
                                        (output/(label.replace('/', '-')+'-'+name+'-failure.proxy.log')).write_bytes(log.read())
                                save()
                                web.bench.check(row['valid'], study.get('failure', 'Invalid HTTP response.'))
                                values[subject].append(row['requests_per_second'])
                            ratios = {a+'/'+b: math.sqrt(math.prod(values[a])/math.prod(values[b])) for a,b in PAIRS}
                            study['blocks'].append({'block': block+1, 'order': order, 'rps': values, 'ratios': ratios})
                            save()
                            print(label+f' block {block+1}: '+', '.join(
                                name+f' {100*(ratio-1):+.1f}%' for name, ratio in ratios.items()), flush=True)
                    study['summary'] = summarize(study)
                    save()
    result.update(state='complete', finished_utc=datetime.now(timezone.utc).isoformat())
    save()
    print(json.dumps({study['label']: study['summary'] for study in result['studies'] if 'summary' in study}, indent=2))


if __name__ == '__main__':
    main()
