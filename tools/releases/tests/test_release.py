"""Verify the interactive release command and partial-publication recovery."""

import contextlib
import io
import os
from pathlib import Path
import subprocess
import sys
import unittest
from unittest.mock import patch

sys.dont_write_bytecode = True
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import release
from application import APIError, APPLICATION, FRAMEWORK, GitHub, ReleaseError, resolve_tag
from test_application import RepositoryAPI


class PublishingAPI(RepositoryAPI):
    def __init__(self):
        super().__init__()
        self.tags.clear()
        self.remote_tags = {}
        self.framework_race = None

    def call(self, repository, endpoint, payload=None, missing=False):
        if endpoint.startswith('tags?'):
            names = self.remote_tags.get(repository, [name for repo, name in self.tags if repo == repository])
            page = int(endpoint.rsplit('=', 1)[1])
            return [{'name': name} for name in names[(page - 1) * 100:page * 100]]
        if repository == FRAMEWORK and payload is not None:
            self.writes.append((repository, endpoint, payload))
            if endpoint == 'git/tags':
                self.annotated[(FRAMEWORK, '7' * 40)] = {'type': 'commit', 'sha': payload['object']}
                return {'sha': '7' * 40}
            if endpoint == 'git/refs':
                tag = payload['ref'].removeprefix('refs/tags/')
                if self.framework_race:
                    self.tags[(FRAMEWORK, tag)] = {'type': 'commit', 'sha': self.framework_race}
                    raise APIError(422, 'Reference already exists')
                self.tags[(FRAMEWORK, tag)] = {'type': 'tag', 'sha': payload['sha']}
                return {'object': self.tags[(FRAMEWORK, tag)]}
            raise AssertionError(endpoint)
        return super().call(repository, endpoint, payload=payload, missing=missing)


