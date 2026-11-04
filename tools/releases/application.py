"""Tag a published application tree from an independently versioned framework tag."""

import argparse
from dataclasses import asdict, dataclass
import json
import os
from pathlib import Path
import re
import subprocess
import sys


FRAMEWORK = 'getphex/framework'
APPLICATION = 'getphex/phex'
VERSION = re.compile(r'v(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)'
                     r'(?:-(?:alpha|beta|rc|RC)(?:\.(?:0|[1-9][0-9]*))?)?')


class ReleaseError(RuntimeError):
    pass


class APIError(ReleaseError):
    def __init__(self, status, message):
        super().__init__(message)
        self.status = status


class GitHub:
    def __init__(self, source_token, mirror_token):
        self.tokens = {FRAMEWORK: source_token, APPLICATION: mirror_token}

    def call(self, repository, endpoint, payload=None, missing=False):
        method = 'GET' if payload is None else 'POST'
        command = ['gh', 'api', '--hostname', 'github.com', '--include', '--method', method,
                   f'repos/{repository}/{endpoint}']
        if payload is not None:
            command += ['--input', '-']
        environment = dict(os.environ, GH_TOKEN=self.tokens[repository])
        result = subprocess.run(command, input=None if payload is None else json.dumps(payload),
                                capture_output=True, text=True, env=environment, timeout=60)
        headers, separator, body = result.stdout.partition('\n\n')
        match = re.match(r'HTTP/\S+ ([0-9]{3})\b', headers)
        status = int(match[1]) if match else None
        if missing and status == 404:
            return None
        if result.returncode or status is None or not 200 <= status < 300 or not separator:
            raise APIError(status, f'GitHub {method} {repository}/{endpoint} failed '
                           f'(HTTP {status or "unknown"}).')
        return json.loads(body)


@dataclass(frozen=True)
class Release:
    framework_tag: str
    application_tag: str
    source_ref: str
    source: str
    tree: str
    mirror_head: str
    mirror: str
    existing_ref: str | None
    compatibility_url: str


def resolve_tag(api, repository, tag, missing=False):
    reference = api.call(repository, f'git/ref/tags/{tag}', missing=missing)
    if reference is None:
        return None
    original = reference['object']['sha']
    target = reference['object']
    seen = set()
    while target['type'] == 'tag':
        if target['sha'] in seen or len(seen) >= 16:
            raise ReleaseError('Invalid annotated tag chain.')
        seen.add(target['sha'])
        target = api.call(repository, f'git/tags/{target["sha"]}')['object']
    if target['type'] != 'commit':
        raise ReleaseError(f'{repository} tag {tag} must resolve to a commit.')
    return original, target['sha']


def require_compatibility(api, source):
    main = api.call(FRAMEWORK, 'git/ref/heads/main')['object']['sha']
    comparison = api.call(FRAMEWORK, f'compare/{source}...{main}?per_page=1')
    if comparison['merge_base_commit']['sha'] != source:
        raise ReleaseError('The framework tag must belong to main history.')
    response = api.call(FRAMEWORK, 'actions/workflows/compatibility.yml/runs'
                        f'?head_sha={source}&branch=main&event=push&per_page=1')
    runs = response['workflow_runs']
    if not runs:
        raise ReleaseError('No main push Compatibility run exists for the exact framework commit.')
    latest = runs[0]
    if (latest['head_sha'] != source or latest['head_branch'] != 'main'
            or latest['event'] != 'push' or latest['head_repository']['full_name'] != FRAMEWORK
            or latest['status'] != 'completed' or latest['conclusion'] != 'success'):
        raise ReleaseError('The latest exact-commit main push Compatibility run must pass.')
    return latest['html_url']


