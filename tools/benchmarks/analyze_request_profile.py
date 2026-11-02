#!/usr/bin/env python3
"""Summarize retained request-profile evidence without running another load test."""

from collections import Counter
import json
from pathlib import Path
import re
import statistics
import sys


def summarize(rows):
    if not rows:
        return None
    value = {'runs': len(rows), 'files': [row['_file'] for row in rows],
             'rps_median': statistics.median(row['requests_per_second'] for row in rows),
             'rps_min': min(row['requests_per_second'] for row in rows),
             'rps_max': max(row['requests_per_second'] for row in rows)}
    for subject in ['backend', 'proxy']:
        if subject not in rows[0]['cpu_microseconds_per_request']:
            continue
        clocks = {clock: statistics.median(row['cpu_microseconds_per_request'][subject][clock] for row in rows)
                  for clock in ['user', 'system']}
        clocks['total'] = statistics.median(sum(row['cpu_microseconds_per_request'][subject].values()) for row in rows)
        value[subject+'_cpu_us'] = clocks
    return value


def folded(path):
    total = 0
    disjoint = Counter()
    inclusive = Counter()
    leaves = Counter()
    for line in path.read_text().splitlines():
        stack, count = line.rsplit(' ', 1)
        count = int(count)
        total += count
        leaves[stack.split(';')[-1]] += count
        native_registry = path.name.startswith('native-') and 'mbstring' in stack
        if native_registry:
            category = 'native_mbstring_ini_metadata'
        elif '__rt_gc_' in stack:
            category = 'cycle_gc'
        elif any(name in stack for name in ['__rt_heap_', '__rt_incref', '__rt_decref']):
            category = 'other_native_heap_refcount'
        else:
            category = 'other'
        disjoint[category] += count
        for label, pattern in [('headers', 'Headers'), ('headers_without', 'Headers::without'),
                               ('container_constructor', 'ApplicationContainer::__construct'),
                               ('server_request_conversion', 'ServerRequest::create'), ('request_reset', '__rt_web_reset'),
                               ('json_logging', 'JsonLogger::log')]:
            if pattern in stack:
                inclusive[label] += count
        if '__rt_gc_' in stack and 'Headers::without' in stack:
            inclusive['gc_under_headers_without'] += count
        if any(name in stack for name in ['zend_mm_', '_emalloc', '_efree', '_erealloc']):
            inclusive['zend_memory_allocator'] += count
    return {'total_weight': total, 'weight_unit': 'cycles' if 'language-' not in path.name else 'wall_samples_with_php_stack',
            'disjoint_percent': {key: 100*count/total for key, count in disjoint.items()},
            'inclusive_percent_overlapping': {key: 100*count/total for key, count in inclusive.items()},
            'top_leaves': [{'symbol': name, 'percent': 100*count/total} for name, count in leaves.most_common(20)]}


def main():
    directory = Path(sys.argv[1]).resolve()
    rows = []
    for path in sorted(directory.glob('*.json')):
        row = json.loads(path.read_text())
        if not isinstance(row, dict) or 'case' not in row:
            continue
        row['_file'] = path.name
        if row.get('valid') and not row.get('profiled') and not row.get('syscalls') and row.get('concurrency', 16) == 16:
            rows.append(row)
    groups = {}
    for case in ['native-full', 'php-full', 'native-noop-headers', 'native-no-string-registry',
                 'native-bulk-headers', 'native-no-eager', 'native-no-log', 'native-bare', 'php-bare', 'native-production']:
        for clean in [False, True]:
            for direct in [False, True]:
                selected = [row for row in rows if row['case'] == case and row.get('clean_environment', False) == clean
                            and row.get('direct', False) == direct]
                if case == 'php-full':
                    selected = [row for row in selected if (row.get('verified_php') or {}).get('opcache')]
                if direct:
                    selected = [row for row in selected if row.get('match_proxy_headers')]
                if clean:
                    selected = [row for row in selected if row.get('environment_bytes') == 32]
                if selected:
                    label = case+('-clean' if clean else '')+('-direct-matched' if direct else '')
                    groups[label] = summarize(selected)
    profiles = {path.name: folded(path) for path in directory.glob('*backend.folded')}
    for path in directory.glob('php-language-*.folded'):
        if path.name.startswith('php-language-183130'):
            profiles[path.name] = folded(path)
    exact = {}
    for path in directory.glob('native-exact-*.raw'):
        functions = {}
        for line in path.read_text().splitlines():
            if line.startswith('elephc-instr: '):
                name, metrics = line[len('elephc-instr: '):].split(' calls=', 1)
                functions[name] = {key: int(value) for key, value in re.findall(r'(\w+)=(-?\d+)', 'calls='+metrics)}
        exact[path.name] = functions
    result = {'groups': groups, 'profiles': profiles, 'exact': exact}
    (directory / 'analysis.json').write_text(json.dumps(result, indent=2)+'\n')
    table = ['| Case | Runs | Median req/s | Range | Backend user us | Backend system us | Proxy CPU us |',
             '|---|---:|---:|---:|---:|---:|---:|']
    for label, row in groups.items():
        table.append(f"| {label} | {row['runs']} | {row['rps_median']:.1f} | {row['rps_min']:.1f}-{row['rps_max']:.1f} | "
                     f"{row['backend_cpu_us']['user']:.1f} | {row['backend_cpu_us']['system']:.1f} | "
                     f"{row.get('proxy_cpu_us', {}).get('total', 0):.1f} |")
    (directory / 'measurements.md').write_text('\n'.join(table)+'\n')
    print('\n'.join(table))


if __name__ == '__main__':
    main()
