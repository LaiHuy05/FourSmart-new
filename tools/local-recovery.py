"""Local process manager for the existing PHP worker. No service or scheduler."""
import ctypes
from ctypes import wintypes
import datetime
import json
import msvcrt
import os
from pathlib import Path
import re
import secrets
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[1]
ALLOWED_RESULTS = {'idle', 'sent', 'discarded', 'retry_or_failed', 'lease_lost'}
ENV_NAMES = {'FOURSMART_PASSWORD_RECOVERY_ENABLED', 'FOURSMART_SESSION_VERSION_MODE',
    'FOURSMART_RECOVERY_LOCAL_ONLY', 'FOURSMART_RECOVERY_HMAC_KEY_FILE', 'FOURSMART_APP_URL',
    'FOURSMART_MAIL_TRANSPORT', 'FOURSMART_SMTP_HOST', 'FOURSMART_SMTP_PORT', 'FOURSMART_SMTP_TIMEOUT',
    'FOURSMART_MAIL_FROM', 'FOURSMART_MAIL_FROM_NAME', 'FOURSMART_MAIL_REPLY_TO'}

class LocalError(Exception):
    pass

def require(ok, message):
    if not ok:
        raise LocalError(message)

def now():
    return datetime.datetime.now(datetime.timezone.utc).isoformat()

def read_json(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))

def local_tools(root):
    path = root / 'tools/local-tools.local.json'
    return read_json(path) if path.is_file() else {}

def runtime_location(root):
    configured = os.environ.get('FOURSMART_RECOVERY_RUNTIME') or local_tools(root).get('recovery_runtime')
    if not configured:
        deployment_file = root / 'mvc/.htaccess'
        text = deployment_file.read_text(encoding='utf-8-sig') if deployment_file.is_file() else ''
        match = re.search(r'(?m)^\s*SetEnv\s+FOURSMART_RECOVERY_HMAC_KEY_FILE\s+"?([^"\r\n]+?)"?\s*$', text)
        if match and Path(match[1]).is_absolute():
            configured = str(Path(match[1]).parent)
    if configured:
        result = Path(configured)
    else:
        result = Path(os.environ.get('LOCALAPPDATA') or Path.home() / '.local/share') / 'FourSmart/recovery-local'
    require(result.is_absolute(), 'Recovery runtime must be an absolute private path.')
    document_root = Path(local_tools(root).get('document_root') or root.parent).resolve()
    require(not result.resolve().is_relative_to(document_root), 'Recovery runtime must stay outside web roots.')
    return result

RUNTIME = runtime_location(ROOT)
WORKER = RUNTIME / 'worker'
SETTINGS = RUNTIME / 'worker.local.json'
STATE = WORKER / 'process.json'

def write_json(path, value):
    temporary = path.with_name(path.name + '.' + secrets.token_hex(4) + '.tmp')
    temporary.write_text(json.dumps(value, indent=2), encoding='utf-8')
    os.replace(temporary, path)

def replace_deployment(path, text):
    # Atomic replacement keeps the required session policy present on every request.
    temporary = path.with_name(path.name + '.' + secrets.token_hex(6) + '.tmp')
    try:
        temporary.write_text(text, encoding='utf-8')
        deadline = time.monotonic() + 2
        while True:
            try:
                os.replace(temporary, path)
                return
            except PermissionError:
                if time.monotonic() >= deadline:
                    raise LocalError('Apache configuration is busy; no partial configuration was written.')
                time.sleep(.05)
    finally:
        temporary.unlink(missing_ok=True)

