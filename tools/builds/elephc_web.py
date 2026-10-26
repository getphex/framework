#!/usr/bin/env python3
"""Compile a static PHP entry point to an ignored native HTTP executable."""

import argparse
from pathlib import Path
import shutil
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[2]


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, default=ROOT / "examples/compiled-container/native.php")
    parser.add_argument("--output", type=Path, default=ROOT / "var/build/phex-web.php")
    arguments = parser.parse_args()
    source = arguments.source.resolve()
    output = arguments.output.resolve()
    if not source.is_file():
        parser.error(f"Entry point does not exist: {source}")
    if not output.is_relative_to(ROOT / "var") or output.suffix != ".php":
        parser.error("Generated entry points must be .php files inside var/.")
    if shutil.which("elephc") is None:
        parser.error("Required executable is missing: elephc")
    output.parent.mkdir(parents=True, exist_ok=True)
    path = str(source).replace("\\", "\\\\").replace("'", "\\'")
    wrapper = "<?php\ndeclare(strict_types=1);\nrequire '" + path + "';\n"
    output.write_text(wrapper, encoding="utf-8")
    try:
        result = subprocess.run(["elephc", "--web", "--strict-php", str(output)], cwd=ROOT, check=False)
    finally:
        output.with_suffix(".s").unlink(missing_ok=True)
    if result.returncode:
        # Do not leave a stale executable looking like a successful new build.
        output.with_suffix("").unlink(missing_ok=True)
        return result.returncode
    print(f"Native HTTP executable: {output.with_suffix('')}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
