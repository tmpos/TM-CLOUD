#!/usr/bin/env python3
"""Host-side reconciler. No Docker socket or proxy configuration access in PHP."""
import argparse
import fcntl
import json
import os
import re
import sqlite3
import tempfile
import time
from pathlib import Path

HOST = re.compile(r"(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\Z")


def configuration(rows, service, reserved):
    routers = {}
    hosts = []
    for row in rows:
        host = row['hostname']
        if len(host) > 253 or not HOST.fullmatch(host) or host in reserved:
            raise ValueError('Invalid or reserved domain in routing configuration')
        key = 'tmpbase-domain-' + str(int(row['id']))
        routers[key + '-http'] = {
            'rule': 'Host(`' + host + '`)', 'entryPoints': ['web'],
            'service': service, 'middlewares': ['redirect-to-https@file'],
        }
        routers[key + '-https'] = {
            'rule': 'Host(`' + host + '`)', 'entryPoints': ['websecure'],
            'service': service, 'tls': {'certResolver': 'letsencrypt'},
        }
        hosts.append(host)
    return {'http': {'routers': routers}}, hosts


def atomic_json(target, data, only_changed=False):
    encoded = json.dumps(data, ensure_ascii=True, indent=2) + '\n'
    if only_changed and target.exists() and target.read_text() == encoded:
        return
    fd, temporary = tempfile.mkstemp(prefix='.tmpbase-domains-', dir=target.parent)
    try:
        with os.fdopen(fd, 'w') as output:
            output.write(encoded)
            output.flush()
            os.fsync(output.fileno())
        os.chmod(temporary, 0o644)
        os.replace(temporary, target)
    finally:
        if os.path.exists(temporary):
            os.unlink(temporary)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--database', required=True)
    parser.add_argument('--output', required=True)
    parser.add_argument('--status', required=True)
    parser.add_argument('--service', required=True)
    parser.add_argument('--reserved', default='api.tmposystem.com,realtime.tmposystem.com')
    args = parser.parse_args()
    output, status = Path(args.output), Path(args.status)
    # The lock prevents overlapping timer/manual runs from publishing stale snapshots.
    with open(str(output) + '.lock', 'w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX)
        db = sqlite3.connect(Path(args.database).resolve().as_uri() + '?mode=ro', uri=True, timeout=10)
        db.row_factory = sqlite3.Row
        try:
            rows = db.execute("""SELECT d.id,d.hostname FROM project_domains d
                JOIN projects p ON p.uid=d.project_uid
                WHERE d.status='active' AND d.verified_at IS NOT NULL AND p.status='active'
                ORDER BY d.id""").fetchall()
        finally:
            db.close()
        config, hosts = configuration(rows, args.service, set(args.reserved.split(',')))
        atomic_json(output, config, only_changed=True)
        atomic_json(status, {'updated_at': int(time.time()), 'hosts': hosts})
        print('Project domain routes synchronized:', len(hosts))


if __name__ == '__main__':
    main()