class ReleaseCommandTest(unittest.TestCase):
    def setUp(self):
        self.api = PublishingAPI()
        self.output = io.StringIO()
        self.stdout = contextlib.redirect_stdout(self.output)
        self.stdout.__enter__()
        self.addCleanup(self.stdout.__exit__, None, None, None)

    def plan(self, application='v0.1.0'):
        with patch('release.checkout', return_value=self.api.source):
            return release.preflight(self.api, Path('.'), 'v0.1.0', application)

    def dispatch(self, arguments, **unused):
        self.assertEqual(['gh', 'workflow', 'run', 'release.yml'], arguments[:4])
        self.assertIn('framework_tag=v0.1.0', arguments)
        self.assertIn('application_tag=v0.1.0', arguments)
        self.api.tags[(APPLICATION, 'v0.1.0')] = {
            'type': 'commit', 'sha': self.api.commits[0]['sha'],
        }
        return 'https://github.com/getphex/framework/actions/runs/123'

    def run_main(self, arguments, answers=()):
        with patch.dict(os.environ, {'VERSION': '', 'APPLICATION_VERSION': '', 'DRY_RUN': ''}), \
             patch('sys.argv', ['release.py', *arguments]), \
             patch('release.GitHub', return_value=self.api), \
             patch('release.checkout', return_value=self.api.source), \
             patch('release.input', side_effect=list(answers)) as prompt, \
             patch('release.command', side_effect=self.dispatch) as dispatch, \
             patch('release.subprocess.run', return_value=subprocess.CompletedProcess([], 0)), \
             contextlib.redirect_stderr(self.output):
            result = release.main()
            return result, prompt, dispatch

    def test_first_release_prompts_for_both_versions_and_confirms_once(self):
        result, prompt, dispatch = self.run_main([], ['', '', 'y'])
        self.assertEqual(0, result)
        self.assertEqual(3, prompt.call_count)
        self.assertEqual(1, dispatch.call_count)
        self.assertIn('Proposed framework tag: v0.1.0', self.output.getvalue())
        self.assertIn('Application v0.1.0: verified', self.output.getvalue())
        self.assertEqual(self.api.source, resolve_tag(self.api, FRAMEWORK, 'v0.1.0')[1])
        self.assertEqual(['git/tags', 'git/refs'], [entry[1] for entry in self.api.writes])

    def test_explicit_dry_run_does_not_prompt_write_or_dispatch(self):
        result, prompt, dispatch = self.run_main([
            '--framework-version', '0.1.0', '--application-version', 'v0.1.0', '--dry-run',
        ])
        self.assertEqual(0, result)
        prompt.assert_not_called()
        dispatch.assert_not_called()
        self.assertEqual([], self.api.writes)

    def test_abort_and_eof_never_publish(self):
        for answers in [['', '', 'n'], [EOFError()]]:
            result, unused, dispatch = self.run_main([], answers)
            self.assertEqual(1, result)
            self.assertEqual([], self.api.writes)
            dispatch.assert_not_called()

    def test_custom_version_and_application_skip_publish_only_the_framework(self):
        result, unused, dispatch = self.run_main([], ['0.2.0', '-', 'yes'])
        self.assertEqual(0, result)
        self.assertIn((FRAMEWORK, 'v0.2.0'), self.api.tags)
        self.assertEqual({FRAMEWORK}, {entry[0] for entry in self.api.writes})
        dispatch.assert_not_called()

    def test_noninteractive_confirmation_requires_explicit_version_choices(self):
        result, prompt, dispatch = self.run_main(['--yes'])
        self.assertEqual(1, result)
        prompt.assert_not_called()
        dispatch.assert_not_called()
        self.assertEqual([], self.api.writes)

    def test_patch_proposal_uses_numeric_remote_versions_across_pages(self):
        self.assertEqual('v0.1.0', release.next_version(self.api, FRAMEWORK))
        self.api.remote_tags[FRAMEWORK] = ['v1.2.9', 'v1.2.10', 'v99.0.0-rc.1'] + ['other'] * 98 + ['2.0.9']
        self.assertEqual('v2.0.10', release.next_version(self.api, FRAMEWORK))

    def test_conflicting_application_tag_is_rejected_before_any_framework_write(self):
        self.api.tags[(APPLICATION, 'v0.1.0')] = {
            'type': 'commit', 'sha': self.api.commits[0]['sha'],
        }
        self.api.commits[0]['commit']['tree']['sha'] = '0' * 40
        with self.assertRaisesRegex(ReleaseError, 'different tree'):
            self.plan()
        self.assertEqual([], self.api.writes)

    def test_changed_mirror_and_failed_ci_after_confirmation_stop_before_writes(self):
        plan = self.plan()
        self.api.commits.insert(0, self.api.commit('0' * 40, self.api.tree))
        with patch('release.checkout', return_value=self.api.source):
            with self.assertRaisesRegex(ReleaseError, 'changed after confirmation'):
                release.execute(self.api, Path('.'), plan)
            self.api.commits.pop(0)
            self.api.run['conclusion'] = 'failure'
            with self.assertRaisesRegex(ReleaseError, 'Compatibility run must pass'):
                release.execute(self.api, Path('.'), plan)
        self.assertEqual([], self.api.writes)

    def test_failed_dispatch_preserves_the_framework_tag_and_same_version_retry_works(self):
        plan = self.plan()
        with patch('release.checkout', return_value=self.api.source):
            with patch('release.command', side_effect=ReleaseError('Dispatch failed')):
                with self.assertRaisesRegex(ReleaseError, 'Dispatch failed'):
                    release.execute(self.api, Path('.'), plan)
            self.assertEqual(self.api.source, resolve_tag(self.api, FRAMEWORK, 'v0.1.0')[1])
            with patch('release.command', side_effect=self.dispatch), \
                 patch('release.subprocess.run', return_value=subprocess.CompletedProcess([], 0)):
                release.execute(self.api, Path('.'), self.plan())
        self.assertEqual(2, len(self.api.writes))

    def test_failed_application_job_does_not_delete_or_move_the_framework_tag(self):
        with patch('release.checkout', return_value=self.api.source), \
             patch('release.command', return_value='https://github.com/getphex/framework/actions/runs/123'), \
             patch('release.subprocess.run', return_value=subprocess.CompletedProcess([], 1)):
            with self.assertRaisesRegex(ReleaseError, 'did not complete successfully'):
                release.execute(self.api, Path('.'), self.plan())
        self.assertEqual(self.api.source, resolve_tag(self.api, FRAMEWORK, 'v0.1.0')[1])
        self.assertEqual(['git/tags', 'git/refs'], [entry[1] for entry in self.api.writes])

    def test_workflow_success_without_the_expected_application_tag_is_not_reported_as_release(self):
        with patch('release.checkout', return_value=self.api.source), \
             patch('release.command', return_value='https://github.com/getphex/framework/actions/runs/123'), \
             patch('release.subprocess.run', return_value=subprocess.CompletedProcess([], 0)):
            with self.assertRaisesRegex(ReleaseError, 'did not publish the confirmed'):
                release.execute(self.api, Path('.'), self.plan())

    def test_already_published_matching_versions_are_a_noop(self):
        release.publish_framework(self.api, 'v0.1.0', self.api.source)
        self.api.tags[(APPLICATION, 'v0.1.0')] = {
            'type': 'commit', 'sha': self.api.commits[0]['sha'],
        }
        with patch('release.checkout', return_value=self.api.source), patch('release.command') as dispatch:
            release.execute(self.api, Path('.'), self.plan())
        self.assertEqual(2, len(self.api.writes))
        dispatch.assert_not_called()

    def test_framework_tag_conflict_and_create_races_never_overwrite_refs(self):
        self.api.tags[(FRAMEWORK, 'v0.1.0')] = {'type': 'commit', 'sha': '0' * 40}
        with self.assertRaisesRegex(ReleaseError, 'will not move'):
            release.publish_framework(self.api, 'v0.1.0', self.api.source)
        self.assertEqual([], self.api.writes)
        for target in [self.api.source, '0' * 40]:
            self.api = PublishingAPI()
            self.api.framework_race = target
            if target == self.api.source:
                self.assertFalse(release.publish_framework(self.api, 'v0.1.0', self.api.source))
            else:
                with self.assertRaises(APIError):
                    release.publish_framework(self.api, 'v0.1.0', self.api.source)


