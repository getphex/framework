import os
from pathlib import Path
import subprocess
import sys
import unittest

sys.dont_write_bytecode = True
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from web_mode_comparison import cpu_snapshot


class ForkCpuAccountingTest(unittest.TestCase):
    @unittest.skipUnless(hasattr(os, 'fork') and Path('/proc').is_dir(), 'Linux process accounting is required')
    def test_waited_child_cpu_remains_counted_after_the_child_is_reaped(self):
        program = '''import os,sys,time
child=os.fork()
if child==0:
    until=time.process_time()+0.12
    while time.process_time()<until:
        sum(range(1000))
    os._exit(0)
os.waitpid(child,0)
print('reaped',flush=True)
sys.stdin.readline()
'''
        process = subprocess.Popen([sys.executable, '-B', '-c', program], stdin=subprocess.PIPE,
                                   stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True, start_new_session=True)
        try:
            self.assertEqual('reaped', process.stdout.readline().strip())
            snapshot = cpu_snapshot(process.pid)
            self.assertEqual([process.pid], [row['pid'] for row in snapshot['processes']])
            self.assertGreaterEqual(snapshot['reaped_user']+snapshot['reaped_system'], 0.05)
            self.assertGreaterEqual(snapshot['user']+snapshot['system'], 0.05)
        finally:
            process.communicate('\n', timeout=5)
        self.assertEqual(0, process.returncode)


if __name__ == '__main__':
    unittest.main()
