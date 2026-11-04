"""Interactively publish framework and optional application version tags with make release."""

import argparse
from dataclasses import dataclass, field
import os
from pathlib import Path
import re
import subprocess
import sys

from application import (APIError, APPLICATION, FRAMEWORK, GitHub, ReleaseError, VERSION,
                         prepare, require_compatibility, resolve_tag, select_application)


def command(arguments, cwd=None):
    result = subprocess.run(arguments, cwd=cwd, capture_output=True, text=True)
    if result.returncode:
        raise ReleaseError(result.stderr.strip() or f'{arguments[0]} exited with {result.returncode}.')
    return result.stdout.strip()


def checkout(api, root):
    def git(*arguments):
        return command(['git', *arguments], cwd=root)
    if git('status', '--porcelain', '--untracked-files=all'):
        raise ReleaseError('Working tree is not clean; commit and push changes first.')
    if git('branch', '--show-current') != 'main':
        raise ReleaseError('Run the release from the main branch.')
    origin = git('remote', 'get-url', 'origin').rstrip('/').removesuffix('.git')
    if origin not in (f'git@github.com:{FRAMEWORK}', f'https://github.com/{FRAMEWORK}',
                      f'ssh://git@github.com/{FRAMEWORK}'):
        raise ReleaseError(f'origin must point to github.com/{FRAMEWORK}.')
    source = git('rev-parse', 'HEAD')
    remote = api.call(FRAMEWORK, 'git/ref/heads/main')['object']['sha']
    if source != remote:
        raise ReleaseError('Local HEAD must equal GitHub main; synchronize the checkout first.')
    return source


def next_version(api, repository):
    versions = []
    page = 1
    while True:
        tags = api.call(repository, f'tags?per_page=100&page={page}')
        if not tags:
            break
        for tag in tags:
            match = re.fullmatch(r'v?([0-9]+)\.([0-9]+)\.([0-9]+)', tag['name'])
            if match:
                versions.append(tuple(int(part) for part in match.groups()))
        page += 1
    if not versions:
        return 'v0.1.0'
    major, minor, patch = max(versions)
    return f'v{major}.{minor}.{patch + 1}'


def version(value):
    value = value.strip()
    normalized = value if value.startswith('v') else 'v' + value
    if not VERSION.fullmatch(normalized):
        raise ReleaseError('Use versions such as 0.1.0, v0.1.0 or v0.1.0-rc.1.')
    return normalized


@dataclass(frozen=True)
class Plan:
    source: str
    framework_tag: str
    framework_ref: str | None
    application_tag: str | None
    application: dict | None
    compatibility: str = field(compare=False)


def preflight(api, root, framework_tag, application_tag):
    source = checkout(api, root)
    compatibility = require_compatibility(api, source)
    existing = resolve_tag(api, FRAMEWORK, framework_tag, missing=True)
    if existing is not None and existing[1] != source:
        raise ReleaseError('The framework version already targets another commit; it will not move.')
    application = None if application_tag is None else select_application(api, source, application_tag)
    return Plan(source, framework_tag, None if existing is None else existing[0],
                application_tag, application, compatibility)


def publish_framework(api, tag, source):
    existing = resolve_tag(api, FRAMEWORK, tag, missing=True)
    if existing is not None:
        if existing[1] != source:
            raise ReleaseError('The framework version already targets another commit; it will not move.')
        return False
    annotation = api.call(FRAMEWORK, 'git/tags', payload={
        'tag': tag, 'message': f'Release {tag}', 'object': source, 'type': 'commit',
    })
    try:
        api.call(FRAMEWORK, 'git/refs', payload={'ref': f'refs/tags/{tag}', 'sha': annotation['sha']})
    except APIError as error:
        if error.status not in (409, 422):
            raise
        existing = resolve_tag(api, FRAMEWORK, tag, missing=True)
        if existing is not None and existing[1] == source:
            return False
        raise
    if resolve_tag(api, FRAMEWORK, tag)[1] != source:
        raise ReleaseError('Framework tag verification failed.')
    return True


