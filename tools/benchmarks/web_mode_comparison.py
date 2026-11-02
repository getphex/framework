#!/usr/bin/env python3
"""Compare every supported Phex web lifecycle/isolation mode with matched FPM controls."""

import argparse
from contextlib import contextmanager, ExitStack
from datetime import datetime, timezone
import hashlib
import json
import math
import os
from pathlib import Path
import resource
import shutil
import statistics
import subprocess
import sys
import tempfile
import time

sys.dont_write_bytecode = True
import native_lifecycle as bench

MODES = {
    'worker_request': ('worker', 'request'),
    'worker_application': ('worker', 'application'),
    'pool_request': ('pool', 'request'),
    'fork_request': ('request', 'request'),
}


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def cpu_snapshot(group):
    """Include waited-for child CPU, which otherwise disappears in fork-per-request mode."""
    ticks = {'own_user': 0, 'own_system': 0, 'reaped_user': 0, 'reaped_system': 0}
    processes = []
    for path in Path('/proc').glob('[0-9]*/stat'):
        try:
            raw = path.read_text()
        except OSError:
            continue
        fields = raw[raw.rfind(')')+2:].split()
        if int(fields[2]) != group:
            continue
        for name, index in [('own_user', 11), ('own_system', 12), ('reaped_user', 13), ('reaped_system', 14)]:
            ticks[name] += int(fields[index])
        processes.append({'pid': int(path.parent.name), 'parent': int(fields[1]), 'state': fields[0]})
    frequency = os.sysconf('SC_CLK_TCK')
    result = {name: value/frequency for name, value in ticks.items()}
    result.update(user=result['own_user']+result['reaped_user'], system=result['own_system']+result['reaped_system'], processes=processes)
    return result


def process_owners(target):
    snapshot = cpu_snapshot(target['backend'].pid)
    workers = sorted(row['pid'] for row in snapshot['processes'] if row['parent'] == target['backend'].pid and row['state'] != 'Z')
    brokers = sorted(row['pid'] for row in snapshot['processes'] if row['parent'] in workers and row['state'] != 'Z')
    handlers = sorted(row['pid'] for row in snapshot['processes'] if row['parent'] in brokers and row['state'] != 'Z')
    return {'workers': workers, 'brokers': brokers, 'handlers': handlers}


def verify_response(port):
    identities = []
    for _ in range(12):
        status, headers, payload = bench.request(port, 'GET', '/')
        bench.check(status == 200 and json.loads(payload) == {'hello': 'world'}, 'Unexpected comparison response.')
        values = bench.fields(headers, 'X-Request-Id')
        bench.check(len(values) == 1, 'Missing correlation header.')
        identities.extend(values)
    bench.check(len(identities) == len(set(identities)), 'Request IDs were reused.')


def prepare(workspace, output, compiler, environment, reuse=None, modes=None):
    caddy, checksum = bench.download_caddy(workspace)
    project = bench.install_project(workspace)
    bench.run(['composer', 'build'], project, env=environment)
    if reuse is not None:
        binaries, invalid = reuse
        for definition in binaries.values():
            bench.check(digest(Path(definition['path'])) == definition['sha256'], 'A retained binary changed.')
        return project, caddy, checksum, binaries, invalid
    binaries = {}
    (output / 'binaries').mkdir()
    (output / 'build-logs').mkdir()
    for name in modes if modes is not None else MODES:
        isolation, lifecycle = MODES[name]
        entry = project / 'native' / ('application.php' if lifecycle == 'application' else 'main.php')
        command = [compiler, '--web', '--strict-php', '--web-isolation='+isolation, '--web-lifecycle='+lifecycle, str(entry)]
        print('Building '+name, flush=True)
        log = bench.run(command, project, env=environment)
        (output / 'build-logs' / (name+'.log')).write_text(log)
        binary = output / 'binaries' / name
        shutil.copy2(entry.with_suffix(''), binary)
        assembly = output / 'build-logs' / (name+'.s')
        shutil.copy2(entry.with_suffix('.s'), assembly)
        binaries[name] = {'path': str(binary), 'sha256': digest(binary), 'command': command, 'isolation': isolation, 'lifecycle': lifecycle}
        binaries[name]['assembly'] = {'path': str(assembly), 'sha256': digest(assembly)}
    invalid = []
    fixture = workspace / 'invalid-combination.php'
    fixture.write_text("<?php echo 'invalid combination';\n")
    for isolation in ['pool', 'request']:
        command = [compiler, '--web', '--web-isolation='+isolation, '--web-lifecycle=application', str(fixture)]
        rejected = subprocess.run(command, capture_output=True, text=True, env=environment)
        diagnostic = rejected.stdout+rejected.stderr
        bench.check(rejected.returncode != 0 and 'worker' in diagnostic.lower() and 'application' in diagnostic.lower(), 'Unexpected combination validation: '+diagnostic)
        invalid.append({'isolation': isolation, 'lifecycle': 'application', 'exit': rejected.returncode, 'diagnostic': diagnostic})
    return project, caddy, checksum, binaries, invalid


