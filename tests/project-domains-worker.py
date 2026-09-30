import importlib.util
import json
import pathlib
import sqlite3
import subprocess
import sys
import tempfile

script = pathlib.Path(__file__).resolve().parents[1] / 'bin/project-domains-sync.py'
spec = importlib.util.spec_from_file_location('domain_worker', script)
worker = importlib.util.module_from_spec(spec)
spec.loader.exec_module(worker)

config, hosts = worker.configuration([{'id': 1, 'hostname': 'shop.example.com'}], 'backend@file', set())
assert hosts == ['shop.example.com']
assert config['http']['routers']['tmpbase-domain-1-https']['tls']['certResolver'] == 'letsencrypt'
for host in ['evil.com`) || Host(`api.example.com', 'api.example.com', '*.example.com']:
    try:
        worker.configuration([{'id': 1, 'hostname': host}], 'backend@file', {'api.example.com'})
    except ValueError:
        pass
    else:
        raise AssertionError('Unsafe domain accepted')

with tempfile.TemporaryDirectory() as folder:
    root = pathlib.Path(folder)
    db = sqlite3.connect(root / 'db.sqlite')
    db.executescript("""CREATE TABLE projects(uid TEXT,status TEXT);
    CREATE TABLE project_domains(id INTEGER,project_uid TEXT,hostname TEXT,status TEXT,verified_at TEXT);
    INSERT INTO projects VALUES('one','active'),('two','blocked');
    INSERT INTO project_domains VALUES
      (1,'one','shop.example.com','active','2026-09-30'),
      (2,'one','pending.example.com','pending',NULL),
      (3,'two','blocked.example.com','active','2026-09-30');""")
    db.commit()
    command = [sys.executable, str(script), '--database', str(root / 'db.sqlite'), '--output', str(root / 'routing.yml'), '--status', str(root / 'status.json'), '--service', 'backend@file']
    subprocess.run(command, check=True)
    status = json.loads((root / 'status.json').read_text())
    assert status['hosts'] == ['shop.example.com']
    stamp = (root / 'routing.yml').stat().st_mtime_ns
    subprocess.run(command, check=True)
    assert (root / 'routing.yml').stat().st_mtime_ns == stamp, 'No reload for unchanged config'
    db.execute("UPDATE project_domains SET status='disabled' WHERE id=1")
    db.commit()
    subprocess.run(command, check=True)
    assert json.loads((root / 'routing.yml').read_text())['http']['routers'] == {}
    db.close()
print('PASS: proxy rules, injection prevention, blocked projects, removal and idempotence')
