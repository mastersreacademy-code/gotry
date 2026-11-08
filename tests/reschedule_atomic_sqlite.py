"""Run: python3 tests/reschedule_atomic_sqlite.py

Load migrations 0001–0009 and execute the production reschedule UPDATE
extracted directly from src/api.js. Verify UTC boundary and ownership.
"""
from pathlib import Path
import sqlite3

root=Path(__file__).resolve().parents[1]
source=(root/'src/api.js').read_text(encoding='utf-8')
start=source.index('UPDATE trial_sessions SET starts_at=?,ends_at=?,starts_at_utc=?,ends_at_utc=?,venue=?,updated_at=CURRENT_TIMESTAMP')
end=source.index('").bind(',start)
sql=source[start:end]
assert sql.count('?')==8, 'Unexpected SQL placeholder count'
db=sqlite3.connect(':memory:')
db.execute('PRAGMA foreign_keys=ON')
for migration in sorted((root/'migrations').glob('*.sql')):
    db.executescript(migration.read_text(encoding='utf-8'))
for uid in ('owner','other'):
    db.execute('INSERT INTO users(id,email,password_hash,role) VALUES(?,?,?,?)',(uid,uid+'@example.test','x','provider'))
db.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','owner','Trial','pending_registrations','{}')")
db.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES('slot','activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',1,2,'2098-12-31T23:00:00.000Z','2099-01-01T00:00:00.000Z')")
def reschedule(actor):
    return db.execute(sql,('2099-01-02T10:00','2099-01-02T11:00','2099-01-01T23:00:00.000Z','2099-01-02T00:00:00.000Z','New venue','slot','2026-11-08T00:00:00.000Z',actor)).rowcount
assert reschedule('other')==0, 'Non-owner rescheduled the session'
assert reschedule('owner')==1, 'Owner cannot reschedule future open session'
assert db.execute("SELECT venue FROM trial_sessions WHERE id='slot'").fetchone()[0]=='New venue'
db.execute("UPDATE trial_sessions SET starts_at_utc='2000-01-01T00:00:00.000Z' WHERE id='slot'")
assert reschedule('owner')==0, 'Already-started session rescheduled'
db.execute("UPDATE trial_sessions SET starts_at_utc='2098-12-31T23:00:00.000Z',status='cancelled' WHERE id='slot'")
assert reschedule('owner')==0, 'Cancelled session rescheduled'
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]>=1
print('PASS: migrations, owner-only future reschedule, expired/cancelled guards, notification outbox')
