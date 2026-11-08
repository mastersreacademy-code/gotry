"""Run: python3 tests/outbox_sqlite_smoke.py
SQLite is D1-compatible for these SQL checks; Wrangler D1 E2E remains separate.
"""
from pathlib import Path
import sqlite3

root=Path(__file__).resolve().parents[1]
db=sqlite3.connect(":memory:")
db.execute("PRAGMA foreign_keys=ON")
for migration in sorted((root/"migrations").glob("*.sql")):
    db.executescript(migration.read_text())
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('provider','provider@example.test','x','provider')")
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('parent','parent@example.test','x','parent')")
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('admin','admin@example.test','x','admin')")
db.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','provider','Trial','pending_registrations','{}')")
db.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES('slot','activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',1,2,'2098-12-31T23:00:00.000Z','2099-01-01T00:00:00.000Z')")
db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('interest','activity','parent','Child',8,'slot')")
db.commit()
# Migration 0008 queues two registration notices before session confirmation.
baseline=db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]
assert baseline==2, f"Expected parent/provider registration notices, got {baseline}"
db.execute("UPDATE trial_sessions SET status='confirmed' WHERE id='slot'")
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]==baseline+2
db.execute("UPDATE trial_sessions SET status='confirmed' WHERE id='slot'")
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]==baseline+2
deliver="""INSERT OR IGNORE INTO notifications(id,user_id,activity_id,kind,title,message,created_at)
SELECT substr(id,1,8)||'-'||substr(id,9,4)||'-'||substr(id,13,4)||'-'||substr(id,17,4)||'-'||substr(id,21,12),user_id,activity_id,kind,title,message,created_at
FROM session_notification_outbox WHERE user_id=? AND delivered_at IS NULL"""
ack="UPDATE session_notification_outbox SET delivered_at=CURRENT_TIMESTAMP WHERE user_id=? AND delivered_at IS NULL"
for user in ("parent","provider"):
    with db:
        db.execute(deliver,(user,))
        db.execute(ack,(user,))
    with db:
        db.execute(deliver,(user,))
        db.execute(ack,(user,))
assert db.execute("SELECT COUNT(*) FROM notifications").fetchone()[0]==baseline+2
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox WHERE delivered_at IS NULL").fetchone()[0]==0
db.execute("UPDATE trial_sessions SET starts_at='2099-01-02T10:00',venue='New venue' WHERE id='slot'")
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox WHERE delivered_at IS NULL").fetchone()[0]==2
db.execute("INSERT INTO users(id,email,password_hash,role) VALUES('other','other@example.test','x','parent')")
with db:
    db.execute(deliver,("other",))
    db.execute(ack,("other",))
assert db.execute("SELECT COUNT(*) FROM notifications WHERE user_id='other'").fetchone()[0]==0
# A trigger failure must roll back the status update as well.
db.execute("CREATE TRIGGER reject_outbox BEFORE INSERT ON session_notification_outbox BEGIN SELECT RAISE(ABORT,'outbox unavailable'); END")
try:
    db.execute("UPDATE trial_sessions SET status='cancelled' WHERE id='slot'")
    raise AssertionError("Expected transactional rollback")
except sqlite3.IntegrityError:
    pass
assert db.execute("SELECT status FROM trial_sessions WHERE id='slot'").fetchone()[0]=="confirmed"
print("PASS: all migrations, atomic trigger rollback, no duplicate delivery, recipient isolation, reschedule")
