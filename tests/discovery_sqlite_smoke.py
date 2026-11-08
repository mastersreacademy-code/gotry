"""Run: python3 tests/discovery_sqlite_smoke.py
Exercises the ACTUAL public discovery SQL extracted from src/api.js against
all repository migrations, including multiple sessions and expired sessions.
"""
from pathlib import Path
import re
import sqlite3

root=Path(__file__).resolve().parents[1]
source=(root/'src/api.js').read_text(encoding='utf-8')
match=re.search(r'if\(path===\'/api/activities\'&&method===\'GET\'\)\{\s*const rows=await db\.prepare\("([^"]+)"\)\.bind\(',source)
assert match, 'Cannot locate public discovery SQL'
sql=match.group(1)
db=sqlite3.connect(':memory:')
db.execute('PRAGMA foreign_keys=ON')
for migration in sorted((root/'migrations').glob('*.sql')):
    db.executescript(migration.read_text(encoding='utf-8'))
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('provider','provider@example.test','x','provider')")
db.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','provider','Trial','pending_registrations','{}')")
for sid,capacity,start in [('open',2,'2098-12-31T23:00:00.000Z'),('expired',5,'2000-01-01T00:00:00.000Z')]:
    db.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES(?,'activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',1,?,?,?)",(sid,capacity,start,start))
rows=db.execute(sql,('2026-11-08T00:00:00.000Z','2026-11-08T00:00:00.000Z')).fetchall()
assert len(rows)==1
columns=[d[0] for d in db.execute(sql,('2026-11-08T00:00:00.000Z','2026-11-08T00:00:00.000Z')).description]
record=dict(zip(columns,rows[0]))
assert record['session_count']==2,record
assert record['available_sessions']==1,record
assert record['session_remaining']==2,record
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('parent','parent@example.test','x','parent')")
db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('interest','activity','parent','Child',8,'open')")
row=db.execute(sql,('2026-11-08T00:00:00.000Z','2026-11-08T00:00:00.000Z')).fetchone()
assert dict(zip(columns,row))['session_remaining']==1
print('PASS: migrations and actual public SQL exclude expired slots and count open capacity')