class CheckoutTest(unittest.TestCase):
    def test_only_a_clean_main_at_the_expected_github_head_is_releasable(self):
        api = PublishingAPI()
        answers = {
            ('status', '--porcelain', '--untracked-files=all'): '',
            ('branch', '--show-current'): 'main',
            ('remote', 'get-url', 'origin'): 'git@github.com:getphex/framework.git',
            ('rev-parse', 'HEAD'): api.source,
        }
        def git(arguments, **unused):
            return answers[tuple(arguments[1:])]
        with patch('release.command', side_effect=git):
            self.assertEqual(api.source, release.checkout(api, Path('.')))
            for key, bad, message in [
                (('status', '--porcelain', '--untracked-files=all'), '?? file', 'not clean'),
                (('branch', '--show-current'), 'feature', 'main branch'),
                (('remote', 'get-url', 'origin'), 'git@github.com:another/framework.git', 'origin'),
                (('rev-parse', 'HEAD'), '0' * 40, 'equal GitHub main'),
            ]:
                original = answers[key]
                answers[key] = bad
                with self.subTest(message=message), self.assertRaisesRegex(ReleaseError, message):
                    release.checkout(api, Path('.'))
                answers[key] = original

    def test_local_driver_uses_existing_gh_authentication_without_reading_secrets(self):
        response = subprocess.CompletedProcess([], 0, 'HTTP/2.0 200 OK\n\n{}', '')
        with patch.dict(os.environ, {}, clear=True), \
             patch('application.subprocess.run', return_value=response) as run:
            GitHub().call(FRAMEWORK, 'git/ref/heads/main')
        self.assertNotIn('GH_TOKEN', run.call_args.kwargs['env'])


if __name__ == '__main__':
    unittest.main()
