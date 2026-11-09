"""SQLite regression: execute API SQL against migrations 0001-0009.

Run: python3 tests/session_atomic_state_sqlite.py
Uses real SQL extracted from src/api.js, not a hand-written substitute.
This does NOT replace Wrangler D1 or Playwright E2E.
"""
from pathlib import Path
import sqlite3

root = Path(__file__).resolve().parents[1]
source = (root / 'src/api.js').read_text(encoding='utf-8')

def statement(prefix):
    start = source.index(prefix)
    end = source.index('").bind(', start)
    return source[start:end]

db = sqlite3.connect(':memory:')
db.execute('PRAGMA foreign_keys=ON')
for migration in sorted((root / 'migrations').glob('*.sql')):
    db.executescript(migration.read_text(encoding='utf-8'))
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('provider','provider@example.test','x','provider')")
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('parent','parent@example.test','x','parent')")
db.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','provider','Trial','pending_registrations','{\"deadline\":\"2026-11-07\"}')")
for sid, starts in [('old','2098-12-31T23:00:00.000Z'),('new','2098-12-31T23:00:00.000Z'),('past','2098-12-31T23:00:00.000Z')]:
    db.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES(?,'activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',1,2,?,?)",(sid,starts,starts))

insert = statement('INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) SELECT')
assert insert.count('?') == 18, 'Registration SQL bind count changed'
args = ('interest','activity','parent','Child',8,'old','activity','old','old',2,'activity','2026-11-08','old','activity','old','old','activity','2026-11-08T00:00:00.000Z')
db.execute(insert,args)
assert db.execute("SELECT COUNT(*) FROM interests").fetchone()[0] == 0, 'Expired provider deadline bypassed during insert'

db.execute("UPDATE activities SET data_json='{\"deadline\":\"2099-01-01\"}' WHERE id='activity'")
db.execute(insert,args)
assert db.execute("SELECT COUNT(*) FROM interests").fetchone()[0] == 1
db.execute("UPDATE interests SET status='withdrawn' WHERE id='interest'")
db.execute("UPDATE activities SET data_json='{\"deadline\":\"2026-11-07\"}' WHERE id='activity'")
restore = statement("UPDATE interests SET status='interested',child_age=")
assert restore.count('?') == 15, 'Restore SQL bind count changed'
restore_args = (8,'old','interest','activity','2026-11-08','old','activity','old','old','activity','2026-11-08T00:00:00.000Z','activity','old','old',2)
db.execute(restore,restore_args)
assert db.execute("SELECT status FROM interests WHERE id='interest'").fetchone()[0] == 'withdrawn', 'Expired provider deadline bypassed during restore'

confirm = statement('UPDATE trial_sessions SET status=?,updated_at=CURRENT_TIMESTAMP')
assert confirm.count('?') == 9, 'Admin decision SQL bind count changed'
# Admin cannot confirm an already-expired session even if minimum is met.
db.execute("UPDATE activities SET data_json='{\"deadline\":\"2099-01-01\"}' WHERE id='activity'")
db.execute("UPDATE interests SET status='interested' WHERE id='interest'")
db.execute("UPDATE interests SET session_id='past' WHERE id='interest'")
db.execute("UPDATE trial_sessions SET starts_at_utc='2000-01-01T00:00:00.000Z' WHERE id='past'")
decision_args = ('confirmed','past','2099-01-01T10:00','2099-01-01T11:00','Sydney','confirmed','2026-11-08T00:00:00.000Z','confirmed','2026-11-08T00:00:00.000Z')
db.execute(confirm,decision_args)
assert db.execute("SELECT status FROM trial_sessions WHERE id='past'").fetchone()[0] == 'open'
db.execute("UPDATE interests SET session_id='old' WHERE id='interest'")
decision_args = ('confirmed','old','2099-01-01T10:00','2099-01-01T11:00','Sydney','confirmed','2026-11-08T00:00:00.000Z','confirmed','2026-11-08T00:00:00.000Z')
db.execute(confirm,decision_args)
assert db.execute("SELECT status FROM trial_sessions WHERE id='old'").fetchone()[0] == 'confirmed'
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0] >= 2
print('PASS: all migrations, atomic insert/restore deadlines, admin UTC confirmation guard and outbox')