def process_identity(pid):
    kernel = ctypes.WinDLL('kernel32', use_last_error=True)
    kernel.OpenProcess.argtypes = [wintypes.DWORD, wintypes.BOOL, wintypes.DWORD]
    kernel.OpenProcess.restype = wintypes.HANDLE
    kernel.CloseHandle.argtypes = [wintypes.HANDLE]
    kernel.GetProcessTimes.argtypes = [wintypes.HANDLE] + [ctypes.POINTER(wintypes.FILETIME)] * 4
    kernel.QueryFullProcessImageNameW.argtypes = [wintypes.HANDLE, wintypes.DWORD, wintypes.LPWSTR, ctypes.POINTER(wintypes.DWORD)]
    handle = kernel.OpenProcess(0x1000, False, int(pid))
    if not handle:
        if ctypes.get_last_error() == 87:
            return None
        raise LocalError('Cannot verify worker process identity.')
    try:
        created, exited, kerneltime, usertime = [wintypes.FILETIME() for _ in range(4)]
        require(kernel.GetProcessTimes(handle, ctypes.byref(created), ctypes.byref(exited), ctypes.byref(kerneltime), ctypes.byref(usertime)), 'Cannot verify worker process time.')
        if exited.dwHighDateTime or exited.dwLowDateTime:
            return None
        length = wintypes.DWORD(32768)
        name = ctypes.create_unicode_buffer(length.value)
        require(kernel.QueryFullProcessImageNameW(handle, 0, name, ctypes.byref(length)), 'Cannot verify worker executable.')
        return {'pid': int(pid), 'created': (created.dwHighDateTime << 32) | created.dwLowDateTime, 'executable': name.value.lower()}
    finally:
        kernel.CloseHandle(handle)

def active_state():
    if not STATE.exists():
        return None
    state = read_json(STATE)
    actual = process_identity(state['identity']['pid'])
    return state if actual == state['identity'] else None

class WorkerLock:
    def __enter__(self):
        WORKER.mkdir(parents=True, exist_ok=True)
        self.stream = (WORKER / 'worker.lock').open('a+b')
        self.stream.seek(0, 2)
        if self.stream.tell() == 0:
            self.stream.write(b'0')
            self.stream.flush()
        self.stream.seek(0)
        try:
            msvcrt.locking(self.stream.fileno(), msvcrt.LK_NBLCK, 1)
        except OSError:
            self.stream.close()
            raise LocalError('A managed worker is already running; use status or stop.')
        return self
    def __exit__(self, *args):
        self.stream.seek(0)
        msvcrt.locking(self.stream.fileno(), msvcrt.LK_UNLCK, 1)
        self.stream.close()

def deployment():
    values = {}
    text = (ROOT / 'mvc/.htaccess').read_text(encoding='utf-8-sig')
    for line in text.splitlines():
        match = re.fullmatch(r'\s*SetEnv\s+(FOURSMART_[A-Z_]+)\s+(?:"([^"\r\n]*)"|([^\s#]+))\s*', line)
        if match and match[1] in ENV_NAMES:
            require(match[1] not in values, 'Duplicate local deployment setting.')
            values[match[1]] = match[2] if match[2] is not None else match[3]
    require(values.get('FOURSMART_SESSION_VERSION_MODE') == 'required', 'Session policy must remain required.')
    require(values.get('FOURSMART_RECOVERY_LOCAL_ONLY') == '1', 'Recovery HTTP must remain loopback only.')
    require(values.get('FOURSMART_PASSWORD_RECOVERY_ENABLED') in ['0', '1'], 'Recovery flag is missing or invalid.')
    require(values.get('FOURSMART_SMTP_HOST') == '127.0.0.1' and values.get('FOURSMART_SMTP_PORT') == '1025', 'Only local Mailpit SMTP is approved.')
    require(values.get('FOURSMART_MAIL_TRANSPORT') == 'mailpit', 'Only the Mailpit transport is approved.')
    settings = read_json(SETTINGS)
    php = Path(settings['php'])
    database = Path(settings['database_config']).resolve()
    require(php.is_file() and database.is_file(), 'PHP or private database configuration is missing.')
    document_root = Path(local_tools(ROOT).get('document_root') or ROOT.parent).resolve()
    require(not database.is_relative_to(ROOT) and not database.is_relative_to(document_root), 'Private database configuration must stay outside web roots.')
    interval = settings.get('poll_seconds', 2)
    require(isinstance(interval, int) and 1 <= interval <= 10, 'Worker poll interval must be 1 to 10 seconds.')
    env = {name: value for name, value in os.environ.items() if not name.startswith('FOURSMART_')}
    env.update(values)
    env['FOURSMART_DATABASE_CONFIG'] = str(database)
    return {'php': str(php), 'env': env, 'enabled': values['FOURSMART_PASSWORD_RECOVERY_ENABLED'] == '1', 'interval': interval}

