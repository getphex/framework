# Benchmarking and profiling

[Documentation](README.md)

Performance results need a defined application, runtime, environment and transport path. The repository includes measurement tools for contributors; these are separate from the application quickstart.

## On this page

- [Choose the comparison](#choose-the-comparison)
- [Repository tools](#repository-tools)
- [Run a controlled lifecycle comparison](#run-a-controlled-lifecycle-comparison)
- [Preserve evidence](#preserve-evidence)

## Choose the comparison

A native Phex binary is already an HTTP server. PHP-FPM normally needs an HTTP frontend speaking FastCGI. Putting a reverse proxy in front of native HTTP adds another HTTP hop and measures a different path.

Report both the intended deployment path and its layers. A direct-native versus frontend/FPM result is an end-to-end deployment comparison. A same-frontend comparison includes native's extra proxy hop. Neither number alone isolates framework execution time.

Keep worker count, client concurrency, body, logging policy, OPcache/preload, JIT settings and warm-up explicit. Rotate comparable runs and retain errors and slow samples rather than selecting a peak.

## Repository tools

From the **framework checkout**:

| Tool | Purpose |
| --- | --- |
| `tools/benchmarks/native_lifecycle.py` | Controlled single-worker lifecycle comparison |
| `tools/benchmarks/web_mode_comparison.py` | Native isolation/lifecycle matrix and PHP-FPM controls |
| `tools/benchmarks/native_frontend_comparison.py` | Direct native HTTP, proxied native HTTP and frontend/FPM |
| `tools/benchmarks/request_profile.py` | Reproducible CPU/allocation and request-stage profiling workspace |
| `tools/benchmarks/compare_request_versions.py` | Separate compiler, header and environment changes |
| `tools/benchmarks/paired_request_versions.py` | Adjacent ABBA confirmation from retained evidence |
| `tools/benchmarks/analyze_request_profile.py` | Summarize existing profiling evidence |

Each tool exposes `--help`; some consume reports from an earlier preparation or matrix command. Read those prerequisites before launching a load run.

## Run a controlled lifecycle comparison

The lifecycle tool expects Linux, suitable physical CPU cores, PHP-FPM, `hey`, `taskset`, Composer, authenticated `gh` and a capable ElephC SDK. It obtains its verified frontend and records provenance:

```sh
python3 tools/benchmarks/native_lifecycle.py --compiler /absolute/path/to/elephc
```

Builds inherit the build-host environment. Runtime processes use a separate controlled environment by default. Forward application values explicitly to native and FPM:

```sh
PHEX_PAGE_SIZE=30 python3 tools/benchmarks/native_lifecycle.py \
  --compiler /absolute/path/to/elephc --pass-env PHEX_PAGE_SIZE
```

The default workload expects the skeleton's default home JSON. A greeting override changes that workload and intentionally fails its response check. Reports retain environment names/counts and byte sizes rather than secret values.

## Preserve evidence

Keep compiler/source revisions, SDK archives, binary hashes, CPU layout, warm-up and run order together with throughput, latency, backend/frontend CPU and errors. Results are written under ignored `var/benchmarks/`; keep reports when making a performance claim.

When profiling, distinguish per-request bootstrap, application/request construction, allocation/runtime work, database or other I/O, transport and frontend overhead. CPU attribution is not a sum of independent isolated stages unless the measurement protocol establishes that separation.

This documentation does not publish a universal requests-per-second figure. Reproduce the actual workload and topology relevant to the application being evaluated.

---

[Previous: Contributing to Phex](contributing.md)
