#!/usr/bin/env python3
"""Characterize process reuse and PHP state reset in the pinned native web runtime."""

from pathlib import Path
import subprocess
import sys
import tempfile
import time

sys.dont_write_bytecode = True
from http_smoke import ROOT, check, fields, php_command, request, server


def verify(port, *, recycle_after=None):
    processes = []
    for index, identity in enumerate(("alice", "bob", "carol", "dave")):
        deadline = time.monotonic() + 5
        while True:
            try:
                status, headers, body = request(port, "GET", "/lifecycle", headers={"X-Request-Id": identity})
                break
            except ConnectionRefusedError:
                # A single-worker quota recycle briefly has no listening worker.
                if index != recycle_after or time.monotonic() >= deadline:
                    raise
                time.sleep(0.05)
        check(status == 200, f"Lifecycle request returned {status}: {body!r}")
        check(fields(headers, "Content-Type") == ["text/plain; charset=utf-8"], "Unexpected lifecycle content type.")
        result = body.decode().split("|")
        pid = int(result[0])
        check(pid > 0, "The runtime did not report a process ID.")
        # Global, function static twice, constructor count, cached object twice,
        # current request ID, and the preload-assigned global.
        check(result[1:] == ["1", "1", "2", "1", "1", "2", identity, "unset"],
              f"Unexpected application lifetime for request {identity}: {result}")
        processes.append(pid)
    return processes


def main():
    source = ROOT / "tests/Runtime/Fixtures/lifecycle.php"
    preload = ROOT / "tests/Runtime/Fixtures/lifecycle-preload.php"
    with server(php_command(source)) as port:
        processes = verify(port)
    check(len(set(processes)) == 1, "The PHP baseline did not use one process.")
    print("PHP baseline: one process reused; globals, statics and application objects reset per request.", flush=True)
    with tempfile.TemporaryDirectory(prefix="native-lifecycle-", dir=ROOT / "var") as directory:
        artifacts = Path(directory)
        for mode, preloaded in (("worker", False), ("pool", False), ("request", False), ("worker", True)):
            label = mode + ("-preload" if preloaded else "")
            entry = artifacts / (label + ".php")
            escaped = str(source).replace("\\", "\\\\").replace("'", "\\'")
            entry.write_text("<?php\ndeclare(strict_types=1);\nrequire '" + escaped + "';\n")
            command = ["elephc", "--web", "--strict-php", "--web-isolation=" + mode]
            if preloaded:
                command += ["--ini", "opcache.preload=" + str(preload)]
            command.append(str(entry))
            compiled = subprocess.run(command, cwd=ROOT, text=True, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, timeout=60)
            check(compiled.returncode == 0, "Native lifecycle compilation failed:\n" + compiled.stdout)
            runtime = [str(entry.with_suffix("")), "--listen", "127.0.0.1:{port}", "--workers", "1"]
            if mode in ("pool", "request"):
                runtime += ["--handler-concurrency", "1"]
            if mode == "pool":
                runtime += ["--max-handler-requests", "0"]
            events = artifacts / "preload-events"
            if preloaded:
                # The preload runs before the prefork workers; recycle to check it does not rerun.
                runtime = ["env", "PHEX_LIFECYCLE_EVENTS=" + str(events), *runtime, "--max-requests", "2"]
            with server(runtime, error_log_marker="phex:preload-startup" if preloaded else None) as port:
                processes = verify(port, recycle_after=2 if preloaded else None)
                if preloaded:
                    startup = events.read_text().splitlines()
                    check(len(startup) == 1, "Preload ran again after worker recycling.")
                    check(int(startup[0]) not in processes, "Preload ran inside a request handler process.")
                    check(processes[0] == processes[1] and processes[2] == processes[3] and processes[0] != processes[2], "Worker recycling did not replace the process at the request quota.")
                elif mode == "request":
                    check(len(set(processes)) == len(processes), "Request isolation reused a handler process.")
                else:
                    check(len(set(processes)) == 1, mode + f" did not reuse its handler process: {processes}.")
            print(f"Native {label}: PHP application state reset for every request; PIDs {processes}.", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
