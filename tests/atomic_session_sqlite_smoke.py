"""Exercise optimistic session guards with real SQLite, no Wrangler needed.
Run: python3 tests/atomic_session_sqlite_smoke.py
"""
import sqlite3

db = sqlite3.connect(":memory:")
db.executescript("""
CREATE TABLE interests(id TEXT PRIMARY KEY, parent_id TEXT, session_id TEXT, status TEXT);
CREATE TABLE trial_sessions(id TEXT PRIMARY KEY, starts_at TEXT, starts_at_utc TEXT, status TEXT);
CREATE TABLE outbox(kind TEXT);
CREATE TRIGGER transfer_notice AFTER UPDATE OF session_id ON interests
WHEN OLD.session_id IS NOT NEW.session_id
BEGIN INSERT INTO outbox VALUES('transferred'); END;
CREATE TRIGGER session_notice AFTER UPDATE OF status,starts_at ON trial_sessions
WHEN OLD.status<>NEW.status OR OLD.starts_at<>NEW.starts_at
BEGIN INSERT INTO outbox VALUES('session_changed'); END;
INSERT INTO trial_sessions VALUES('a','2099-01-01T10:00','2099-01-01T00:00:00Z','open');
INSERT INTO trial_sessions VALUES('b','2099-01-02T10:00','2099-01-02T00:00:00Z','open');
INSERT INTO trial_sessions VALUES('c','2099-01-03T10:00','2099-01-03T00:00:00Z','open');
INSERT INTO interests VALUES('i','parent','a','interested');
""")
def move(source, target):
    return db.execute("UPDATE interests SET session_id=? WHERE id='i' AND parent_id='parent' AND status='interested' AND session_id=? AND EXISTS(SELECT 1 FROM trial_sessions WHERE id=? AND status='open')", (target, source, target)).rowcount

assert move('a','b') == 1
assert move('a','c') == 0, "Stale transfer overwrote newer session"
assert db.execute("SELECT session_id FROM interests WHERE id='i'").fetchone()[0] == 'b'
assert db.execute("SELECT COUNT(*) FROM outbox WHERE kind='transferred'").fetchone()[0] == 1
assert db.execute("UPDATE interests SET status='withdrawn' WHERE id='i' AND status='interested' AND session_id IS 'a'").rowcount == 0
assert db.execute("UPDATE interests SET status='withdrawn' WHERE id='i' AND status='interested' AND session_id IS 'b'").rowcount == 1
def reschedule(expected, new):
    return db.execute("UPDATE trial_sessions SET starts_at=? WHERE id='a' AND status='open' AND starts_at=?", (new, expected)).rowcount
assert reschedule('2099-01-01T10:00','2099-01-01T11:00') == 1
assert reschedule('2099-01-01T10:00','2099-01-01T12:00') == 0
assert db.execute("UPDATE trial_sessions SET status='cancelled' WHERE id='a' AND status='open' AND starts_at='2099-01-01T10:00'").rowcount == 0
assert db.execute("UPDATE trial_sessions SET status='cancelled' WHERE id='a' AND status='open' AND starts_at='2099-01-01T11:00'").rowcount == 1
count=db.execute("SELECT COUNT(*) FROM outbox").fetchone()[0]
db.execute("UPDATE trial_sessions SET status='cancelled' WHERE id='a'")
assert db.execute("SELECT COUNT(*) FROM outbox").fetchone()[0] == count
print("PASS: stale transfer/withdraw/reschedule/cancel rejected; no duplicate outbox on no-op")
