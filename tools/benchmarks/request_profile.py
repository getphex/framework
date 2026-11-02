#!/usr/bin/env python3
"""Reproduce request CPU profiles in a disposable workspace and keep evidence."""

import argparse
import base64
from contextlib import ExitStack
from datetime import datetime, timezone
import hashlib
import hmac
import json
import os
from pathlib import Path
import re
import shutil
import socket
import signal
import subprocess
import sys
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor

sys.dont_write_bytecode = True
import native_lifecycle as bench

ROOT = bench.ROOT
FLAMEGRAPH_REVISION = '41fee1f99f9276008b7cd112fca19dc3ea84ac32'


def fetch_exact(address, key_file):
    """Read the pinned compiler's authenticated probe protocol without table truncation."""
    key = bytes.fromhex(key_file.read_text().strip())
    nonce_c = os.urandom(32)
    tag = lambda key_, value: hmac.new(key_, value, hashlib.sha256).digest()
    with socket.create_connection(address, timeout=40) as connection:
        def receive(size):
            data = bytearray()
            while len(data) < size:
                block = connection.recv(size - len(data))
                bench.check(bool(block), 'Truncated probe response.')
                data.extend(block)
            return bytes(data)
        connection.sendall(nonce_c)
        nonce_s = receive(32)
        bench.check(hmac.compare_digest(receive(32), tag(key, b'S'+nonce_c+nonce_s)), 'Probe build authentication failed.')
        connection.sendall(b'E'+tag(key, b'C'+nonce_s+nonce_c+b'E'))
        session = tag(key, b'elephc-probe-session-v1'+nonce_c+nonce_s+b'E')
        length = receive(4)
        size = int.from_bytes(length, 'big')
        bench.check(size <= 16 * 1024 * 1024, 'Oversized probe response.')
        ciphertext = receive(size)
        bench.check(hmac.compare_digest(receive(32), tag(tag(session, b'mac'), length+ciphertext)), 'Probe payload authentication failed.')
        plaintext = bytearray(ciphertext)
        for index in range(0, size, 32):
            mask = tag(tag(session, b'enc'), (index // 32).to_bytes(8, 'big'))
            for offset in range(min(32, size - index)):
                plaintext[index + offset] ^= mask[offset]
        return plaintext.decode()


def selected_runtime(state, clean_environment=False, policy='controlled', forwarded_names=()):
    return bench.runtime_environment('controlled' if clean_environment else policy, forwarded_names, source=state['environment'])


def exact(workspace, state, *, clean_environment=False, runtime_policy='controlled', forwarded_names=()):
    output = Path(state['output'])
    backend_port = bench.available_port()
    probe_port = bench.available_port()
    proxy_port = bench.available_port()
    runtime = selected_runtime(state, clean_environment, runtime_policy, forwarded_names)
    environment = dict(runtime.values, ELEPHC_PROBE_ADDR='127.0.0.1:'+str(probe_port))
    config = workspace / 'Caddyfile'
    bench.proxy_config(config, proxy_port, backend_port, Path(state['project']))
    with ExitStack() as stack:
        stack.enter_context(bench.process(bench.pinned([workspace / 'native-monitored', '--listen', '127.0.0.1:'+str(backend_port), '--workers', '1', '--max-requests', '0'],
                                                       state['affinity']['backend']), backend_port, workspace, environment=environment, label='monitored native', quiet=True))
        stack.enter_context(bench.process(bench.pinned([workspace / 'caddy', 'run', '--config', config, '--adapter', 'caddyfile'], state['affinity']['proxy']),
                                          proxy_port, workspace, environment=runtime.values, label='Caddy'))
        for _ in range(10):
            bench.request(proxy_port, 'GET', '/')
        for capture in range(3):
            with ThreadPoolExecutor(max_workers=1) as executor:
                future = executor.submit(fetch_exact, ('127.0.0.1', probe_port), workspace / 'native-monitored.key')
                time.sleep(0.1)
                checked([shutil.which('hey'), '-n', '1', '-c', '1', '-disable-compression', 'http://127.0.0.1:'+str(proxy_port)+'/'], env=runtime.values)
                raw = future.result(timeout=40)
            target = output / ('native-exact-'+('clean-' if runtime.policy == 'controlled' else '')+str(capture)+'.raw')
            target.write_text(raw)
            target.with_suffix('.environment.json').write_text(json.dumps({'runtime_environment': runtime.describe(),
                                                                         'instrumentation_environment_names': ['ELEPHC_PROBE_ADDR']}, indent=2)+'\n')
            print('EXACT '+str(target)+'\n'+'\n'.join(raw.splitlines()[:4]), flush=True)


def variants(workspace, state, selected=None):
    """Diagnostic copies only. Never modify the framework checkout or compiler cache."""
    import difflib
    import struct
    output = Path(state['output'])
    project = Path(state['project'])
    base = output / 'binaries/native-full'
    patched = output / 'binaries/native-no-string-registry'
    shutil.copy2(base, patched)
    image = bytearray(patched.read_bytes())
    section_offset = struct.unpack_from('<Q', image, 40)[0]
    section_size, section_count = struct.unpack_from('<HH', image, 58)
    sections = [struct.unpack_from('<IIQQQQIIQQ', image, section_offset+i*section_size) for i in range(section_count)]
    symbols = checked(['nm', '-S', '--defined-only', base])
    changes = []
    for line in symbols.splitlines():
        fields = line.split()
        if len(fields) != 4 or not re.fullmatch(r'elephc_mbstring_native_string_(bind|lookup|copy|fresh|literal|persist|resolve|forget|reset)_v1', fields[3]):
            continue
        address, size = int(fields[0], 16), int(fields[1], 16)
        bench.check(size >= 3, 'Registry callback is too short for the diagnostic replacement.')
        section = next(item for item in sections if item[3] <= address < item[3] + item[5] and item[2] & 4)
        offset = section[4] + address - section[3]
        original = image[offset:offset+3].hex()
        image[offset:offset+3] = bytes.fromhex('31c0c3')
        changes.append({'symbol': fields[3], 'offset': offset, 'original': original, 'replacement': '31c0c3'})
    required = {'elephc_mbstring_native_string_'+name+'_v1' for name in ['fresh', 'literal', 'persist', 'forget', 'reset']}
    bench.check(required <= {item['symbol'] for item in changes}, 'Did not find the active native string registry callbacks.')
    patched.write_bytes(image)
    (output / 'registry-ablation.json').write_text(json.dumps({'changes': changes,
        'baseline_sha256': hashlib.sha256(base.read_bytes()).hexdigest(), 'patched_sha256': hashlib.sha256(image).hexdigest(),
        'limitation': 'Diagnostic only: returning zero skips native INI string identity semantics. Not a production fix.'}, indent=2)+'\n')
    state['cases']['native-no-string-registry'] = {'binary': str(patched)}
    paths = [project / 'vendor/getphex/phex/src/Http/Message/Headers.php',
             project / 'vendor/getphex/phex/src/Runtime/Http/BufferedResponseEmitter.php',
             project / 'vendor/getphex/phex/src/Logging/JsonLogger.php',
             project / 'var/generated/ApplicationContainer.php']
    original = {path: path.read_text() for path in paths}
    headers, emitter, logger, container = paths
    no_op_headers = original[headers].replace("        $fields = $this->fields;\n        unset($fields[strtolower($name)]);",
        "        $key = strtolower($name);\n        if (!isset($this->fields[$key])) {\n            return $this;\n        }\n        $fields = $this->fields;\n        unset($fields[$key]);")
    bulk = original[emitter].replace("        $headers = $response->headers->without('Transfer-Encoding')->without('Trailer');",
        "        $fields = $response->headers->all();\n        unset($fields['transfer-encoding'], $fields['trailer']);")
    bulk = bulk.replace("$headers = $headers->without('Content-Length');", "unset($fields['content-length']);")
    bulk = bulk.replace("$headers = $headers->with('Content-Length', ['0']);", "$fields['content-length'] = ['0'];")
    bulk = bulk.replace("$headers = $headers->with('Content-Length', [(string) strlen($response->body)]);", "$fields['content-length'] = [(string) strlen($response->body)];")
    bulk = bulk.replace("        $this->output->start($response->status, $headers);", "        $this->output->start($response->status, new \\Phex\\Http\\Message\\Headers($fields));")
    no_log = original[logger][:original[logger].index('    public function log(Record $record): void')] + '    public function log(Record $record): void {}\n}\n'
    no_eager = re.sub(r'^            \$this->service\d+\(\);\n', '', original[container], flags=re.M)
    guarded = bulk.replace("        unset($fields['transfer-encoding'], $fields['trailer']);",
        "        if (isset($fields['transfer-encoding'])) { unset($fields['transfer-encoding']); }\n        if (isset($fields['trailer'])) { unset($fields['trailer']); }")
    variants_ = {'native-no-log': {logger: no_log}, 'native-no-eager': {container: no_eager},
                 'native-noop-headers': {headers: no_op_headers}, 'native-guarded-framing': {headers: no_op_headers, emitter: guarded},
                 'native-bulk-headers': {headers: no_op_headers, emitter: bulk}}
    try:
        for name, contents in variants_.items():
            if selected and name != selected:
                continue
            for path, baseline in original.items():
                path.write_text(baseline)
            patch = []
            for path, content in contents.items():
                path.write_text(content)
                patch.extend(difflib.unified_diff(original[path].splitlines(True), content.splitlines(True),
                                                fromfile=str(path.relative_to(project)), tofile=str(path.relative_to(project))))
            (output / (name+'.diff')).write_text(''.join(patch))
            print('Compiling diagnostic '+name, flush=True)
            checked([state['compiler'], '--web', '--strict-php', '--keep-symbols', '--debug-info', project / 'var/build/server.php'], cwd=project)
            target = output / 'binaries' / name
            shutil.copy2(project / 'var/build/server', target)
            state['cases'][name] = {'binary': str(target)}
    finally:
        for path, content in original.items():
            path.write_text(content)
        (workspace / 'state.json').write_text(json.dumps(state, indent=2)+'\n')
        (output / 'metadata.json').write_text(json.dumps({k: v for k, v in state.items() if k != 'environment'}, indent=2)+'\n')


def php_spy(workspace, state, *, runtime_policy='controlled', forwarded_names=()):
    output = Path(state['output'])
    project = Path(state['project'])
    port, proxy_port = bench.available_port(), bench.available_port()
    config, marker = workspace / 'fpm-spy.conf', workspace / 'phpspy-start.flag'
    marker.unlink(missing_ok=True)
    runtime = selected_runtime(state, policy=runtime_policy, forwarded_names=forwarded_names)
    environment = runtime.values
    bench.fpm_config(config, port, runtime.forwarded_names)
    with config.open('a') as file:
        file.write('process.dumpable = yes\n')
    helper = workspace / 'phpspy-fpm-launch.py'
    helper.write_text('''import json,os,subprocess,sys,time
from pathlib import Path
config=json.loads(sys.argv[1])
child=subprocess.Popen(config['fpm'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
while not Path(config['marker']).exists():
    if child.poll() is not None: raise RuntimeError('FPM exited')
    time.sleep(0.01)
pids=Path('/proc/'+str(child.pid)+'/task/'+str(child.pid)+'/children').read_text().split()
assert len(pids)==1,pids
os.sched_setaffinity(0,{config['cpu']})
os.execv(config['spy'],[config['spy'],'-p',pids[0],'-V','85','-H','499','-i','10000','-S','-c','-o',config['traces']])
''')
    stamp = 'php-language-'+datetime.now(timezone.utc).strftime('%H%M%S')
    fpm = [shutil.which('php-fpm'), '-F', '-y', str(config), '-d', 'opcache.enable=1', '-d', 'opcache.validate_timestamps=0',
           '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0', '-d', 'opcache.preload='+str(project / 'var/generated/php-bootstrap.php')]
    command = [sys.executable, helper, json.dumps({'fpm': fpm, 'marker': str(marker), 'cpu': 8, 'spy': str(workspace / 'phpspy/phpspy'),
                                                 'traces': str(output / (stamp+'.traces'))})]
    proxy_config = workspace / 'Caddyfile'
    bench.proxy_config(proxy_config, proxy_port, port, project, project / 'public/compiled.php')
    with ExitStack() as stack:
        stack.enter_context(bench.process(bench.pinned(command, state['affinity']['backend']), port, workspace,
                                          environment=environment, label='phpspy owner'))
        stack.enter_context(bench.process(bench.pinned([workspace / 'caddy', 'run', '--config', proxy_config, '--adapter', 'caddyfile'], state['affinity']['proxy']),
                                          proxy_port, workspace, environment=environment, label='Caddy'))
        bench.load(shutil.which('hey'), proxy_port, 2, 16, environment, state['affinity']['client'])
        marker.write_text('start\n')
        time.sleep(0.1)
        result = bench.load(shutil.which('hey'), proxy_port, 10, 16, environment, state['affinity']['client'])
    traces = (output / (stamp+'.traces')).read_text()
    bench.check(bool(traces.strip()), 'phpspy produced no PHP stacks.')
    folded = subprocess.run(['perl', str(workspace / 'phpspy/stackcollapse-phpspy.pl')], input=traces, text=True, capture_output=True, check=True).stdout
    (output / (stamp+'.folded')).write_text(folded)
    svg = subprocess.run(['perl', str(workspace / 'tools/flamegraph.pl'), '--title', 'PHP-FPM language stacks (wall samples)', '--countname', 'samples'],
                         input=folded, text=True, capture_output=True, check=True).stdout
    (output / (stamp+'.svg')).write_text(svg)
    (output / (stamp+'.json')).write_text(json.dumps({'load': result, 'runtime_environment': runtime.describe(),
                                                    'phpspy_revision': (workspace / 'phpspy-revision').read_text().strip()}, indent=2)+'\n')
    print('PHP LANGUAGE PROFILE '+str(output / stamp), flush=True)


def checked(command, *, env=None, cwd=ROOT):
    result = subprocess.run(list(map(str, command)), cwd=cwd, env=env, capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError(f'Command failed: {command}\n{result.stdout}\n{result.stderr}')
    return result.stdout


def tool_environment(workspace):
    return dict(os.environ, LD_LIBRARY_PATH=str(workspace / 'tools/usr/lib'),
                PERF_BUILDID_DIR=str(workspace / 'perf-buildid'), GOMAXPROCS='1')


def prepare(workspace, compiler, *, runtime_policy='controlled', forwarded_names=()):
    output = ROOT / 'var/profiles' / ('request-'+datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S'))
    output.mkdir(parents=True)
    (output / 'binaries').mkdir()
    tools = workspace / 'tools'
    tools.mkdir()
    urls = checked(['pacman', '-Sp', '--nodeps', '--print-format', '%l', 'perf', 'strace']).splitlines()
    packages = []
    for url in urls:
        archive = tools / url.rsplit('/', 1)[-1]
        urllib.request.urlretrieve(url, archive)
        packages.append({'url': url, 'sha256': hashlib.sha256(archive.read_bytes()).hexdigest()})
        checked(['tar', '-xf', archive, '-C', tools])
    for script in ['stackcollapse-perf.pl', 'flamegraph.pl']:
        encoded = checked(['gh', 'api', f'repos/brendangregg/FlameGraph/contents/{script}?ref={FLAMEGRAPH_REVISION}', '--jq', '.content'])
        (tools / script).write_bytes(base64.b64decode(encoded))
    caddy, checksum = bench.download_caddy(workspace)
    project = bench.install_project(workspace)
    environment = dict(os.environ)
    runtime = bench.runtime_environment(runtime_policy, forwarded_names, source=environment)
    bench.run(['composer', 'build:native'], project, env=dict(environment, PHEX_ELEPHC=compiler))
    shutil.copy2(project / 'var/build/server', output / 'binaries/native-production')
    checked([compiler, '--web', '--strict-php', '--keep-symbols', '--debug-info', project / 'var/build/server.php'], cwd=project)
    shutil.copy2(project / 'var/build/server', output / 'binaries/native-full')
    bare = project / 'public/benchmark-bare.php'
    bare.write_text('''<?php
header('Content-Type: application/json');
echo '{"hello":"world"}';
''')
    checked([compiler, '--web', '--strict-php', '--keep-symbols', '--debug-info', bare], cwd=project)
    shutil.copy2(bare.with_suffix(''), output / 'binaries/native-bare')
    metadata = {'output': str(output), 'project': str(project), 'compiler': compiler, 'affinity': bench.cpu_assignment(),
                'compiler_version': checked([compiler, '--version']).strip(), 'php': checked(['php', '-v']).splitlines()[0],
                'perf': checked([tools / 'usr/bin/perf', '--version'], env=tool_environment(workspace)).strip(),
                'packages': packages, 'caddy_sha256': checksum, 'flamegraph_revision': FLAMEGRAPH_REVISION,
                'source_sha256': bench.source_digest(), 'git_head': checked(['git', 'rev-parse', 'HEAD']).strip(),
                'kernel': os.uname().release, 'cpu': checked(['lscpu']), 'environment': environment,
                'default_runtime_environment': runtime.describe(),
                'build_environment': {'policy': 'inherited build host; separate from runtime', 'entries': len(environment)},
                'cases': {'native-full': {'binary': str(output / 'binaries/native-full')},
                          'native-production': {'binary': str(output / 'binaries/native-production')},
                          'native-bare': {'binary': str(output / 'binaries/native-bare')},
                          'php-full': {'script': str(project / 'public/compiled.php')},
                          'php-bare': {'script': str(bare)}}}
    # Keep inherited environment only in the disposable workspace, never in reports.
    (workspace / 'state.json').write_text(json.dumps(metadata, indent=2)+'\n')
    public = {key: value for key, value in metadata.items() if key != 'environment'}
    (output / 'metadata.json').write_text(json.dumps(public, indent=2)+'\n')
    print(json.dumps({'workspace': str(workspace), 'output': str(output)}, indent=2), flush=True)


def cpu_snapshot(group):
    ticks = [0, 0]
    pids = []
    for path in Path('/proc').glob('[0-9]*/stat'):
        try:
            raw = path.read_text()
        except OSError:
            continue
        fields = raw[raw.rfind(')') + 2:].split()
        if int(fields[2]) == group:
            ticks[0] += int(fields[11])
            ticks[1] += int(fields[12])
            pids.append(int(path.parent.name))
    frequency = os.sysconf('SC_CLK_TCK')
    return {'user': ticks[0] / frequency, 'system': ticks[1] / frequency, 'pids': sorted(pids)}


def execute(workspace, state, case, *, seconds, concurrency, direct=False, profile=False, syscalls=False, clean_environment=False, match_proxy_headers=False,
            runtime_policy='controlled', forwarded_names=(), warmup_seconds=2):
    output = Path(state['output'])
    project = Path(state['project'])
    definition = state['cases'][case]
    runtime = selected_runtime(state, clean_environment, runtime_policy, forwarded_names)
    environment = runtime.values
    affinity = state['affinity']
    stamp = f'{case}-'+('direct-' if direct else '')+('matched-' if match_proxy_headers else '')+('clean-env-' if runtime.policy == 'controlled' else '')+datetime.now(timezone.utc).strftime('%H%M%S-%f')
    backend_port = bench.available_port()
    if 'binary' in definition:
        command = [definition['binary'], '--listen', '127.0.0.1:'+str(backend_port), '--workers', '1', '--max-requests', '0']
    else:
        config = workspace / 'fpm.conf'
        bench.fpm_config(config, backend_port, runtime.forwarded_names)
        command = [shutil.which('php-fpm'), '-F', '-y', str(config), '-d', 'opcache.enable=1',
                   '-d', 'opcache.validate_timestamps=0', '-d', 'opcache.jit=disable', '-d', 'opcache.jit_buffer_size=0',
                   '-d', 'opcache.preload='+str(project / 'var/generated/php-bootstrap.php')]
    if syscalls:
        command = [str(workspace / 'tools/usr/bin/strace'), '-f', '-c', '-o', str(output / (stamp+'.strace.txt')), *command]
        environment = dict(environment, LD_LIBRARY_PATH=str(workspace / 'tools/usr/lib'))
    with ExitStack() as stack:
        backend = stack.enter_context(bench.process(bench.pinned(command, affinity['backend']), backend_port, workspace,
                                                    environment=environment, label=case, quiet=True))
        verified_php = None
        if 'script' in definition:
            probe = project / 'public/profile-status.php'
            probe.write_text("<?php header('Content-Type: application/json'); $s=opcache_get_status(false); echo json_encode(['opcache'=>$s['opcache_enabled']??false,'preload'=>isset($s['preload_statistics']),'jit'=>$s['jit']['enabled']??false,'env_count'=>count($_ENV)]);")
            probe_port = bench.available_port()
            probe_config = workspace / 'Caddyfile-status'
            bench.proxy_config(probe_config, probe_port, backend_port, project, probe)
            with bench.process(bench.pinned([workspace / 'caddy', 'run', '--config', probe_config, '--adapter', 'caddyfile'], affinity['proxy']),
                               probe_port, workspace, environment=environment, label='FPM status'):
                status, _, payload = bench.request(probe_port, 'GET', '/')
                verified_php = json.loads(payload)
                bench.check(status == 200 and verified_php['opcache'] and verified_php['preload'] and not verified_php['jit'], 'Unexpected PHP profile.')
        proxy = None
        port = backend_port
        if not direct:
            port = bench.available_port()
            config = workspace / 'Caddyfile'
            bench.proxy_config(config, port, backend_port, project, Path(definition['script']) if 'script' in definition else None)
            proxy = stack.enter_context(bench.process(bench.pinned([workspace / 'caddy', 'run', '--config', config, '--adapter', 'caddyfile'], affinity['proxy']),
                                                      port, workspace, environment=environment, label='Caddy'))
        status, headers, payload = bench.request(port, 'GET', '/')
        bench.check(status == 200 and json.loads(payload) == {'hello': 'world'}, 'Invalid profile endpoint: '+repr(payload))
        headers = ['X-Forwarded-For: 127.0.0.1', 'X-Forwarded-Proto: http', 'X-Forwarded-Host: 127.0.0.1:'+str(port),
                   'Via: 1.1 Caddy', 'Accept-Encoding: gzip'] if match_proxy_headers else []
        warmup = bench.load(shutil.which('hey'), port, warmup_seconds, concurrency, environment, affinity['client'], headers)
        bench.check(warmup['valid'], 'Warm-up failed.')
        subjects = [('backend', backend)] + ([('proxy', proxy)] if proxy else [])
        profilers = []
        if profile:
            for label, child in subjects:
                ids = cpu_snapshot(child.pid)['pids']
                log = stack.enter_context((output / (stamp+'-'+label+'.perf.log')).open('w'))
                command = [workspace / 'tools/usr/bin/perf', 'record', '--no-buildid-cache', '-e', 'cycles:u', '-F', '999', '--call-graph', 'dwarf,16384',
                           '-o', output / (stamp+'-'+label+'.perf.data'), '-p', ','.join(map(str, ids)), '--', 'sleep', str(seconds)]
                perf = subprocess.Popen(list(map(str, command)), env=tool_environment(workspace), stdout=log, stderr=log)
                profilers.append((label, perf))
            time.sleep(0.1)
        host_before = {'load': os.getloadavg(), 'frequency_khz': Path('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq').read_text().strip()}
        before = {label: cpu_snapshot(child.pid) for label, child in subjects}
        result = bench.load(shutil.which('hey'), port, seconds, concurrency, environment, affinity['client'], headers)
        after = {label: cpu_snapshot(child.pid) for label, child in subjects}
        result.update(case=case, direct=direct, profiled=profile, syscalls=syscalls, clean_environment=runtime.policy == 'controlled', concurrency=concurrency,
                      runtime_environment=runtime.describe(),
                      environment_entries=runtime.describe()['entries'], environment_bytes=runtime.describe()['bytes'],
                      warmup=warmup, cpu_before=before, cpu_after=after,
                      cpu_microseconds_per_request={label: {clock: 1e6*(after[label][clock]-before[label][clock])/result['requests']
                                                            for clock in ['user', 'system']} for label, child in subjects},
                      completed_requests_including_probe_and_warmup=1+int('script' in definition)+warmup['requests']+result['requests'])
        result['verified_php'] = verified_php
        result['match_proxy_headers'] = match_proxy_headers
        result['host_before'] = host_before
        result['host_after'] = {'load': os.getloadavg(), 'frequency_khz': Path('/sys/devices/system/cpu/cpu0/cpufreq/scaling_cur_freq').read_text().strip()}
        for label, child in subjects:
            bench.check(before[label]['pids'] == after[label]['pids'], 'Unexpected process replacement during profile.')
        for label, perf in profilers:
            perf.wait(timeout=10)
            bench.check(perf.returncode == 0, 'perf record failed: '+str(output / (stamp+'-'+label+'.perf.log')))
        if syscalls:
            children = Path(f'/proc/{backend.pid}/task/{backend.pid}/children').read_text().split()
            for pid in children:
                os.kill(int(pid), signal.SIGQUIT if 'script' in definition else signal.SIGTERM)
            backend.wait(timeout=10)
    (output / (stamp+'.json')).write_text(json.dumps(result, indent=2)+'\n')
    print(json.dumps({key: value for key, value in result.items() if key not in ['raw_hey', 'warmup', 'cpu_before', 'cpu_after']}, indent=2), flush=True)
    if profile:
        for label, _ in profilers:
            base = output / (stamp+'-'+label)
            env = tool_environment(workspace)
            report = checked([workspace / 'tools/usr/bin/perf', 'report', '-i', str(base)+'.perf.data', '--stdio', '--no-children', '--call-graph', 'none', '--percent-limit', '0.1'], env=env)
            Path(str(base)+'.report.txt').write_text(report)
            script = checked([workspace / 'tools/usr/bin/perf', 'script', '-i', str(base)+'.perf.data'], env=env)
            folded = subprocess.run(['perl', str(workspace / 'tools/stackcollapse-perf.pl')], input=script, text=True, capture_output=True, check=True).stdout
            Path(str(base)+'.folded').write_text(folded)
            svg = subprocess.run(['perl', str(workspace / 'tools/flamegraph.pl'), '--title', case+' '+label+' CPU', '--countname', 'cycles'],
                                 input=folded, text=True, capture_output=True, check=True).stdout
            Path(str(base)+'.svg').write_text(svg)
            print('PROFILE '+str(base), flush=True)
    return dict(result, artifact=str(output / (stamp+'.json')))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('action', choices=['prepare', 'run', 'exact', 'variants', 'php-spy'])
    parser.add_argument('--workspace', required=True, type=Path)
    parser.add_argument('--compiler', default=os.environ.get('PHEX_ELEPHC', 'elephc'))
    parser.add_argument('--case', default='native-full')
    parser.add_argument('--variant')
    parser.add_argument('--seconds', type=int, default=5)
    parser.add_argument('--concurrency', type=int, default=16)
    parser.add_argument('--direct', action='store_true')
    parser.add_argument('--match-proxy-headers', action='store_true')
    parser.add_argument('--profile', action='store_true')
    parser.add_argument('--syscalls', action='store_true')
    parser.add_argument('--clean-environment', action='store_true')
    parser.add_argument('--runtime-environment', choices=['controlled', 'inherited'], default='controlled')
    parser.add_argument('--pass-env', action='append', default=[], metavar='NAME')
    args = parser.parse_args()
    workspace = args.workspace.resolve()
    bench.check(workspace.parent == ROOT / 'var' and workspace.name.startswith('request-profile-work-'), 'Use a dedicated workspace under var.')
    if args.action == 'prepare':
        prepare(workspace, args.compiler, runtime_policy=args.runtime_environment, forwarded_names=args.pass_env)
    else:
        state = json.loads((workspace / 'state.json').read_text())
        if args.action == 'php-spy':
            php_spy(workspace, state, runtime_policy=args.runtime_environment, forwarded_names=args.pass_env)
            return
        if args.action == 'variants':
            variants(workspace, state, selected=args.variant)
            return
        if args.action == 'exact':
            exact(workspace, state, clean_environment=args.clean_environment,
                  runtime_policy=args.runtime_environment, forwarded_names=args.pass_env)
            return
        execute(workspace, state, args.case, seconds=args.seconds, concurrency=args.concurrency,
                direct=args.direct, profile=args.profile, syscalls=args.syscalls, clean_environment=args.clean_environment,
                match_proxy_headers=args.match_proxy_headers, runtime_policy=args.runtime_environment, forwarded_names=args.pass_env)


if __name__ == '__main__':
    main()