def prepare(api, framework_tag, application_tag):
    for tag in (framework_tag, application_tag):
        if not VERSION.fullmatch(tag):
            raise ReleaseError('Use version tags such as v0.1.0 or v0.1.0-rc.1.')
    source_ref, source = resolve_tag(api, FRAMEWORK, framework_tag)
    compatibility = require_compatibility(api, source)
    root = api.call(FRAMEWORK, f'git/commits/{source}')['tree']['sha']
    listing = api.call(FRAMEWORK, f'git/trees/{root}?recursive=1')
    if listing['truncated']:
        raise ReleaseError('The framework tree listing is truncated.')
    tree = next((entry['sha'] for entry in listing['tree']
                 if entry['path'] == 'stubs/application' and entry['type'] == 'tree'), None)
    if tree is None:
        raise ReleaseError('The tagged framework has no stubs/application tree.')

    head = api.call(APPLICATION, 'git/ref/heads/main')['object']['sha']
    existing = resolve_tag(api, APPLICATION, application_tag, missing=True)
    page = 1
    while True:
        commits = api.call(APPLICATION, f'commits?sha={head}&per_page=100&page={page}')
        if not commits:
            break
        for commit in commits:
            if existing is not None and commit['sha'] != existing[1]:
                continue
            if commit['commit']['tree']['sha'] == tree:
                return Release(framework_tag, application_tag, source_ref, source, tree, head,
                               commit['sha'], None if existing is None else existing[0], compatibility)
            if existing is not None:
                raise ReleaseError('The existing application tag has a different tree; it will not move.')
        page += 1
    if existing is not None:
        raise ReleaseError('The existing application tag is outside published main history.')
    raise ReleaseError('No published mirror commit matches the tagged skeleton. Wait for the split, '
                       'or release a framework commit whose skeleton was published.')


def publish(api, release):
    # Recheck mutable refs and CI immediately before the only write operation.
    if resolve_tag(api, FRAMEWORK, release.framework_tag) != (release.source_ref, release.source):
        raise ReleaseError('The framework tag changed during release; rerun the workflow.')
    require_compatibility(api, release.source)
    if api.call(APPLICATION, 'git/ref/heads/main')['object']['sha'] != release.mirror_head:
        raise ReleaseError('Mirror main changed during release; rerun the workflow.')
    existing = resolve_tag(api, APPLICATION, release.application_tag, missing=True)
    if release.existing_ref is not None:
        if existing != (release.existing_ref, release.mirror):
            raise ReleaseError('The application tag changed during release; it will not move.')
        return False
    if existing is not None:
        if existing[1] == release.mirror:
            return False
        raise ReleaseError('The application tag already targets another commit; it will not move.')
    try:
        api.call(APPLICATION, 'git/refs', payload={
            'ref': f'refs/tags/{release.application_tag}', 'sha': release.mirror,
        })
    except APIError as error:
        # Another publisher can win the create race. Accept only the same exact commit.
        if error.status not in (409, 422):
            raise
        target = resolve_tag(api, APPLICATION, release.application_tag, missing=True)
        if target is not None and target[1] == release.mirror:
            return False
        raise
    target = resolve_tag(api, APPLICATION, release.application_tag)
    if target[1] != release.mirror:
        raise ReleaseError('Published tag verification failed.')
    return True


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--framework-tag', required=True)
    parser.add_argument('--application-tag', required=True)
    parser.add_argument('--publish', action='store_true', help='Create the tag; otherwise only validate.')
    options = parser.parse_args()
    source_token = os.environ.get('GH_TOKEN', '')
    mirror_token = os.environ.get('PHEX_MIRROR_TOKEN', source_token)
    if not source_token or not mirror_token:
        parser.error('GH_TOKEN and a mirror-readable PHEX_MIRROR_TOKEN are required.')
    try:
        api = GitHub(source_token, mirror_token)
        release = prepare(api, options.framework_tag, options.application_tag)
        created = publish(api, release) if options.publish else False
    except (ReleaseError, OSError, ValueError, KeyError, subprocess.TimeoutExpired) as error:
        print(f'Application release failed: {error}', file=sys.stderr)
        return 1
    result = dict(asdict(release), created=created, dry_run=not options.publish)
    print(json.dumps(result, indent=2))
    summary = os.environ.get('GITHUB_STEP_SUMMARY')
    if summary:
        with Path(summary).open('a') as output:
            output.write(f'### Application release\n\n'
                         f'- Framework: `{FRAMEWORK}@{release.framework_tag}` (`{release.source}`)\n'
                         f'- Application: `{APPLICATION}@{release.application_tag}` (`{release.mirror}`)\n'
                         f'- Exact skeleton tree: `{release.tree}`\n'
                         f'- Compatibility: {release.compatibility_url}\n'
                         f'- Dry run: `{not options.publish}`; created: `{created}`\n')
    return 0


if __name__ == '__main__':
    sys.exit(main())
