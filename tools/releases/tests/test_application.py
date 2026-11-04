"""Exercise release gates and immutable publication without touching GitHub refs."""

import json
from pathlib import Path
import subprocess
import sys
import unittest
from unittest.mock import patch

sys.dont_write_bytecode = True
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from application import APIError, APPLICATION, FRAMEWORK, GitHub, ReleaseError, prepare, publish


class RepositoryAPI:
    def __init__(self):
        self.source = 'a' * 40
        self.tree = 'b' * 40
        self.tags = {(FRAMEWORK, 'v0.1.0'): {'type': 'commit', 'sha': self.source}}
        self.annotated = {}
        self.commits = [self.commit('c' * 40, self.tree)]
        self.main = self.source
        self.merge_base = self.source
        self.run = {'head_sha': self.source, 'head_branch': 'main', 'event': 'push',
                    'head_repository': {'full_name': FRAMEWORK}, 'status': 'completed',
                    'conclusion': 'success', 'html_url': 'https://github.com/example/run/1'}
        self.truncated = False
        self.has_skeleton = True
        self.writes = []
        self.create_race = None
        self.releases = {}
        self.release_commands = []

    @staticmethod
    def commit(sha, tree):
        return {'sha': sha, 'commit': {'tree': {'sha': tree}}}

    def call(self, repository, endpoint, payload=None, missing=False):
        if endpoint.startswith('releases/tags/'):
            return self.releases.get((repository, endpoint.removeprefix('releases/tags/')))
        if endpoint.startswith('releases?'):
            page = int(endpoint.rsplit('=', 1)[1])
            releases = [value for (repo, unused), value in self.releases.items() if repo == repository]
            return releases[(page - 1) * 100:page * 100]
        if endpoint == 'releases/generate-notes':
            return {'body': '## Changes\n\nGenerated changelog.\n'}
        if payload is not None:
            self.writes.append((repository, endpoint, payload))
            tag = payload['ref'].removeprefix('refs/tags/')
            if self.create_race is not None:
                self.tags[(APPLICATION, tag)] = {'type': 'commit', 'sha': self.create_race}
                raise APIError(422, 'Reference already exists')
            self.tags[(APPLICATION, tag)] = {'type': 'commit', 'sha': payload['sha']}
            return {'ref': payload['ref'], 'object': self.tags[(APPLICATION, tag)]}
        if endpoint.startswith('git/ref/tags/'):
            tag = endpoint.removeprefix('git/ref/tags/')
            target = self.tags.get((repository, tag))
            if target is None and missing:
                return None
            if target is None:
                raise APIError(404, 'Tag not found')
            return {'object': target}
        if endpoint.startswith('git/tags/'):
            return {'object': self.annotated[(repository, endpoint.removeprefix('git/tags/'))]}
        if endpoint == 'git/ref/heads/main':
            return {'object': {'type': 'commit', 'sha': self.main if repository == FRAMEWORK
                               else self.commits[0]['sha']}}
        if endpoint.startswith('compare/'):
            return {'merge_base_commit': {'sha': self.merge_base}}
        if endpoint.startswith('actions/workflows/'):
            return {'workflow_runs': [] if self.run is None else [self.run]}
        if endpoint.startswith('git/commits/'):
            return {'tree': {'sha': 'd' * 40}}
        if endpoint.startswith('git/trees/'):
            return {'truncated': self.truncated, 'tree': [
                {'path': 'stubs/application', 'type': 'tree', 'sha': self.tree}
            ] if self.has_skeleton else []}
        if endpoint.startswith('commits?'):
            page = int(endpoint.rsplit('=', 1)[1])
            return self.commits[(page - 1) * 100:page * 100]
        raise AssertionError((repository, endpoint, payload))

    @staticmethod
    def release_data(repository, tag, **overrides):
        release = {'tag_name': tag, 'name': tag, 'body': 'Release notes.', 'draft': False,
                   'prerelease': '-' in tag, 'published_at': '2026-11-04T16:00:00Z',
                   'html_url': f'https://github.com/{repository}/releases/tag/{tag}',
                   'zipball_url': f'https://api.github.com/repos/{repository}/zipball/{tag}',
                   'tarball_url': f'https://api.github.com/repos/{repository}/tarball/{tag}'}
        release.update(overrides)
        return release

    def command(self, repository, arguments, stdin=None):
        self.release_commands.append((repository, arguments, stdin))
        tag = arguments[2]
        if arguments[1] == 'create':
            release = self.release_data(repository, tag, body=stdin + '\nGenerated changelog.')
        else:
            release = dict(self.releases[(repository, tag)], draft=False,
                           prerelease='--prerelease=true' in arguments)
            if '--notes-file' in arguments:
                release['body'] = stdin
        if '--title' in arguments:
            release['name'] = arguments[arguments.index('--title') + 1]
        self.releases[(repository, tag)] = release
        return release['html_url']


