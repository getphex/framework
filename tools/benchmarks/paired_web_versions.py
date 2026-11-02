#!/usr/bin/env python3
"""Compare retained web binaries from two compiler versions in adjacent ABBA blocks."""

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


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--baseline', type=Path, required=True)
    parser.add_argument('--candidate', type=Path, required=True)
    parser.add_argument('--blocks', type=int, default=8)
    parser.add_argument('--seconds', type=int, default=1)
    parser.add_argument('--workers', nargs='+', type=int, default=[1, 8])
    parser.add_argument('--runtime-environments', nargs='+', choices=['controlled', 'inherited'], default=['controlled', 'inherited'])
    parser.add_argument('--modes', nargs='+', choices=['worker_request', 'worker_application'], default=['worker_request', 'worker_application'])
    parser.add_argument('--resume', type=Path, help='Keep completed studies and retry incomplete cases, retaining every previous attempt')
    args = parser.parse_args()
    resource.setrlimit(resource.RLIMIT_CORE, (0, 0))
    web.bench.check(min([args.blocks, args.seconds, *args.workers]) > 0, 'Sizes must be positive.')
    inputs = {'baseline': args.baseline.resolve(), 'candidate': args.candidate.resolve()}
    versions = {label: json.loads(path.read_text()) for label, path in inputs.items()}
    for label, metadata in versions.items():
        web.bench.check(metadata['state'] == 'complete', label+' matrix is incomplete.')
        web.bench.check(metadata['source_sha256'] == web.bench.source_digest(), label+' application sources differ.')
        for mode in args.modes:
            binary = metadata['binaries'][mode]
            web.bench.check(web.digest(Path(binary['path'])) == binary['sha256'], label+' binary changed: '+mode)
    before, after = versions['baseline'], versions['candidate']
    for key in ['source_sha256', 'concurrency', 'proxy_threads', 'allowed_cpus', 'caddy_archive_sha256']:
        web.bench.check(before[key] == after[key], 'Comparison protocol differs: '+key)
    web.bench.check(sorted(os.sched_getaffinity(0)) == after['allowed_cpus'], 'Available CPUs changed.')
    output = args.resume.resolve() if args.resume else args.candidate.parent / ('paired-versions-'+datetime.now(timezone.utc).strftime('%H%M%S'))
    if not args.resume:
        output.mkdir()
    result = {'state': 'preparing', 'started_utc': datetime.now(timezone.utc).isoformat(),
              'inputs': {label: {'path': str(path), 'sha256': web.digest(path),
                                'compiler_source': versions[label]['compiler_source'],
                                'compiler_sha256': versions[label]['compiler_sha256'],
                                'binaries': {mode: versions[label]['binaries'][mode] for mode in args.modes}}
                         for label, path in inputs.items()},
              'blocks': args.blocks, 'seconds': args.seconds, 'warmup_seconds': 2,
              'concurrency': after['concurrency'], 'proxy_threads': after['proxy_threads'],
              'allowed_cpus': after['allowed_cpus'], 'source_sha256': after['source_sha256'],
              'method': 'Both versions stay running; one active load at a time. Warm each once, then alternate baseline, candidate, candidate, baseline. Report medians and the range of within-block geometric-mean candidate/baseline ratios. All successful legs are retained.',
              'studies': []}
    if args.resume:
        previous = json.loads((output/'results.json').read_text())
        for key in ['inputs', 'blocks', 'seconds', 'concurrency', 'proxy_threads', 'allowed_cpus', 'source_sha256']:
            web.bench.check(previous[key] == result[key], 'Resume protocol changed: '+key)
        for study in previous['studies']:
            if 'summary' not in study:
                study['failure'] = 'Incomplete prior attempt; all raw legs and invalid responses retained.'
        previous.setdefault('resumptions', []).append({'utc': datetime.now(timezone.utc).isoformat(),
            'reason': 'Retry incomplete cases while preserving completed studies and every previous attempt.'})
        result = previous
        result['state'] = 'preparing'

    def save():
        (output/'results.json').write_text(json.dumps(result, indent=2)+'\n')

    source = dict(os.environ)
    save()
    print('Results: '+str(output/'results.json'), flush=True)
    with tempfile.TemporaryDirectory(prefix='paired-web-work-', dir=web.bench.ROOT/'var') as temporary:
        workspace = Path(temporary)
        caddy, checksum = web.bench.download_caddy(workspace)
        web.bench.check(checksum == after['caddy_archive_sha256'], 'Proxy archive changed.')
        project = web.bench.install_project(workspace)
        web.bench.run(['composer', 'build'], project, env=source)
        for policy in args.runtime_environments:
            runtime = web.bench.runtime_environment(policy, source=source)
            for workers in args.workers:
                for mode in args.modes:
                    label = f'{policy}/{mode}/w{workers}'
                    if any(study['label'] == label and 'summary' in study and len(study['blocks']) == args.blocks
                           and all(row['valid'] for row in study['runs']) for study in result['studies']):
                        print('Preserving completed '+label, flush=True)
                        continue
                    print('Starting '+label, flush=True)
                    definition = {'mode': mode, 'isolation': 'worker', 'lifecycle': web.MODES[mode][1],
                                  'capacity': workers, 'web_workers': workers, 'handlers_per_worker': 1}
                    study = {'label': label, 'runtime_environment': runtime.describe(),
                             'definition': definition, 'preflights': {}, 'runs': [], 'blocks': [], 'warmups': {}}
                    result['studies'].append(study)
                    save()
                    with ExitStack() as stack:
                        targets = {}
                        for version, metadata in versions.items():
                            directory = workspace/version
                            directory.mkdir(exist_ok=True)
                            study['preflights'][version] = web.preflight(directory, output, project, caddy, metadata['binaries'],
                                definition, runtime, after['proxy_threads'], version+'-'+label)
                            save()
                            web.bench.check(study['preflights'][version]['valid'], version+' preflight failed.')
                            current = stack.enter_context(web.target(directory, project, caddy, metadata['binaries'],
                                definition, runtime, after['proxy_threads']))
                            web.verify_response(current['port'])
                            study['warmups'][version] = web.bench.load(shutil.which('hey'), current['port'], 2,
                                after['concurrency'], runtime.values, after['allowed_cpus'])
                            web.bench.check(study['warmups'][version]['valid'], version+' warm-up failed.')
                            targets[version] = current
                        owners = {version: web.process_owners(current) for version, current in targets.items()}
                        study['initial_process_owners'] = owners
                        result['state'] = 'running'
                        for block in range(args.blocks):
                            values = {'baseline': [], 'candidate': []}
                            for version in ['baseline', 'candidate', 'candidate', 'baseline']:
                                current = targets[version]
                                start = {name: web.cpu_snapshot(current[name].pid) for name in ['backend', 'proxy']}
                                row = web.bench.load(shutil.which('hey'), current['port'], args.seconds,
                                    after['concurrency'], runtime.values, after['allowed_cpus'])
                                end = {name: web.cpu_snapshot(current[name].pid) for name in ['backend', 'proxy']}
                                current_owners = web.process_owners(current)
                                row.update(version=version, block=block+1, process_owners=current_owners,
                                           persistent_processes_stable=current_owners == owners[version],
                                           host_load=os.getloadavg(), cpu_before=start, cpu_after=end, cpu={},
                                           backend_exit=current['backend'].poll(), proxy_exit=current['proxy'].poll())
                                row['valid'] = row['valid'] and row['persistent_processes_stable'] and row['backend_exit'] is None and row['proxy_exit'] is None
                                if row['requests']:
                                    for name in ['backend', 'proxy']:
                                        total = sum(end[name][key]-start[name][key] for key in ['user', 'system'])
                                        row['cpu'][name] = {'total_us': 1e6*total/row['requests'], 'cores': total/row['total_seconds']}
                                study['runs'].append(row)
                                if not row['valid']:
                                    study['failure'] = f"Invalid HTTP/process result: backend exit={row['backend_exit']}, proxy exit={row['proxy_exit']}"
                                    result['state'] = 'failed'
                                    proxy_log = current['proxy'].benchmark_log
                                    proxy_log.seek(0)
                                    (output/(label.replace('/', '-')+'-'+version+'-failure.proxy.log')).write_bytes(proxy_log.read())
                                save()
                                web.bench.check(row['valid'], study.get('failure', 'HTTP errors or persistent process replacement.'))
                                values[version].append(row['requests_per_second'])
                            ratio = math.sqrt(math.prod(values['candidate'])/math.prod(values['baseline']))
                            study['blocks'].append({'block': block+1, 'ratio': ratio, 'rps': values})
                            save()
                            print(f'{label} block {block+1}: {100*(ratio-1):+.1f}%', flush=True)
                    ratios = [block['ratio'] for block in study['blocks']]
                    study['summary'] = {'paired_median_percent': 100*(statistics.median(ratios)-1),
                                        'paired_min_percent': 100*(min(ratios)-1), 'paired_max_percent': 100*(max(ratios)-1),
                                        'errors': sum(row['errors'] for row in study['runs'])}
                    for version in versions:
                        rows = [row for row in study['runs'] if row['version'] == version]
                        study['summary'][version] = {key: statistics.median(row[key] for row in rows)
                                                    for key in ['requests_per_second', 'p95_ms', 'p99_ms']}
                        study['summary'][version]['backend_cpu_us'] = statistics.median(row['cpu']['backend']['total_us'] for row in rows)
                        study['summary'][version]['proxy_cpu_us'] = statistics.median(row['cpu']['proxy']['total_us'] for row in rows)
                    save()
    result.update(state='complete', finished_utc=datetime.now(timezone.utc).isoformat())
    save()
    print(json.dumps({study['label']: study['summary'] for study in result['studies'] if 'summary' in study}, indent=2), flush=True)
    print('Results: '+str(output/'results.json'), flush=True)


if __name__ == '__main__':
    main()
