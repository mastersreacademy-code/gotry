"""Run: python3 tests/multi_session_sqlite_smoke.py
Executes every migration and validates multiple interests, uniqueness and atomic outbox triggers.
SQLite compatibility test; not a substitute for Wrangler D1 browser E2E.
"""
from pathlib import Path
import sqlite3
root=Path(__file__).resolve().parents[1]
db=sqlite3.connect(":memory:")
db.execute("PRAGMA foreign_keys=ON")
for path in sorted((root/"migrations").glob("*.sql")):
    db.executescript(path.read_text())
for uid,role in [('provider','provider'),('parent','parent'),('other','parent')]:
    db.execute("INSERT INTO users(id,email,password_hash,role) VALUES(?,?,?,?)",(uid,uid+'@example.test','x',role))
db.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','provider','Trial','pending_registrations','{}')")
for n in ('one','two','three'):
    db.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES(?, 'activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',1,2,'2098-12-31T23:00:00.000Z','2099-01-01T00:00:00.000Z')",(n,))
for n,child in [('one','Child'),('two','Another child')]:
    db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES(?, 'activity','parent',?,8,?)",(n,child,n))
assert db.execute("SELECT COUNT(*) FROM interests WHERE parent_id='parent'").fetchone()[0]==2
try:
    db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('dup_other_session','activity','parent',' child ',8,'three')")
    raise AssertionError('Duplicate child across sessions accepted')
except sqlite3.IntegrityError:
    pass
try:
    db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('dup','activity','parent','Child',8,'one')")
    raise AssertionError('Duplicate same-session registration accepted')
except sqlite3.IntegrityError:
    pass
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]==4
db.execute("UPDATE interests SET status='withdrawn' WHERE id='one'")
db.execute("UPDATE interests SET session_id='three' WHERE id='two'")
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]==8
assert db.execute("SELECT session_id FROM interests WHERE id='two'").fetchone()[0]=='three'
db.execute("CREATE TRIGGER fail_outbox BEFORE INSERT ON session_notification_outbox BEGIN SELECT RAISE(ABORT,'failed'); END")
try:
    db.execute("UPDATE interests SET status='withdrawn' WHERE id='two'")
    raise AssertionError('Failed outbox did not roll back interest change')
except sqlite3.IntegrityError:
    pass
assert db.execute("SELECT status FROM interests WHERE id='two'").fetchone()[0]=='interested'
print("PASS: all migrations, different children across sessions, active-child uniqueness, outbox and rollback")
