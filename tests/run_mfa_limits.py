#!/usr/bin/env python3
"""Run MFA integration tests against disposable local MySQL and Redis only."""
import pathlib
import socket
import subprocess
import tempfile
import time

repo = pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='cc-mfa-') as directory:
    root = pathlib.Path(directory)
    (root / 'isolated-mfa-test').touch()
    subprocess.run(['mysqld', '--no-defaults', '--initialize-insecure',
                    f'--datadir={root / "mysql"}', f'--log-error={root / "init.log"}'], check=True)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    processes = []
    try:
        with (root / 'servers.log').open('w') as log:
            processes.append(subprocess.Popen([
                'mysqld', '--no-defaults', f'--datadir={root / "mysql"}', '--skip-networking',
                '--mysqlx=OFF', f'--socket={root / "mysql.sock"}', f'--pid-file={root / "mysql.pid"}',
                f'--log-error={root / "mysql.log"}'], stdout=log, stderr=log))
            processes.append(subprocess.Popen([
                'redis-server', '--bind', '127.0.0.1', '--port', str(port), '--save', '',
                '--appendonly', 'no', '--dir', str(root)], stdout=log, stderr=log))
            for _ in range(200):
                if any(p.poll() is not None for p in processes):
                    raise RuntimeError('Disposable server exited; inspect temporary logs')
                if (root / 'mysql.sock').exists():
                    try:
                        with socket.create_connection(('127.0.0.1', port), timeout=.1):
                            break
                    except OSError:
                        pass
                time.sleep(.1)
            else:
                raise RuntimeError('Disposable server startup timed out')
            subprocess.run(['php', str(repo / 'tests/mfa_attempt_limits.php'), str(root), str(port)],
                           cwd=repo, check=True)
    finally:
        for process in processes:
            if process.poll() is None:
                process.terminate()
        for process in processes:
            try:
                process.wait(timeout=15)
            except subprocess.TimeoutExpired:
                process.kill()
                process.wait()
