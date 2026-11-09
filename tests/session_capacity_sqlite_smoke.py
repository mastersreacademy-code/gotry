"""Run with python3 tests/session_capacity_sqlite_smoke.py after migration 0009.
Uses real SQLite to validate all migrations and DB-level capacity constraints.
"""
from pathlib import Path
import sqlite3
root=Path(__file__).resolve().parents[1]
db=sqlite3.connect(':memory:')
db.execute('PRAGMA foreign_keys=ON')
for migration in sorted((root/'migrations').glob('*.sql')):
    db.executescript(migration.read_text(encoding='utf8'))
assert db.execute("SELECT COUNT(*) FROM sqlite_master WHERE type='trigger' AND name='session_parent_outbox'").fetchone()[0]==1, 'migration 0008 lost session outbox trigger'
for uid,role in [('provider','provider'),('parent','parent')]:
    db.execute('INSERT INTO users(id,email,password_hash,role) VALUES(?,?,?,?)',(uid,uid+'@example.test','x',role))
db.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','provider','Trial','pending_registrations','{}')")
for sid in ('a','b'):
    db.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES(?,'activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',1,2,'2098-12-31T23:00:00.000Z','2099-01-01T00:00:00.000Z')",(sid,))
def add(id,slot,child):
    db.execute('INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES(?,?,?,?,?,?)',(id,'activity','parent',child,8,slot))
add('one','a','Child 1')
add('two','a','Child 2')
try:
    add('three','a','Child 3')
    raise AssertionError('Overbooked session')
except sqlite3.IntegrityError:
    pass
add('four','b','Child 4')
assert db.execute("SELECT COUNT(*) FROM interests WHERE status='interested'").fetchone()[0]==3
try:
    db.execute("UPDATE interests SET session_id='a' WHERE id='four'")
    raise AssertionError('Transferred into full session')
except sqlite3.IntegrityError:
    pass
db.execute("UPDATE interests SET status='withdrawn' WHERE id='one'")
db.execute("UPDATE interests SET session_id='a' WHERE id='four'")
assert db.execute("SELECT COUNT(*) FROM interests WHERE session_id='a' AND status='interested'").fetchone()[0]==2
db.execute("UPDATE trial_sessions SET status='cancelled' WHERE id='a'")
try:
    db.execute("UPDATE interests SET status='interested' WHERE id='one'")
    raise AssertionError('Restored into cancelled session')
except sqlite3.IntegrityError:
    pass
# Outbox events are written only for actual state changes.
outbox_before=db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]
assert outbox_before>0
db.execute("UPDATE trial_sessions SET status='cancelled' WHERE id='a'")
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]==outbox_before
# Capacity is freed by withdrawal, and a cancelled session remains unavailable.
assert db.execute("SELECT COUNT(*) FROM interests WHERE session_id='a' AND status='interested'").fetchone()[0]==2
# Duplicate active child in a different session is rejected by migration 0010.
try:
    add('duplicate','b','Child 4')
    raise AssertionError('Duplicate active child accepted')
except sqlite3.IntegrityError:
    pass
print('PASS: all migrations, per-session capacity, transfer, withdrawal, cancelled-session rejection, outbox')
