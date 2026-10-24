"""Isolated Windows process-manager tests; no database or real mail delivery."""
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch

SOURCE = Path(__file__).resolve().parents[1] / 'local-recovery.py'
spec = importlib.util.spec_from_file_location('local_recovery', SOURCE)
manager = importlib.util.module_from_spec(spec)
spec.loader.exec_module(manager)

class ManagerTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix='foursmart-worker-manager-test-')
        self.base = Path(self.directory.name)
        self.root = self.base / 'repo'
        (self.root / 'mvc').mkdir(parents=True)
        self.worker = self.base / 'runtime/worker'
        self.worker.mkdir(parents=True)
        self.patchers = [patch.object(manager, 'ROOT', self.root), patch.object(manager, 'RUNTIME', self.worker.parent),
            patch.object(manager, 'WORKER', self.worker), patch.object(manager, 'STATE', self.worker / 'process.json'),
            patch.object(manager, 'SETTINGS', self.worker.parent / 'worker.local.json')]
        for item in self.patchers: item.start()
        (self.root / 'tools').mkdir()
        manager.write_json(self.root / 'tools/local-tools.local.json', {'document_root': str(self.root)})
        text = (SOURCE.parents[1] / 'mvc/.htaccess.example').read_text(encoding='utf-8')
        (self.root / 'mvc/.htaccess').write_text(text, encoding='utf-8')
        self.php = self.base / 'php.exe'
        self.php.touch()
        self.database = self.base / 'database.local.json'
        self.database.write_text('{}')
        manager.write_json(manager.SETTINGS, {'php': str(self.php), 'database_config': str(self.database), 'poll_seconds': 2})

    def tearDown(self):
        for item in reversed(self.patchers): item.stop()
        self.directory.cleanup()

    def test_cli_environment_drops_inherited_secrets_and_matches_apache_settings(self):
        with patch.dict(os.environ, {'FOURSMART_RECOVERY_HMAC_KEY': 'must-not-be-inherited', 'FOURSMART_SMTP_HOST': 'smtp.invalid'}):
            config = manager.deployment()
        self.assertNotIn('FOURSMART_RECOVERY_HMAC_KEY', config['env'])
        self.assertEqual(config['env']['FOURSMART_SMTP_HOST'], '127.0.0.1')
        self.assertEqual(config['env']['FOURSMART_DATABASE_CONFIG'], str(self.database))

    def test_external_smtp_is_rejected_before_worker_start(self):
        path = self.root / 'mvc/.htaccess'
        path.write_text(path.read_text().replace('SetEnv FOURSMART_SMTP_HOST 127.0.0.1', 'SetEnv FOURSMART_SMTP_HOST smtp.invalid'))
        with self.assertRaises(manager.LocalError): manager.deployment()

    def test_clean_checkout_template_is_off_and_requires_versioned_sessions(self):
        config = manager.deployment()
        self.assertFalse(config['enabled'])
        self.assertEqual(config['env']['FOURSMART_SESSION_VERSION_MODE'], 'required')
        self.assertEqual(config['env']['FOURSMART_RECOVERY_LOCAL_ONLY'], '1')

    def test_machine_runtime_can_be_configured_without_shared_source_paths(self):
        (self.root / 'tools').mkdir(exist_ok=True)
        private = self.base / 'outside-web/private-runtime'
        manager.write_json(self.root / 'tools/local-tools.local.json', {'recovery_runtime': str(private), 'document_root': str(self.root)})
        with patch.dict(os.environ, {}, clear=True):
            self.assertEqual(manager.runtime_location(self.root), private)

    def test_duplicate_deployment_flags_are_rejected(self):
        path = self.root / 'mvc/.htaccess'
        path.write_text(path.read_text() + '\nSetEnv FOURSMART_SESSION_VERSION_MODE legacy\n')
        with self.assertRaises(manager.LocalError): manager.deployment()

    def test_private_database_configuration_inside_source_is_rejected(self):
        inside = self.root / 'db.local.json'; inside.write_text('{}')
        manager.write_json(manager.SETTINGS, {'php': str(self.php), 'database_config': str(inside)})
        with self.assertRaises(manager.LocalError): manager.deployment()

    def test_native_lock_prevents_a_second_process_and_releases_cleanly(self):
        code = ('import importlib.util,pathlib; s=importlib.util.spec_from_file_location("m",' + repr(str(SOURCE))
            + '); m=importlib.util.module_from_spec(s); s.loader.exec_module(m); m.WORKER=pathlib.Path(' + repr(str(self.worker))
            + ');\ntry:\n with m.WorkerLock(): pass\nexcept m.LocalError: raise SystemExit(7)\n')
        with manager.WorkerLock():
            result = subprocess.run([sys.executable, '-c', code], capture_output=True, creationflags=subprocess.CREATE_NO_WINDOW)
            self.assertEqual(result.returncode, 7)
        with manager.WorkerLock(): pass

    def test_pid_reuse_is_detected_from_process_creation_time(self):
        identity = manager.process_identity(os.getpid())
        identity['created'] -= 1
        manager.write_json(manager.STATE, {'identity': identity, 'run_id': 'a' * 24})
        self.assertIsNone(manager.active_state())
        self.assertTrue(manager.stop()['already_stopped'])

    def test_duplicate_start_returns_the_existing_manager(self):
        state = {'identity': manager.process_identity(os.getpid()), 'run_id': 'b' * 24, 'mode': 'loop', 'phase': 'running'}
        manager.write_json(manager.STATE, state)
        with patch.object(manager.subprocess, 'Popen') as spawn:
            self.assertTrue(manager.start()['already_running'])
            spawn.assert_not_called()

    def test_emergency_disable_works_when_private_settings_are_missing(self):
        manager.SETTINGS.unlink()
        with patch.object(manager, 'stop', return_value={'already_stopped': True}):
            self.assertFalse(manager.set_enabled(False)['recovery_enabled'])
        text = (self.root / 'mvc/.htaccess').read_text()
        self.assertIn('SetEnv FOURSMART_PASSWORD_RECOVERY_ENABLED 0', text)
        self.assertIn('SetEnv FOURSMART_SESSION_VERSION_MODE required', text)

    def test_stop_request_cannot_escape_runtime_directory(self):
        with self.assertRaises(manager.LocalError): manager.stop_path({'run_id': '../unexpected'})

    def test_unexpected_worker_diagnostics_never_reach_state_or_event_log(self):
        class FakeProcess:
            pid = 123
            returncode = 0
            def communicate(self): return b'private-reset-url-and-token\n', b'private-password-and-key'
        state = {'identity': manager.process_identity(os.getpid()), 'run_id': 'c' * 24}
        with patch.object(manager.subprocess, 'Popen', return_value=FakeProcess()):
            self.assertFalse(manager.execute_once({'php': 'unused', 'env': {}}, state))
        text = manager.STATE.read_text() + (self.worker / 'events.log').read_text()
        self.assertIn('WORKER_UNAVAILABLE', text)
        self.assertNotIn('private-reset', text)
        self.assertNotIn('private-password', text)

if __name__ == '__main__':
    unittest.main(verbosity=2)
