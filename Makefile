.DEFAULT_GOAL := help
.PHONY: help release

# Export values as environment data, not interpolated shell commands.
export VERSION APPLICATION_VERSION DRY_RUN

help:
	@python3 -B tools/releases/release.py --help

release:
	@python3 -B tools/releases/release.py