@contextmanager
def target(workspace, project, caddy, binaries, definition, runtime, proxy_threads, *, capture=False):
    native = definition['mode'] != 'php_fpm'
    backend_port, port = bench.available_port(), bench.available_port()
    if native:
        command = [binaries[definition['mode']]['path'], '--listen', '127.0.0.1:'+str(backend_port),
                   '--workers', str(definition['web_workers']), '--max-requests', '0']
        if definition['isolation'] in ['pool', 'request']:
            command += ['--handler-concurrency', str(definition['handlers_per_worker'])]
        if definition['isolation'] == 'pool':
            command += ['--max-handler-requests', '0']
        script = None
    else:
        config = workspace / 'fpm.conf'
        bench.fpm_config(config, backend_port, runtime.forwarded_names, workers=definition['capacity'])
        command = [shutil.which('php-fpm'), '-F', '-y', str(config), '-d', 'opcache.enable=1',
                   '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0',
                   '-d', 'opcache.preload='+str(project / 'var/generated/php-bootstrap.php')]
        script = project / 'public/compiled.php'
    proxy_environment = dict(runtime.values, GOMAXPROCS=str(proxy_threads))
    with ExitStack() as stack:
        backend = stack.enter_context(bench.process(command, backend_port, workspace, environment=runtime.values,
                                                    label=definition['mode'], quiet=not capture))
        config = workspace / (definition['mode']+'.Caddyfile')
        bench.proxy_config(config, port, backend_port, project, script)
        proxy = stack.enter_context(bench.process([caddy, 'run', '--config', config, '--adapter', 'caddyfile'], port, workspace,
                                                  environment=proxy_environment, label='Caddy '+definition['mode']))
        current = {'backend': backend, 'proxy': proxy, 'port': port, 'definition': definition, 'command': command}
        if not native:
            probe = project / 'public/web-modes-status.php'
            probe.write_text("<?php $s=opcache_get_status(false); echo json_encode(['opcache'=>$s['opcache_enabled']??false,'preload'=>isset($s['preload_statistics']),'kernel'=>class_exists('Phex\\\\Kernel\\\\Application',false),'jit'=>$s['jit']['enabled']??false]);")
            probe_port = bench.available_port()
            probe_config = workspace / 'probe.Caddyfile'
            bench.proxy_config(probe_config, probe_port, backend_port, project, probe)
            with bench.process([caddy, 'run', '--config', probe_config, '--adapter', 'caddyfile'], probe_port, workspace,
                               environment=proxy_environment, label='FPM profile'):
                status, _, payload = bench.request(probe_port, 'GET', '/')
                checked = json.loads(payload)
                bench.check(status == 200 and checked == {'opcache': True, 'preload': True, 'kernel': True, 'jit': False}, 'Unexpected FPM profile: '+repr(payload))
                current['verified_php'] = checked
        if not native:
            verify_response(port)
        owners = process_owners(current)
        deadline = time.monotonic()+3
        while len(owners['workers']) < definition['web_workers'] and time.monotonic() < deadline:
            time.sleep(.01)
            owners = process_owners(current)
        bench.check(len(owners['workers']) == definition['web_workers'], 'Unexpected live web/FPM worker count: '+repr(owners))
        yield current