class ReleaseTest(unittest.TestCase):
    def setUp(self):
        self.api = RepositoryAPI()

    def prepare(self, application_tag='v0.1.0'):
        return prepare(self.api, 'v0.1.0', application_tag)

    def test_independent_versions_create_only_the_matching_application_tag(self):
        release = self.prepare('v0.2.0')
        self.assertTrue(publish(self.api, release))
        self.assertEqual([(APPLICATION, 'git/refs', {
            'ref': 'refs/tags/v0.2.0', 'sha': release.mirror,
        })], self.api.writes)
        self.assertEqual({'type': 'commit', 'sha': self.api.source},
                         self.api.tags[(FRAMEWORK, 'v0.1.0')])

    def test_prepare_is_a_read_only_dry_run(self):
        self.prepare()
        self.assertEqual([], self.api.writes)
        self.assertNotIn((APPLICATION, 'v0.1.0'), self.api.tags)

    def test_prerelease_versions_are_supported(self):
        self.api.tags[(FRAMEWORK, 'v0.1.0-rc.1')] = self.api.tags[(FRAMEWORK, 'v0.1.0')]
        release = prepare(self.api, 'v0.1.0-rc.1', 'v0.2.0-beta.1')
        self.assertTrue(publish(self.api, release))
        self.assertIn((APPLICATION, 'v0.2.0-beta.1'), self.api.tags)

    def test_historical_framework_and_mirror_tree_do_not_tag_current_main(self):
        old = self.api.commits[0]
        self.api.main = 'e' * 40
        self.api.commits = [self.api.commit('f' * 40, '0' * 40)] * 100 + [old]
        release = self.prepare()
        self.assertEqual(old['sha'], release.mirror)
        self.assertNotEqual(release.mirror_head, release.mirror)
        self.assertTrue(publish(self.api, release))

    def test_missing_mirror_tree_does_not_publish_an_unrelated_commit(self):
        self.api.commits[0]['commit']['tree']['sha'] = '0' * 40
        with self.assertRaisesRegex(ReleaseError, 'No published mirror commit matches'):
            self.prepare()
        self.assertEqual([], self.api.writes)

    def test_annotated_framework_and_existing_application_tags_are_supported(self):
        self.api.tags[(FRAMEWORK, 'v0.1.0')] = {'type': 'tag', 'sha': '1' * 40}
        self.api.annotated[(FRAMEWORK, '1' * 40)] = {'type': 'commit', 'sha': self.api.source}
        self.api.tags[(APPLICATION, 'v0.1.0')] = {'type': 'tag', 'sha': '2' * 40}
        self.api.annotated[(APPLICATION, '2' * 40)] = {
            'type': 'commit', 'sha': self.api.commits[0]['sha'],
        }
        self.assertFalse(publish(self.api, self.prepare()))
        self.assertEqual([], self.api.writes)

    def test_repeated_release_keeps_the_existing_matching_commit(self):
        first = self.prepare()
        self.assertTrue(publish(self.api, first))
        self.api.commits.insert(0, self.api.commit('9' * 40, self.api.tree))
        repeated = self.prepare()
        self.assertEqual(first.mirror, repeated.mirror)
        self.assertFalse(publish(self.api, repeated))
        self.assertEqual(1, len(self.api.writes))

    def test_existing_tag_with_different_content_is_never_moved(self):
        self.api.tags[(APPLICATION, 'v0.1.0')] = {
            'type': 'commit', 'sha': self.api.commits[0]['sha'],
        }
        self.api.commits[0]['commit']['tree']['sha'] = '0' * 40
        with self.assertRaisesRegex(ReleaseError, 'different tree'):
            self.prepare()
        self.assertEqual([], self.api.writes)

    def test_existing_tag_outside_main_history_is_rejected(self):
        self.api.tags[(APPLICATION, 'v0.1.0')] = {'type': 'commit', 'sha': '0' * 40}
        with self.assertRaisesRegex(ReleaseError, 'outside published main history'):
            self.prepare()

    def test_only_successful_exact_commit_main_push_ci_is_accepted(self):
        for changes in [{'head_sha': '0' * 40}, {'head_branch': 'feature'}, {'event': 'pull_request'},
                        {'head_repository': {'full_name': 'another/framework'}},
                        {'status': 'in_progress'}, {'conclusion': 'failure'}, {'conclusion': 'cancelled'}]:
            with self.subTest(changes=changes):
                self.api = RepositoryAPI()
                self.api.run.update(changes)
                with self.assertRaisesRegex(ReleaseError, 'Compatibility run must pass'):
                    self.prepare()
                self.assertEqual([], self.api.writes)

    def test_missing_ci_or_a_tag_outside_framework_main_is_rejected(self):
        self.api.run = None
        with self.assertRaisesRegex(ReleaseError, 'No main push Compatibility run'):
            self.prepare()
        self.api.merge_base = '0' * 40
        with self.assertRaisesRegex(ReleaseError, 'must belong to main history'):
            self.prepare()

    def test_changed_source_tag_and_failed_ci_are_rechecked_before_publication(self):
        release = self.prepare()
        self.api.tags[(FRAMEWORK, 'v0.1.0')]['sha'] = '0' * 40
        with self.assertRaisesRegex(ReleaseError, 'framework tag changed'):
            publish(self.api, release)
        self.api.tags[(FRAMEWORK, 'v0.1.0')]['sha'] = self.api.source
        self.api.run['conclusion'] = 'failure'
        with self.assertRaisesRegex(ReleaseError, 'Compatibility run must pass'):
            publish(self.api, release)
        self.assertEqual([], self.api.writes)

    def test_changed_mirror_main_and_conflicting_new_tag_are_rechecked(self):
        release = self.prepare()
        self.api.commits.insert(0, self.api.commit('0' * 40, self.api.tree))
        with self.assertRaisesRegex(ReleaseError, 'Mirror main changed'):
            publish(self.api, release)
        self.api.commits.pop(0)
        self.api.tags[(APPLICATION, 'v0.1.0')] = {'type': 'commit', 'sha': '0' * 40}
        with self.assertRaisesRegex(ReleaseError, 'another commit'):
            publish(self.api, release)
        self.assertEqual([], self.api.writes)

    def test_changed_existing_tag_is_not_repaired(self):
        publish(self.api, self.prepare())
        release = self.prepare()
        self.api.tags[(APPLICATION, 'v0.1.0')]['sha'] = '0' * 40
        with self.assertRaisesRegex(ReleaseError, 'application tag changed'):
            publish(self.api, release)
        self.assertEqual(1, len(self.api.writes))

    def test_atomic_create_race_accepts_only_the_same_commit(self):
        release = self.prepare()
        self.api.create_race = release.mirror
        self.assertFalse(publish(self.api, release))
        self.api = RepositoryAPI()
        release = self.prepare()
        self.api.create_race = '0' * 40
        with self.assertRaises(APIError):
            publish(self.api, release)

    def test_invalid_versions_and_incomplete_source_trees_are_rejected(self):
        for tag in ['main', 'v0.1', 'v01.1.0', 'v0.1.0/other', 'v0.1.0\n', '$(touch file)', '-x']:
            with self.subTest(tag=tag), self.assertRaisesRegex(ReleaseError, 'Use version tags'):
                prepare(self.api, 'v0.1.0', tag)
        self.api.truncated = True
        with self.assertRaisesRegex(ReleaseError, 'truncated'):
            self.prepare()
        self.api.truncated = False
        self.api.has_skeleton = False
        with self.assertRaisesRegex(ReleaseError, 'no stubs/application tree'):
            self.prepare()

    def test_non_commit_tags_and_annotation_cycles_are_rejected(self):
        self.api.tags[(FRAMEWORK, 'v0.1.0')]['type'] = 'tree'
        with self.assertRaisesRegex(ReleaseError, 'must resolve to a commit'):
            self.prepare()
        self.api.tags[(FRAMEWORK, 'v0.1.0')] = {'type': 'tag', 'sha': '1' * 40}
        self.api.annotated[(FRAMEWORK, '1' * 40)] = {'type': 'tag', 'sha': '1' * 40}
        with self.assertRaisesRegex(ReleaseError, 'Invalid annotated tag chain'):
            self.prepare()