def execute(api, root, plan):
    if preflight(api, root, plan.framework_tag, plan.application_tag) != plan:
        raise ReleaseError('Release inputs changed after confirmation; run make release again.')
    created = publish_framework(api, plan.framework_tag, plan.source)
    print(f'Framework {plan.framework_tag}: {"published" if created else "already published"}.', flush=True)
    if plan.application_tag is None:
        return
    if plan.application['existing_ref'] is not None:
        print(f'Application {plan.application_tag}: already published.')
        return
    output = command(['gh', 'workflow', 'run', 'release.yml', '--repo', FRAMEWORK, '--ref', 'main',
                      '-f', f'framework_tag={plan.framework_tag}',
                      '-f', f'application_tag={plan.application_tag}'])
    print(output, flush=True)
    run = re.search(r'https://github\.com/getphex/framework/actions/runs/([0-9]+)', output)
    if run is None:
        raise ReleaseError('The workflow was dispatched, but gh returned no run URL. '
                           'Check GitHub Actions before retrying.')
    print(f'Waiting for application publication: {run[0]}', flush=True)
    result = subprocess.run(['gh', 'run', 'watch', run[1], '--repo', FRAMEWORK,
                             '--exit-status', '--compact', '--interval', '10'])
    if result.returncode:
        raise ReleaseError(f'Application publication did not complete successfully: {run[0]}')
    released = prepare(api, plan.framework_tag, plan.application_tag)
    if released.existing_ref is None or released.mirror != plan.application['mirror']:
        raise ReleaseError('The completed workflow did not publish the confirmed application commit.')
    print(f'Application {plan.application_tag}: verified at {released.mirror}.')


def main():
    parser = argparse.ArgumentParser(description=__doc__, epilog=(
        'From the framework checkout: make release. Optional Make variables: '
        'VERSION=0.1.0 APPLICATION_VERSION=0.1.0 (or - to skip) DRY_RUN=1.'))
    parser.add_argument('--framework-version', default=os.environ.get('VERSION') or None)
    group = parser.add_mutually_exclusive_group()
    group.add_argument('--application-version', default=os.environ.get('APPLICATION_VERSION') or None)
    group.add_argument('--skip-application', action='store_true')
    parser.add_argument('--dry-run', action='store_true', default=os.environ.get('DRY_RUN') == '1')
    parser.add_argument('--yes', action='store_true', help='Use with explicit versions to skip confirmation.')
    options = parser.parse_args()
    root = Path(__file__).resolve().parents[2]
    plan = None
    try:
        if options.yes and (options.framework_version is None or
                            (options.application_version is None and not options.skip_application)):
            raise ReleaseError('--yes requires an explicit framework version and application version or skip.')
        api = GitHub()
        checkout(api, root)
        proposed = next_version(api, FRAMEWORK)
        print(f'Proposed framework tag: {proposed}')
        framework_tag = version(options.framework_version or
                                input(f'Framework version [{proposed}]: ') or proposed)
        application_tag = None
        if not options.skip_application:
            proposed_application = next_version(api, APPLICATION)
            selected = options.application_version or input(
                f'Application version [{proposed_application}], or - to skip: ') or proposed_application
            if selected.strip() != '-':
                application_tag = version(selected)
        plan = preflight(api, root, framework_tag, application_tag)
        print(f'\nFramework: {FRAMEWORK}@{framework_tag}\nCommit: {plan.source}\n'
              f'Compatibility: {plan.compatibility}')
        if application_tag:
            print(f'Application: {APPLICATION}@{application_tag}\n'
                  f'Mirror commit: {plan.application["mirror"]}\nExact tree: {plan.application["tree"]}')
        else:
            print('Application release: skipped.')
        if options.dry_run:
            print('\nDry run completed. No tags or workflow dispatches were created.')
            return 0
        if not options.yes and input('\nPublish these versions? [y/N] ').lower() not in ('y', 'yes'):
            print('Aborted.')
            return 1
        execute(api, root, plan)
        return 0
    except (ReleaseError, OSError, ValueError, KeyError, EOFError, KeyboardInterrupt,
            subprocess.TimeoutExpired) as error:
        print(f'Release stopped: {error or "interrupted"}', file=sys.stderr)
        if plan is not None:
            application = plan.application_tag or '-'
            print('If the framework tag was already published, keep it and retry the same versions:\n'
                  f'  make release VERSION={plan.framework_tag} APPLICATION_VERSION={application}',
                  file=sys.stderr)
        return 1


if __name__ == '__main__':
    sys.exit(main())
