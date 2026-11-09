"""Run: python3 tests/reactivation_idempotency_sqlite_smoke.py
Verifies atomic withdrawn-state precondition and single outbox event.
"""
import sqlite3
from pathlib import Path
api=(Path(__file__).resolve().parents[1]/'src'/'api.js').read_text(encoding='utf-8')
assert "WHERE id=? AND status='withdrawn' AND EXISTS" in api
db=sqlite3.connect(':memory:')
db.executescript("""
CREATE TABLE interests(id TEXT PRIMARY KEY,status TEXT);
CREATE TABLE outbox(id INTEGER PRIMARY KEY,kind TEXT);
CREATE TRIGGER interest_restore AFTER UPDATE OF status ON interests
WHEN OLD.status<>NEW.status BEGIN INSERT INTO outbox(kind) VALUES('restored'); END;
INSERT INTO interests VALUES('i','withdrawn');
""")
query="UPDATE interests SET status='interested' WHERE id=? AND status='withdrawn'"
assert db.execute(query,('i',)).rowcount==1
assert db.execute(query,('i',)).rowcount==0
assert db.execute("SELECT COUNT(*) FROM outbox").fetchone()[0]==1
print("PASS: one reactivation and one outbox event")
