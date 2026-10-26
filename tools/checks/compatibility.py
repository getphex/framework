#!/usr/bin/env python3
"""Run the same behavior suite under PHP and ElephC and compare JUnit results."""

from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET


ROOT = Path(__file__).resolve().parents[2]


def read_report(path: Path) -> dict[tuple[str, str], str]:
    cases = {}
    for case in ET.parse(path).getroot().iter("testcase"):
        # PHPUnit uses dots in classname; native reports use PHP namespace separators.
        class_name = case.attrib.get("class") or case.attrib["classname"]
        class_name = ".".join(part for part in class_name.replace("\\", ".").split(".") if part)
        key = (class_name, case.attrib["name"])
        if key in cases:
            raise ValueError(f"Duplicate test case in {path.name}: {key}")
        outcome = "passed"
        for tag in ("failure", "error", "skipped"):
            if case.find(tag) is not None:
                outcome = tag
                break
        cases[key] = outcome
    if not cases:
        raise ValueError(f"No test cases in {path.name}")
    return cases


def main() -> int:
    for tool in ("php", "elephc"):
        if shutil.which(tool) is None:
            print(f"Required executable is missing: {tool}", file=sys.stderr)
            return 1
    phpunit = ROOT / "vendor/bin/phpunit"
    if not phpunit.is_file():
        print("Run composer install before running compatibility tests.", file=sys.stderr)
        return 1

    artifacts = ROOT / "var"
    artifacts.mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(prefix="compatibility-", dir=artifacts) as directory:
        reports = Path(directory)
        php_report = reports / "php.xml"
        native_report = reports / "elephc.xml"
        commands = (
            ["php", str(phpunit), "--log-junit", str(php_report)],
            ["elephc", "test", "tests/Compatibility", "--strict-php", "--junit", str(native_report)],
        )
        exits = []
        for command in commands:
            print("Running " + " ".join(command), flush=True)
            exits.append(subprocess.run(command, cwd=ROOT, check=False).returncode)

        if any(code != 0 for code in exits):
            return 1

        try:
            php_cases = read_report(php_report)
            native_cases = read_report(native_report)
        except (OSError, ET.ParseError, KeyError, ValueError) as error:
            print(f"Invalid compatibility report: {error}", file=sys.stderr)
            return 1

        if php_cases != native_cases:
            for key in sorted(php_cases.keys() | native_cases.keys()):
                php_result = php_cases.get(key, "missing")
                native_result = native_cases.get(key, "missing")
                if php_result != native_result:
                    print(f"{key}: PHP={php_result}, ElephC={native_result}", file=sys.stderr)
            return 1

        if any(outcome != "passed" for outcome in php_cases.values()):
            print("Every compatibility case must pass on both runtimes.", file=sys.stderr)
            return 1

        print(f"Compatibility verified: {len(php_cases)} shared cases passed on PHP and ElephC.")

        example = ROOT / "examples/hello-world/main.php"
        source = reports / "example.php"
        escaped_path = str(example).replace("\\", "\\\\").replace("'", "\\'")
        source.write_text("<?php\nrequire '" + escaped_path + "';\n", encoding="utf-8")
        compiled = subprocess.run(["elephc", "--strict-php", str(source)], cwd=ROOT, check=False)
        if compiled.returncode != 0:
            return 1
        php_example = subprocess.run(["php", str(example)], cwd=ROOT, capture_output=True, check=False)
        native_example = subprocess.run([str(source.with_suffix(""))], cwd=ROOT, capture_output=True, check=False)
        expected = b'{"hello":"world"}\n'
        for name, result in (("PHP", php_example), ("ElephC", native_example)):
            if result.returncode != 0 or result.stdout != expected or result.stderr:
                print(f"{name} example failed: {result}", file=sys.stderr)
                return 1
        print("JSON example verified on PHP and ElephC.")
        return 0


if __name__ == "__main__":
    sys.exit(main())