def invoke_php(config, name):
    return subprocess.run([config['php'], str(ROOT / 'mvc/cli' / name)], cwd=ROOT, env=config['env'],
        capture_output=True, creationflags=subprocess.CREATE_NO_WINDOW, timeout=30)

def check(config):
    result = invoke_php(config, 'password_recovery_status.php')
    require(result.returncode == 0 and not result.stderr, 'Local recovery preflight failed; check dependencies and private configuration.')
    info = json.loads(result.stdout)
    require(info['session_required'] and info['local_only'] and info['hmac_bytes'] >= 32, 'Local recovery safety configuration is invalid.')
    return info

def event(code, **fields):
    # Callers supply allowlisted codes/counts only, never subprocess diagnostics.
    with (WORKER / 'events.log').open('a', encoding='utf-8') as stream:
        stream.write(json.dumps({'utc': now(), 'event': code, **fields}) + '\n')

def stop_path(state):
    require(re.fullmatch(r'[a-f0-9]{24}', state['run_id']) is not None, 'Invalid worker state.')
    return WORKER / ('stop-' + state['run_id'] + '.request')

def execute_once(config, state):
    process = subprocess.Popen([config['php'], str(ROOT / 'mvc/cli/password_recovery_worker.php'), '--once'],
        cwd=ROOT, env=config['env'], stdout=subprocess.PIPE, stderr=subprocess.PIPE, creationflags=subprocess.CREATE_NO_WINDOW)
    state.update(child_pid=process.pid, last_poll_utc=now())
    write_json(STATE, state)
    # STOP never kills a PHP process during a transaction/SMTP send.
    stdout, stderr = process.communicate()
    state['child_pid'] = None
    lines = stdout.decode('utf-8', errors='replace').splitlines()
    ok = process.returncode == 0 and not stderr and len(lines) == 1 and lines[0] in ALLOWED_RESULTS
    outcome = lines[0] if ok else 'WORKER_UNAVAILABLE'
    state.update(last_result=outcome, last_poll_utc=now())
    write_json(STATE, state)
    if outcome != 'idle':
        event(outcome if ok else 'WORKER_UNAVAILABLE')
    return ok

def supervise(run_id, once=False):
    with WorkerLock():
        config = deployment()
        require(config['enabled'], 'Recovery is OFF; enable it before starting the worker.')
        check(config)
        state = {'identity': process_identity(os.getpid()), 'run_id': run_id, 'mode': 'once' if once else 'loop',
            'phase': 'running', 'child_pid': None, 'last_result': None, 'started_utc': now(), 'last_poll_utc': None}
        write_json(STATE, state)
        event('STARTED', mode=state['mode'])
        failures = 0
        try:
            while not stop_path(state).exists():
                config = deployment()
                if not config['enabled']:
                    state['last_result'] = 'RECOVERY_OFF'
                    break
                ok = execute_once(config, state)
                failures = 0 if ok else failures + 1
                if once or failures >= 3:
                    if not ok:
                        state['phase'] = 'stopped_error'
                    break
                deadline = time.monotonic() + config['interval']
                while time.monotonic() < deadline and not stop_path(state).exists():
                    time.sleep(.1)
        except Exception:
            state['phase'] = 'stopped_error'
            state['last_result'] = 'MANAGER_UNAVAILABLE'
            event('MANAGER_UNAVAILABLE')
        finally:
            state['phase'] = 'stopped_error' if state['phase'] == 'stopped_error' else 'stopped'
            state['stopped_utc'] = now()
            write_json(STATE, state)
            stop_path(state).unlink(missing_ok=True)
            event('STOPPED', error=state['phase'] == 'stopped_error')
        if once:
            print(json.dumps({'result': state['last_result'], 'phase': state['phase']}))
        return 1 if state['phase'] == 'stopped_error' else 0

