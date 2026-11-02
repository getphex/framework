import json
from pathlib import Path
import shutil
import socket
import struct
import subprocess
import sys
import tempfile
import time
import unittest

sys.dont_write_bytecode = True
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from benchmark_environment import runtime_environment, fpm_environment_lines
import native_lifecycle as bench


class BenchmarkEnvironmentTest(unittest.TestCase):
    def test_controlled_environment_excludes_unrelated_process_values(self):
        source = {'PATH': '/private/bin', 'HOST_PRIVATE': 'private-canary', 'PHEX_GREETING': 'build-only', 'GOMAXPROCS': '8'}
        runtime = runtime_environment(source=source)
        self.assertEqual({'PATH': '/usr/bin:/bin', 'GOMAXPROCS': '1'}, runtime.values)
        self.assertEqual('8', source['GOMAXPROCS'])
        self.assertEqual('build-only', source['PHEX_GREETING'])

    def test_inherited_mode_reproduces_original_demo_defaults(self):
        source = {'HOST_PRIVATE': 'private-canary', 'PHEX_GREETING': 'build-only', 'PHP_CLI_SERVER_WORKERS': '4'}
        runtime = runtime_environment('inherited', source=source)
        self.assertEqual('private-canary', runtime.values['HOST_PRIVATE'])
        self.assertNotIn('PHEX_GREETING', runtime.values)
        self.assertNotIn('PHP_CLI_SERVER_WORKERS', runtime.values)
        self.assertEqual('inherited', runtime.describe()['policy'])

    def test_explicit_application_values_include_empty_and_zero(self):
        source = {'APP_EMPTY': '', 'APP_ZERO': '0', 'PHEX_GREETING': 'runtime-value'}
        for policy in ['controlled', 'inherited']:
            runtime = runtime_environment(policy, ['PHEX_GREETING', 'APP_EMPTY', 'APP_ZERO', 'APP_ZERO'], source)
            self.assertEqual('', runtime.values['APP_EMPTY'])
            self.assertEqual('0', runtime.values['APP_ZERO'])
            self.assertEqual('runtime-value', runtime.values['PHEX_GREETING'])
            self.assertEqual(('APP_EMPTY', 'APP_ZERO', 'PHEX_GREETING'), runtime.forwarded_names)

    def test_php_configuration_is_kept_even_with_empty_scan_directory(self):
        runtime = runtime_environment(source={'PHPRC': '/configuration/php.ini', 'PHP_INI_SCAN_DIR': ''})
        self.assertEqual('/configuration/php.ini', runtime.values['PHPRC'])
        self.assertEqual('', runtime.values['PHP_INI_SCAN_DIR'])
        self.assertEqual(['PHPRC', 'PHP_INI_SCAN_DIR'], runtime.describe()['php_configuration_names'])

    def test_unknown_policy_or_missing_forwarded_value_fails_explicitly(self):
        with self.assertRaises(ValueError):
            runtime_environment('unknown', source={})
        with self.assertRaisesRegex(ValueError, 'APP_MISSING'):
            runtime_environment(names=['APP_MISSING'], source={})

    def test_names_cannot_inject_fpm_configuration(self):
        for name in ['', 'APP-NAME', 'NAME]', 'NAME\nenv[OTHER]', '1NAME', 'A B']:
            with self.subTest(name=name), self.assertRaises(ValueError):
                fpm_environment_lines([name])

    def test_fpm_uses_references_instead_of_interpolated_values(self):
        self.assertEqual(['env[APP_EMPTY] = $APP_EMPTY', 'env[APP_VALUE] = $APP_VALUE'],
                         fpm_environment_lines(['APP_VALUE', 'APP_EMPTY', 'APP_VALUE']))

    def test_reports_contain_names_and_byte_counts_without_values(self):
        runtime = runtime_environment(names=['APP_VALUE'], source={'APP_VALUE': 'private-canary-\u00e9'})
        report = runtime.describe()
        self.assertEqual(['APP_VALUE'], report['forwarded_names'])
        self.assertNotIn('private-canary', json.dumps(report))
        self.assertEqual(sum(len((key+'='+value+'\0').encode()) for key, value in runtime.values.items()), report['bytes'])

    def test_php_child_observes_configuration_and_literal_application_values(self):
        php = shutil.which('php')
        self.assertIsNotNone(php)
        root = Path(__file__).resolve().parents[3]
        (root / 'var').mkdir(exist_ok=True)
        with tempfile.TemporaryDirectory(prefix='benchmark-environment-', dir=root / 'var') as directory:
            config = Path(directory) / 'php.ini'
            config.write_text('memory_limit=73M\n')
            source = {'PHPRC': str(config), 'PHP_INI_SCAN_DIR': '', 'APP_EMPTY': '', 'APP_ZERO': '0',
                      'APP_LITERAL': 'quotes\' "$dollar\\slash\nnext', 'UNRELATED': 'hidden'}
            runtime = runtime_environment(names=['APP_EMPTY', 'APP_ZERO', 'APP_LITERAL'], source=source)
            script = "echo json_encode([getenv('APP_EMPTY'), getenv('APP_ZERO'), getenv('APP_LITERAL'), getenv('UNRELATED'), ini_get('memory_limit')]);"
            result = subprocess.run([php, '-r', script], env=runtime.values, capture_output=True, text=True, check=True)
            self.assertEqual(['', '0', source['APP_LITERAL'], False, '73M'], json.loads(result.stdout))

    @unittest.skipUnless(shutil.which('php-fpm'), 'PHP-FPM is required for the worker environment integration check')
    def test_fpm_worker_observes_empty_zero_literal_values_and_php_configuration(self):
        root = Path(__file__).resolve().parents[3]
        (root / 'var').mkdir(exist_ok=True)
        with tempfile.TemporaryDirectory(prefix='benchmark-fpm-environment-', dir=root / 'var') as temporary:
            directory = Path(temporary)
            ini = directory / 'php.ini'
            ini.write_text('memory_limit=73M\n')
            source = {'PHPRC': str(ini), 'PHP_INI_SCAN_DIR': '', 'APP_EMPTY': '', 'APP_ZERO': '0',
                      'APP_LITERAL': 'quotes\' "$dollar\\slash\nnext', 'UNRELATED': 'hidden'}
            runtime = runtime_environment(names=['APP_EMPTY', 'APP_ZERO', 'APP_LITERAL'], source=source)
            script = directory / 'probe.php'
            script.write_text("<?php echo json_encode([getenv('APP_EMPTY'), getenv('APP_ZERO'), getenv('APP_LITERAL'), getenv('UNRELATED'), ini_get('memory_limit')]);")
            port = bench.available_port()
            config = directory / 'fpm.conf'
            bench.fpm_config(config, port, runtime.forwarded_names, workers=2)
            command = [shutil.which('php-fpm'), '-F', '-y', str(config)]
            with bench.process(command, port, directory, environment=runtime.values, label='FPM environment test') as child:
                process_directory = Path('/proc/'+str(child.pid))
                if process_directory.is_dir():
                    self.assertEqual(directory / 'runtime-cwd', (process_directory / 'cwd').resolve())
                    children_file = process_directory / 'task' / str(child.pid) / 'children'
                    deadline = time.monotonic()+3
                    children = children_file.read_text().split()
                    while len(children) < 2 and time.monotonic() < deadline:
                        time.sleep(.01)
                        children = children_file.read_text().split()
                    self.assertEqual(2, len(children))
                with socket.create_connection(('127.0.0.1', port), timeout=5) as connection:
                    def record(kind, data):
                        return struct.pack('!BBHHBB', 1, kind, 1, len(data), 0, 0)+data

                    def receive(size):
                        data = b''
                        while len(data) < size:
                            chunk = connection.recv(size-len(data))
                            self.assertTrue(chunk, 'FPM closed an incomplete FastCGI response')
                            data += chunk
                        return data

                    parameters = {'SCRIPT_FILENAME': str(script), 'REQUEST_METHOD': 'GET', 'SERVER_PROTOCOL': 'HTTP/1.1'}
                    data = b''
                    for key, value in parameters.items():
                        key, value = key.encode(), value.encode()
                        lengths = b''.join(bytes([size]) if size < 128 else struct.pack('!I', size | 0x80000000)
                                           for size in [len(key), len(value)])
                        data += lengths+key+value
                    connection.sendall(record(1, b'\0\1\0\0\0\0\0\0')+record(4, data)+record(4, b'')+record(5, b''))
                    output = b''
                    while True:
                        _, kind, _, size, padding, _ = struct.unpack('!BBHHBB', receive(8))
                        payload = receive(size)
                        receive(padding)
                        if kind == 6:
                            output += payload
                        if kind == 3:
                            break
            self.assertEqual(['', '0', source['APP_LITERAL'], False, '73M'], json.loads(output.split(b'\r\n\r\n', 1)[1]))


if __name__ == '__main__':
    unittest.main()