def preflight(workspace, output, project, caddy, binaries, definition, runtime, proxy_threads, label):
    """Detect post-response crashes before exposing an unhealthy handler to a load test."""
    result = {'responses': [], 'valid': True, 'runtime_abort': False}
    with target(workspace, project, caddy, binaries, definition, runtime, proxy_threads, capture=True) as current:
        result['owners_before'] = process_owners(current)
        for _ in range(2):
            try:
                status, headers, payload = bench.request(current['port'], 'GET', '/')
                result['responses'].append({'status': status, 'body': payload.decode(errors='replace')})
                result['valid'] = result['valid'] and status == 200 and json.loads(payload) == {'hello': 'world'}
            except Exception as error:
                result.update(valid=False, request_error=str(error))
            time.sleep(.15)
            log = current['backend'].benchmark_log
            log.seek(0)
            contents = log.read().decode(errors='replace')
            aborted = any(marker in contents for marker in ['invalid pointer', 'terminated by signal', 'heap memory exhausted', 'Fatal error:', 'panicked at'])
            result['runtime_abort'] = aborted
            if aborted or not result['valid']:
                result['valid'] = False
                break
        result['owners_after'] = process_owners(current)
        path = output/(label.replace('/','-')+'.preflight.log')
        path.write_text(contents)
        result['log'] = str(path)
    return result


def summarize(study):
    valid = [row for row in study['runs'] if row['valid']]
    result = {'valid_runs': len(valid), 'invalid_runs': len(study['runs'])-len(valid),
              'errors': sum(row['errors'] for row in study['runs'])+sum(row['errors'] for row in study.get('warmups',{}).values()),
              'runtime_aborts': int(study.get('preflight',{}).get('runtime_abort',False))}
    if len(valid) != len(study['runs']) or not study['blocks'] or 'failure' in study:
        return dict(result, usable=False)
    for label in ['php_fpm', 'native']:
        rows = [row for row in valid if row['subject'] == label]
        result[label] = {key: statistics.median(row[key] for row in rows) for key in ['requests_per_second','p50_ms','p95_ms','p99_ms']}
        result[label].update(rps_min=min(row['requests_per_second'] for row in rows), rps_max=max(row['requests_per_second'] for row in rows),
                             backend_cpu_us=statistics.median(row['cpu']['backend']['total_us'] for row in rows),
                             backend_system_us=statistics.median(row['cpu']['backend']['system_us'] for row in rows),
                             reaped_child_cpu_us=statistics.median(row['cpu']['backend']['reaped_child_us'] for row in rows),
                             proxy_cpu_us=statistics.median(row['cpu']['proxy']['total_us'] for row in rows),
                             backend_cores=statistics.median(row['cpu']['backend']['cores'] for row in rows),
                             proxy_cores=statistics.median(row['cpu']['proxy']['cores'] for row in rows))
    ratios = [block['ratio'] for block in study['blocks']]
    result.update(usable=True, median_paired_change=100*(statistics.median(ratios)-1),
                  paired_min=100*(min(ratios)-1), paired_max=100*(max(ratios)-1),
                  median_rps_change=100*(result['native']['requests_per_second']/result['php_fpm']['requests_per_second']-1))
    return result