def status():
    state = active_state()
    saved = read_json(STATE) if STATE.exists() else {}
    return {'running': state is not None, 'pid': state['identity']['pid'] if state else None,
        'child_pid': state.get('child_pid') if state else None, 'mode': (state or saved).get('mode'),
        'phase': (state or saved).get('phase', 'stopped'), 'last_result': (state or saved).get('last_result'),
        'last_poll_utc': (state or saved).get('last_poll_utc')}

def start():
    existing = active_state()
    if existing:
        return {**status(), 'already_running': True}
    config = deployment()
    require(config['enabled'], 'Recovery is OFF; enable it before starting the worker.')
    check(config)
    WORKER.mkdir(parents=True, exist_ok=True)
    run_id = secrets.token_hex(12)
    process = subprocess.Popen([sys.executable, str(Path(__file__).resolve()), '_supervise', run_id], cwd=ROOT,
        stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
        creationflags=subprocess.CREATE_NO_WINDOW | subprocess.CREATE_NEW_PROCESS_GROUP)
    for _ in range(100):
        state = active_state()
        if state:
            return {**status(), 'already_running': state['run_id'] != run_id}
        if process.poll() is not None:
            break
        time.sleep(.1)
    raise LocalError('Worker did not start. Check status and the safe event log.')

def stop():
    state = active_state()
    if not state:
        return {**status(), 'already_stopped': True}
    stop_path(state).touch()
    for _ in range(275):
        if active_state() is None:
            return {**status(), 'clean_stop': True}
        time.sleep(.2)
    raise LocalError('Stop is pending while the current PHP invocation finishes; use status. No process was killed.')

def set_enabled(enabled):
    # Validate all private configuration before turning the HTTP feature on.
    if enabled:
        check(deployment())
    path = ROOT / 'mvc/.htaccess'
    text = path.read_text(encoding='utf-8-sig')
    value = '1' if enabled else '0'
    changed, count = re.subn(r'(?m)^(SetEnv FOURSMART_PASSWORD_RECOVERY_ENABLED )[01]\s*$', r'\g<1>' + value, text)
    require(count == 1, 'Expected exactly one Recovery flag.')
    backups = RUNTIME / 'config-backups'
    backups.mkdir(exist_ok=True)
    name = now().replace(':', '').replace('.', '-') + '-' + secrets.token_hex(3) + '.htaccess'
    (backups / name).write_text(text, encoding='utf-8')
    replace_deployment(path, changed)
    result = {'recovery_enabled': enabled, 'session_policy': 'required', 'local_only': True}
    if not enabled:
        result['worker'] = stop()
    return result

def main():
    action = sys.argv[1] if len(sys.argv) > 1 else 'status'
    if action == '_supervise':
        require(len(sys.argv) == 3 and re.fullmatch(r'[a-f0-9]{24}', sys.argv[2]), 'Invalid supervisor invocation.')
        return supervise(sys.argv[2])
    if action == 'start':
        result = start()
    elif action == 'stop':
        result = stop()
    elif action == 'status':
        result = status()
    elif action == 'queue':
        result = check(deployment())
    elif action == 'once':
        return supervise(secrets.token_hex(12), once=True)
    elif action in ['enable', 'disable']:
        result = set_enabled(action == 'enable')
    else:
        raise LocalError('Usage: local-recovery start|stop|status|once|queue|enable|disable')
    print(json.dumps(result, indent=2))
    return 0

if __name__ == '__main__':
    try:
        sys.exit(main())
    except Exception as error:
        print(str(error) if isinstance(error, LocalError) else 'Local recovery manager unavailable; check configuration and safe logs.', file=sys.stderr)
        sys.exit(1)
