"""Run with: python3 tests/sqlite_d1_smoke.py (no dependencies).
SQLite validates D1-compatible migration SQL and capacity/status constraints.
This is NOT a substitute for Wrangler D1 integration testing.
"""
from pathlib import Path
import sqlite3

root = Path(__file__).resolve().parents[1]
db = sqlite3.connect(":memory:")
db.execute("PRAGMA foreign_keys=ON")
for path in sorted((root / "migrations").glob("*.sql")):
    db.executescript(path.read_text(encoding="utf-8"))
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('provider','p@example.test','x','provider')")
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('parent','q@example.test','x','parent')")
db.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','provider','Trial','pending_registrations','{}')")
db.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES('slot','activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',2,2,'2098-12-31T23:00:00.000Z','2099-01-01T00:00:00.000Z')")
insert = """INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id)
SELECT ?,?,?,?,?,?
WHERE (SELECT COUNT(*) FROM interests WHERE activity_id=? AND status='interested' AND (?='' OR session_id=?)) < ?
AND EXISTS(SELECT 1 FROM activities WHERE id=? AND status='pending_registrations')
AND (?='' OR EXISTS(SELECT 1 FROM trial_sessions WHERE id=? AND status='open' AND starts_at_utc>?))"""
for i in range(3):
    args = (str(i), "activity", "parent", "child"+str(i), 8, "slot",
            "activity", "slot", "slot", 2, "activity", "slot", "slot", "2026-11-08T00:00:00.000Z")
    db.execute(insert, args)
    assert db.execute("SELECT changes()").fetchone()[0] == (1 if i < 2 else 0)
confirm = """UPDATE trial_sessions SET status=? WHERE id=? AND status='open'
AND EXISTS(SELECT 1 FROM activities WHERE id=trial_sessions.activity_id AND status='pending_registrations')
AND ((?='confirmed' AND (SELECT COUNT(*) FROM interests WHERE session_id=trial_sessions.id AND status='interested')>=min_participants)
OR (?='cancelled' AND (SELECT COUNT(*) FROM interests WHERE session_id=trial_sessions.id AND status='interested')<min_participants AND starts_at_utc IS NOT NULL AND starts_at_utc<?))"""
db.execute(confirm, ("confirmed", "slot", "confirmed", "confirmed", "2026-11-08T00:00:00.000Z"))
assert db.execute("SELECT status FROM trial_sessions WHERE id='slot'").fetchone()[0] == "confirmed"
db.execute(insert, ("4","activity","parent","child4",8,"slot","activity","slot","slot",2,"activity","slot","slot","2026-11-08T00:00:00.000Z"))
assert db.execute("SELECT changes()").fetchone()[0] == 0
print("PASS: all D1 migrations, capacity 2/2, confirmation and closed-session rejection")
