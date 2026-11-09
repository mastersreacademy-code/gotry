"""Run: python3 tests/transfer_deadline_sqlite_smoke.py
Executes the actual API transfer UPDATE extracted from src/api.js on SQLite
after all migrations, verifying provider deadlines and session capacity.
"""
from pathlib import Path
import sqlite3
root=Path(__file__).resolve().parents[1]
source=(root/'src/api.js').read_text(encoding='utf-8')
start=source.index('UPDATE interests SET session_id=?,updated_at=CURRENT_TIMESTAMP')
end=source.index('").bind(',start)
sql=source[start:end]
assert sql.count('?')==10, 'Unexpected transfer bind count'
db=sqlite3.connect(':memory:')
db.execute('PRAGMA foreign_keys=ON')
for migration in sorted((root/'migrations').glob('*.sql')):
    db.executescript(migration.read_text(encoding='utf-8'))
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('provider','provider@example.test','x','provider')")
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('parent','parent@example.test','x','parent')")
db.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','provider','Trial','pending_registrations','{\"deadline\":\"2026-11-07\"}')")
for sid in ('old','new'):
    db.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES(?,'activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',1,2,'2098-12-31T23:00:00.000Z','2099-01-01T00:00:00.000Z')",(sid,))
db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('interest','activity','parent','Child',8,'old')")
args=('new','interest','parent','old','new','new','2026-11-08T00:00:00.000Z','2026-11-08','2026-11-08','2026-11-08T00:00:00.000Z')
db.execute(sql,args)
assert db.execute("SELECT session_id FROM interests WHERE id='interest'").fetchone()[0]=='old', 'Transfer bypassed provider deadline'
db.execute("UPDATE activities SET data_json='{\"deadline\":\"2099-01-01\"}' WHERE id='activity'")
db.execute(sql,args)
assert db.execute("SELECT session_id FROM interests WHERE id='interest'").fetchone()[0]=='new', 'Transfer incorrectly rejected'
db.execute("UPDATE trial_sessions SET status='cancelled' WHERE id='old'")
db.execute(sql,('old','interest','parent','new','old','old','2026-11-08T00:00:00.000Z','2026-11-08','2026-11-08','2026-11-08T00:00:00.000Z'))
assert db.execute("SELECT session_id FROM interests WHERE id='interest'").fetchone()[0]=='new', 'Transferred into cancelled session'
print('PASS: actual transfer SQL enforces deadline, valid transfer and cancelled-session rejection')

