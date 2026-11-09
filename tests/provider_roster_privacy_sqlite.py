"""Run: python3 tests/provider_roster_privacy_sqlite.py
Extracts the production roster SQL and executes it after migrations 0001–0009.
No real child data or provider credentials are used.
"""
from pathlib import Path
import sqlite3
root=Path(__file__).resolve().parents[1]
source=(root/'src/api.js').read_text(encoding='utf-8')
prefix="SELECT CASE WHEN s.status='confirmed' AND a.status NOT LIKE 'cancelled%' THEN i.child_name ELSE NULL END AS child_name"
start=source.index(prefix)
end=source.index('").bind(',start)
sql=source[start:end]
db=sqlite3.connect(':memory:')
db.execute('PRAGMA foreign_keys=ON')
for migration in sorted((root/'migrations').glob('*.sql')):
    db.executescript(migration.read_text(encoding='utf-8'))
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('provider','provider@example.test','x','provider')")
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('parent','parent@example.test','x','parent')")
db.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','provider','Trial','pending_registrations','{}')")
db.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES('slot','activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',1,2,'2098-12-31T23:00:00.000Z','2099-01-01T00:00:00.000Z')")
db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('interest','activity','parent','Test Child',8,'slot')")
for state in ('open','cancelled'):
    db.execute('UPDATE trial_sessions SET status=? WHERE id=?',(state,'slot'))
    row=db.execute(sql,('slot','provider')).fetchone()
    assert row[0] is None, f'Child name leaked in {state} roster'
db.execute("UPDATE trial_sessions SET status='confirmed' WHERE id='slot'")
assert db.execute(sql,('slot','provider')).fetchone()[0]=='Test Child'
db.execute("UPDATE activities SET status='cancelled_by_provider' WHERE id='activity'")
assert db.execute(sql,('slot','provider')).fetchone()[0] is None
assert db.execute(sql,('slot','intruder')).fetchone() is None
print('PASS: all migrations and provider roster child-name minimization')