class TransportTest(unittest.TestCase):
    def test_gh_receives_structured_payload_and_repository_scoped_tokens(self):
        api = GitHub('source-token', 'mirror-token')
        response = subprocess.CompletedProcess([], 0, 'HTTP/2.0 201 Created\ncontent-type: application/json\n\n{}', '')
        payload = {'ref': 'refs/tags/v0.1.0', 'sha': 'a' * 40}
        with patch('application.subprocess.run', return_value=response) as run:
            api.call(APPLICATION, 'git/refs', payload=payload)
            command = run.call_args.args[0]
            options = run.call_args.kwargs
            self.assertEqual('mirror-token', options['env']['GH_TOKEN'])
            self.assertEqual(payload, json.loads(options['input']))
            self.assertIn('--input', command)
            self.assertNotIn('mirror-token', ' '.join(command))
            api.call(FRAMEWORK, 'git/ref/heads/main')
            self.assertEqual('source-token', run.call_args.kwargs['env']['GH_TOKEN'])

    def test_only_http_404_is_treated_as_a_missing_tag(self):
        api = GitHub('source-token', 'mirror-token')
        for status in (403, 404, 429, 500):
            response = subprocess.CompletedProcess([], 1, f'HTTP/2.0 {status} Error\n\n{{}}', '')
            with self.subTest(status=status), patch('application.subprocess.run', return_value=response):
                if status == 404:
                    self.assertIsNone(api.call(APPLICATION, 'git/ref/tags/v0.1.0', missing=True))
                else:
                    with self.assertRaises(APIError):
                        api.call(APPLICATION, 'git/ref/tags/v0.1.0', missing=True)


if __name__ == '__main__':
    unittest.main()