def render(metadata):
    lines = ['# ElephC web modes versus PHP-FPM', '',
             'Native isolation and PHP application lifetime are separate compile-time choices. Worker isolation supports Request and Application; pool and per-request process isolation support Request only. Pool reuses a handler process while rebuilding PHP state; per-request isolation forks a fresh handler. Application retains one container per worker. FPM resets PHP application state on every request.', '',
             'Same installed Phex application, GET / JSON/correlation headers, one active load generator, same Caddy release/frontend, logging enabled to /dev/null. PHP-FPM has verified OPcache/preload and disabled JIT. Recycling is disabled. No single-core affinity limit is imposed; all backends share the same available CPUs. '
             f"Caddy allows {metadata['proxy_threads']} Go execution threads, hey allows two, and all cases use {metadata['concurrency']} clients. "
             f"Each case has {metadata['blocks']} adjacent ABBA blocks with {metadata['seconds']} measured seconds per leg after warm-up. CPU counters include waited-for child CPU, essential for fork-per-request accounting. No slow successful run is discarded.", '',
             'Measured modes: '+', '.join(metadata.get('modes', MODES))+'.', '']
    for policy in metadata['runtime_policies']:
        lines += ['## '+policy+' runtime environment', '', '| Mode | Web workers | Handlers per worker | Parallel PHP executors | Native req/s | Matched FPM req/s | Paired change | Native p95 ms | Errors |',
                  '|---|---:|---:|---:|---:|---:|---:|---:|---:|']
        for study in {row['label']:row for row in metadata['studies']}.values():
            if study['runtime_environment']['policy'] != policy:
                continue
            definition, row = study['definition'], study.get('summary', {})
            if not row.get('usable'):
                lines.append(f"| {definition['mode']} | {definition['web_workers']} | {definition['handlers_per_worker']} | {definition['capacity']} | INVALID | | | | {row.get('errors',0)} HTTP; {row.get('runtime_aborts',0)} abort |")
                continue
            lines.append(f"| {definition['mode']} | {definition['web_workers']} | {definition['handlers_per_worker']} | {definition['capacity']} | {row['native']['requests_per_second']:.1f} | {row['php_fpm']['requests_per_second']:.1f} | {row['median_paired_change']:+.1f}% | {row['native']['p95_ms']:.1f} | {row['errors']} |")
        lines.append('')
    lines += ['The percentage is the median of within-block geometric-mean B/A throughput ratios, not the ratio of independently aggregated throughput medians. Every row has its own adjacent FPM control. Native/PHP process counts differ for brokered isolation; the parallel PHP executor budget is matched. This host is shared, so ranges, CPU use and raw legs are retained. Controlled/inherited policies affect process variables; FPM still uses clear_env=yes with explicit app forwarding. No linear eight-worker scaling or unlimited-uptime claim is made.', '',
              'Application lifecycle with pool or per-request process isolation was checked and rejected by the pinned compiler. Failure diagnostics and any invalid performance cases remain in JSON. The complete data includes exact compile/start commands, binary/SDK hashes, worker/handler identities, FPM status checks and raw hey output.']
    return '\n'.join(lines)+'\n'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--compiler', required=True)
    parser.add_argument('--source-ref', required=True)
    parser.add_argument('--workers', nargs='+', type=int, default=[1,8], help='Maximum simultaneous PHP handlers')
    parser.add_argument('--runtime-environments', nargs='+', choices=['controlled','inherited'], default=['controlled','inherited'])
    parser.add_argument('--modes', nargs='+', choices=list(MODES), default=list(MODES))
    parser.add_argument('--blocks', type=int, default=4)
    parser.add_argument('--seconds', type=int, default=1)
    parser.add_argument('--warmup', type=int, default=2)
    parser.add_argument('--concurrency', type=int, default=32)
    parser.add_argument('--proxy-threads', type=int, default=4)
    parser.add_argument('--pass-env', action='append', default=[])
    parser.add_argument('--output', type=Path, default=bench.ROOT/'var/benchmarks')
    parser.add_argument('--resume', type=Path, help='Resume an existing output directory, preserving completed studies and failed attempts')
    parser.add_argument('--prepare-only', action='store_true', help='Build retained binaries and exit before starting servers; continue later with --resume')
    args = parser.parse_args()
    args.modes = list(dict.fromkeys(args.modes))
    # A bounded preflight records abort diagnostics; load tests must not create core storms.
    resource.setrlimit(resource.RLIMIT_CORE,(0,0))
    bench.check(min([*args.workers,args.blocks,args.seconds,args.warmup,args.concurrency,args.proxy_threads]) > 0, 'Sizes must be positive.')
    compiler = str(Path(args.compiler).resolve())
    source = dict(os.environ)
    for policy in args.runtime_environments:
        bench.runtime_environment(policy,args.pass_env,source=source)
    for tool in ['php-fpm','hey','composer','gh']:
        bench.check(shutil.which(tool) is not None, 'Missing tool: '+tool)
    output = args.output.resolve()/('web-modes-'+datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S'))
    output.mkdir(parents=True)
    metadata = {'state':'preparing','started_utc':datetime.now(timezone.utc).isoformat(),'compiler':compiler,'compiler_source':args.source_ref,
                'compiler_version':subprocess.check_output([compiler,'--version'],text=True).strip(),'compiler_sha256':digest(Path(compiler)),
                'sdk_sha256':{path.name:digest(path) for path in sorted(Path(compiler).parent.glob('libelephc_*.a'))},
                'source_sha256':bench.source_digest(),'git_head':subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip(),
                'php_fpm':subprocess.check_output(['php-fpm','-v'],text=True).splitlines()[0], 'cpu':subprocess.check_output(['lscpu'],text=True),
                'allowed_cpus':sorted(os.sched_getaffinity(0)), 'affinity':'no per-backend single-core restriction',
                'build_environment':{'policy':'inherited build host; separate from runtime','entries':len(source)},
                'proxy_threads':args.proxy_threads,'concurrency':args.concurrency,'blocks':args.blocks,'seconds':args.seconds,'warmup_seconds':args.warmup,
                'runtime_policies':args.runtime_environments,'capacities':args.workers,'modes':args.modes,'studies':[]}
    reuse = None
    if args.resume:
        fresh = metadata
        output.rmdir()
        output = args.resume.resolve()
        metadata = json.loads((output/'results.json').read_text())
        for key in ['compiler_sha256','compiler_source','source_sha256','proxy_threads','concurrency','blocks','seconds','warmup_seconds','runtime_policies','capacities']:
            bench.check(metadata[key] == fresh[key], 'Resume protocol changed: '+key)
        bench.check(metadata.get('modes',list(MODES)) == fresh['modes'], 'Resume protocol changed: modes')
        metadata.setdefault('modes',list(MODES))
        reuse = (metadata['binaries'],metadata['rejected_combinations'])
        for study in metadata['studies']:
            study['summary'] = summarize(study)
        metadata.setdefault('resumptions',[]).append({'utc':datetime.now(timezone.utc).isoformat(),
            'prior_state':metadata['state'],
            'reason':'Continue retained binaries and skip completed cases; bounded native preflight detects post-response aborts and descendants disable core dumps.'})

    def save():
        (output/'results.json').write_text(json.dumps(metadata,indent=2)+'\n')
        (output/'report.md').write_text(render(metadata))

    save()
    with tempfile.TemporaryDirectory(prefix='web-mode-work-',dir=bench.ROOT/'var') as temporary:
        workspace = Path(temporary)
        project,caddy,checksum,binaries,invalid = prepare(workspace,output,compiler,source,reuse=reuse,modes=args.modes)
        metadata.update(binaries=binaries,caddy_archive_sha256=checksum,rejected_combinations=invalid,state='running')
        if args.prepare_only:
            metadata['state'] = 'prepared'
            save()
            print('Prepared binaries: '+str(output/'results.json'),flush=True)
            return
        save()
        print('Results: '+str(output/'results.json'),flush=True)
        definitions = []
        for capacity in args.workers:
            for mode in args.modes:
                isolation,lifecycle = MODES[mode]
                definitions.append({'mode':mode,'isolation':isolation,'lifecycle':lifecycle,'capacity':capacity,'web_workers':capacity,'handlers_per_worker':1})
                if capacity > 1 and isolation in ['pool','request']:
                    definitions.append({'mode':mode,'isolation':isolation,'lifecycle':lifecycle,'capacity':capacity,'web_workers':1,'handlers_per_worker':capacity})
        for policy in args.runtime_environments:
            runtime = bench.runtime_environment(policy,args.pass_env,source=source)
            for definition in definitions:
                label=f"{policy}/{definition['mode']}/w{definition['web_workers']}-h{definition['handlers_per_worker']}"
                if any(row['label']==label and row.get('summary',{}).get('usable') for row in metadata['studies']):
                    print('Preserving completed '+label,flush=True)
                    continue
                print('Starting '+label,flush=True)
                study={'label':label,'definition':definition,'runtime_environment':runtime.describe(),'runs':[],'blocks':[],'warmups':{}}
                metadata['studies'].append(study)
                save()
                fpm={'mode':'php_fpm','isolation':'fpm','lifecycle':'request','capacity':definition['capacity'],'web_workers':definition['capacity'],'handlers_per_worker':1}
                try:
                    study['preflight']=preflight(workspace,output,project,caddy,binaries,definition,runtime,args.proxy_threads,label)
                    save()
                    bench.check(study['preflight']['valid'],'Native preflight failed'+(' with a post-response runtime abort.' if study['preflight']['runtime_abort'] else '.'))
                    with ExitStack() as stack:
                        targets={subject:stack.enter_context(target(workspace,project,caddy,binaries,config,runtime,args.proxy_threads))
                                 for subject,config in [('php_fpm',fpm),('native',definition)]}
                        for subject,current in targets.items():
                            warmup=bench.load(shutil.which('hey'),current['port'],args.warmup,args.concurrency,runtime.values,metadata['allowed_cpus'])
                            study['warmups'][subject]=warmup
                            save()
                            bench.check(warmup['valid'],'Invalid '+subject+' warm-up.')
                        initial={subject:process_owners(current) for subject,current in targets.items()}
                        study['initial_process_owners']=initial
                        study['start_commands']={name:current['command'] for name,current in targets.items()}
                        study['verified_php']=targets['php_fpm']['verified_php']
                        for block in range(args.blocks):
                            values={'php_fpm':[],'native':[]}
                            for subject in ['php_fpm','native','native','php_fpm']:
                                current=targets[subject]
                                before={name:cpu_snapshot(current[name].pid) for name in ['backend','proxy']}
                                row=bench.load(shutil.which('hey'),current['port'],args.seconds,args.concurrency,runtime.values,metadata['allowed_cpus'])
                                after={name:cpu_snapshot(current[name].pid) for name in ['backend','proxy']}
                                owners=process_owners(current)
                                stable=owners['workers']==initial[subject]['workers']
                                stable=stable and owners['brokers']==initial[subject]['brokers']
                                if subject=='native' and definition['isolation']=='pool':
                                    stable=stable and owners['handlers']==initial[subject]['handlers']
                                row.update(subject=subject,block=block+1,process_owners=owners,persistent_processes_stable=stable,
                                           host_load=os.getloadavg(),cpu_before=before,cpu_after=after,cpu={})
                                row['valid']=row['valid'] and stable
                                if row['requests']:
                                    for name in ['backend','proxy']:
                                        delta={clock:after[name][clock]-before[name][clock] for clock in ['user','system','reaped_user','reaped_system']}
                                        row['cpu'][name]={'total_us':1e6*(delta['user']+delta['system'])/row['requests'],
                                                          'system_us':1e6*delta['system']/row['requests'],
                                                          'reaped_child_us':1e6*(delta['reaped_user']+delta['reaped_system'])/row['requests'],
                                                          'cores':(delta['user']+delta['system'])/row['total_seconds']}
                                study['runs'].append(row)
                                save()
                                bench.check(row['valid'],'Invalid HTTP result or unexpected persistent process replacement.')
                                values[subject].append(row['requests_per_second'])
                            ratio=math.sqrt(math.prod(values['native'])/math.prod(values['php_fpm']))
                            study['blocks'].append({'block':block+1,'ratio':ratio,'rps':values})
                            save()
                            print(f"{label} block {block+1}: {100*(ratio-1):+.1f}% versus FPM",flush=True)
                except (RuntimeError,OSError,ValueError) as error:
                    study['failure']=str(error)
                    print('INVALID '+label+': '+str(error),flush=True)
                study['summary']=summarize(study)
                save()
                if not study['summary']['usable'] and study.get('preflight',{}).get('valid'):
                    try:
                        study['post_failure_probe']=preflight(workspace,output,project,caddy,binaries,definition,runtime,args.proxy_threads,label+'-after-failure')
                    except Exception as error:
                        study['diagnostic_failure']=str(error)
                    save()
    metadata['state']='complete'
    metadata['finished_utc']=datetime.now(timezone.utc).isoformat()
    save()
    print(render(metadata),flush=True)
    print('Results: '+str(output/'results.json'),flush=True)


if __name__ == '__main__':
    main()
