"""Verify complete GitHub Release publication while preserving tags and authored notes."""

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
import application
from application import (APPLICATION, FRAMEWORK, GitHub, ReleaseError,
                         complete_github_release, ensure_github_release, github_release)
from test_application import RepositoryAPI


class GitHubReleaseTest(unittest.TestCase):
    def setUp(self):
        self.api = RepositoryAPI()
        self.tag = 'v0.1.0'

    def publish(self):
        return ensure_github_release(self.api, FRAMEWORK, self.tag, self.api.source,
                                     'https://github.com/example/run/1')

    def test_creation_publishes_notes_and_archives_for_the_existing_tag(self):
        original = dict(self.api.tags[(FRAMEWORK, self.tag)])
        url = self.publish()
        repository, arguments, notes = self.api.release_commands[0]
        self.assertEqual(FRAMEWORK, repository)
        self.assertEqual(['release', 'create', self.tag], arguments[:3])
        self.assertIn('--verify-tag', arguments)
        self.assertIn('--generate-notes', arguments)
        self.assertIn('--notes-file', arguments)
        self.assertIn('composer require "getphex/framework:0.1.0"', notes)
        self.assertIn('PHP and ElephC compatibility checks', notes)
        published = self.api.releases[(FRAMEWORK, self.tag)]
        self.assertTrue(complete_github_release(published, self.tag))
        self.assertEqual(published['html_url'], url)
        self.assertEqual(original, self.api.tags[(FRAMEWORK, self.tag)])
        self.assertEqual([], self.api.writes)

    def test_application_prerelease_is_not_marked_latest(self):
        tag = 'v0.2.0-rc.1'
        source = self.api.commits[0]['sha']
        self.api.tags[(APPLICATION, tag)] = {'type': 'commit', 'sha': source}
        ensure_github_release(self.api, APPLICATION, tag, source)
        repository, arguments, notes = self.api.release_commands[0]
        self.assertEqual(APPLICATION, repository)
        self.assertIn('--prerelease', arguments)
        self.assertIn('--latest=false', arguments)
        self.assertIn('composer create-project getphex/phex my-app 0.2.0-rc.1 --stability=RC', notes)

    def test_existing_published_notes_and_title_are_never_rewritten(self):
        existing = self.api.release_data(FRAMEWORK, self.tag, name='Custom title', body='Authored notes.')
        self.api.releases[(FRAMEWORK, self.tag)] = existing
        self.assertEqual(existing['html_url'], self.publish())
        self.assertEqual([], self.api.release_commands)
        self.assertEqual('Authored notes.', existing['body'])

    def test_draft_publication_preserves_authored_notes_title_and_assets(self):
        existing = self.api.release_data(FRAMEWORK, self.tag, draft=True, name='Custom title',
                                         body='Authored notes.', assets=[{'name': 'reference.pdf'}])
        self.api.releases[(FRAMEWORK, self.tag)] = existing
        self.publish()
        arguments = self.api.release_commands[0][1]
        self.assertEqual(['release', 'edit', self.tag], arguments[:3])
        self.assertIn('--draft=false', arguments)
        self.assertNotIn('--title', arguments)
        self.assertNotIn('--notes-file', arguments)
        published = self.api.releases[(FRAMEWORK, self.tag)]
        self.assertFalse(published['draft'])
        self.assertEqual(existing['body'], published['body'])
        self.assertEqual(existing['assets'], published['assets'])

    def test_an_empty_draft_gets_notes_before_publication(self):
        self.api.releases[(FRAMEWORK, self.tag)] = self.api.release_data(
            FRAMEWORK, self.tag, draft=True, name='', body='')
        self.publish()
        published = self.api.releases[(FRAMEWORK, self.tag)]
        self.assertIn('Phex framework', published['name'])
        self.assertIn('Installation', published['body'])
        self.assertIn('Generated changelog.', published['body'])
        self.assertFalse(published['draft'])

    def test_drafts_hidden_from_the_tag_endpoint_are_found_in_the_release_list(self):
        existing = self.api.release_data(FRAMEWORK, self.tag, draft=True)
        self.api.releases[(FRAMEWORK, self.tag)] = existing
        original = self.api.call
        def hidden(repository, endpoint, **options):
            if endpoint.startswith('releases/tags/'):
                return None
            return original(repository, endpoint, **options)
        with patch.object(self.api, 'call', side_effect=hidden):
            self.assertEqual(existing, github_release(self.api, FRAMEWORK, self.tag))

    def test_missing_or_wrong_tag_cannot_create_a_release(self):
        for source in ['0' * 40, self.api.source]:
            if source == self.api.source:
                self.api.tags.clear()
            with self.subTest(source=source), self.assertRaises(ReleaseError):
                ensure_github_release(self.api, FRAMEWORK, self.tag, source)
        self.assertEqual([], self.api.release_commands)

    def test_conflicting_published_prerelease_metadata_is_not_changed(self):
        self.api.releases[(FRAMEWORK, self.tag)] = self.api.release_data(FRAMEWORK, self.tag, prerelease=True)
        with self.assertRaisesRegex(ReleaseError, 'conflicting'):
            self.publish()
        self.assertEqual([], self.api.release_commands)

    def test_a_concurrent_publisher_is_accepted_only_when_publication_is_complete(self):
        def concurrent(repository, arguments, stdin=None):
            self.api.releases[(repository, self.tag)] = self.api.release_data(repository, self.tag)
            raise ReleaseError('Already exists')
        with patch.object(self.api, 'command', side_effect=concurrent):
            self.assertIn('/releases/tag/v0.1.0', self.publish())
        self.api.releases.clear()
        with patch.object(self.api, 'command', side_effect=ReleaseError('Permission denied')):
            with self.assertRaisesRegex(ReleaseError, 'Permission denied'):
                self.publish()

    def test_moved_tags_and_incomplete_release_metadata_are_reported(self):
        original = self.api.command
        def moved(repository, arguments, stdin=None):
            result = original(repository, arguments, stdin=stdin)
            self.api.tags[(repository, self.tag)]['sha'] = '0' * 40
            return result
        with patch.object(self.api, 'command', side_effect=moved):
            with self.assertRaisesRegex(ReleaseError, 'verification failed'):
                self.publish()
        release = self.api.release_data(FRAMEWORK, self.tag)
        for missing in ['body', 'published_at', 'zipball_url', 'tarball_url']:
            incomplete = dict(release, **{missing: None})
            self.assertFalse(complete_github_release(incomplete, self.tag))
        self.assertEqual([], self.api.writes)

    def test_cli_authentication_and_multiline_notes_stay_out_of_command_arguments(self):
        response = subprocess.CompletedProcess([], 0, 'https://github.com/getphex/phex/releases/tag/v0.1.0', '')
        notes = 'First line\nSecond line with literal $() and `text`.\n'
        with patch('application.subprocess.run', return_value=response) as run:
            GitHub('source-token', 'mirror-token').command(APPLICATION,
                ['release', 'create', self.tag, '--verify-tag', '--notes-file', '-'], stdin=notes)
        self.assertEqual('mirror-token', run.call_args.kwargs['env']['GH_TOKEN'])
        self.assertEqual(notes, run.call_args.kwargs['input'])
        self.assertEqual(['--repo', 'github.com/getphex/phex'], run.call_args.args[0][-2:])
        self.assertNotIn('mirror-token', ' '.join(run.call_args.args[0]))

    def test_bot_entry_point_completes_an_existing_tag_and_dry_run_never_publishes(self):
        mirror = self.api.commits[0]['sha']
        self.api.tags[(APPLICATION, self.tag)] = {'type': 'commit', 'sha': mirror}
        arguments = ['application.py', '--framework-tag', self.tag, '--application-tag', self.tag]
        with patch.dict(os.environ, {'GH_TOKEN': 'test-token'}), \
             patch('application.GitHub', return_value=self.api), \
             patch('sys.argv', arguments), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(0, application.main())
        self.assertEqual([], self.api.release_commands)
        with patch.dict(os.environ, {'GH_TOKEN': 'test-token'}), \
             patch('application.GitHub', return_value=self.api), \
             patch('sys.argv', arguments + ['--publish']), contextlib.redirect_stdout(io.StringIO()):
            self.assertEqual(0, application.main())
        self.assertTrue(complete_github_release(self.api.releases[(APPLICATION, self.tag)], self.tag))
        self.assertEqual([], self.api.writes)


if __name__ == '__main__':
    unittest.main()
