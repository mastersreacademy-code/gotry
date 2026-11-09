"""SQLite smoke test for privacy-safe, action-specific interest notifications.
Run: python3 tests/interest_notification_sqlite_smoke.py
"""
import sqlite3
from pathlib import Path

db = sqlite3.connect(":memory:")
db.executescript("""
CREATE TABLE activities(id TEXT PRIMARY KEY,provider_id TEXT);
CREATE TABLE trial_sessions(id TEXT PRIMARY KEY,starts_at TEXT,venue TEXT);
CREATE TABLE interests(id TEXT PRIMARY KEY,activity_id TEXT,parent_id TEXT,status TEXT,session_id TEXT);
CREATE TABLE session_notification_outbox(id TEXT PRIMARY KEY,user_id TEXT,activity_id TEXT,kind TEXT,title TEXT,message TEXT);
INSERT INTO activities VALUES('a','provider');
INSERT INTO trial_sessions VALUES('s1','2026-11-20T10:00','Hall A'),('s2','2026-11-21T11:00','Hall B');
INSERT INTO interests VALUES('i','a','parent','interested','s1');
""")
migration = Path(__file__).resolve().parents[1] / "migrations" / "0011_interest_notification_details.sql"
db.executescript(migration.read_text())
db.execute("UPDATE interests SET session_id='s2' WHERE id='i'")
rows = db.execute("SELECT user_id,kind,message FROM session_notification_outbox ORDER BY rowid").fetchall()
assert len(rows) == 2 and rows[0][1] == "interest_transferred"
assert "Hall B" in rows[0][2] and "Hall B" not in rows[1][2]
db.execute("UPDATE interests SET status='withdrawn' WHERE id='i'")
rows = db.execute("SELECT user_id,kind,message FROM session_notification_outbox ORDER BY rowid").fetchall()
assert len(rows) == 4 and all("withdrawn" in row[2] for row in rows[-2:])
db.execute("UPDATE interests SET status='interested' WHERE id='i'")
rows = db.execute("SELECT user_id,kind,message FROM session_notification_outbox ORDER BY rowid").fetchall()
assert len(rows) == 6 and all(row[1] == "interest_registered" for row in rows[-2:])
db.execute("UPDATE interests SET session_id='s2' WHERE id='i'")
assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0] == 6
print("Interest notification SQLite smoke PASS")
