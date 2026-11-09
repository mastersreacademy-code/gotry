"""Real SQLite integration checks for all GoTry migrations and concurrent writers.

Run: python3 tests/concurrent_capacity_sqlite_smoke.py
Uses a temporary on-disk database, separate SQLite connections and WAL.
No Wrangler, Cloudflare, Playwright or GitHub Actions required.
"""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
from tempfile import TemporaryDirectory
import sqlite3
import threading

ROOT = Path(__file__).resolve().parents[1]
MIGRATIONS = sorted((ROOT / "migrations").glob("*.sql"))
assert [p.name[:4] for p in MIGRATIONS] == [f"{i:04}" for i in range(1, 12)], "Expected migrations 0001–0011"

with TemporaryDirectory() as directory:
    path = str(Path(directory) / "gotry.sqlite")
    def connect():
        db = sqlite3.connect(path, timeout=20, isolation_level=None)
        db.execute("PRAGMA foreign_keys=ON")
        db.execute("PRAGMA busy_timeout=20000")
        return db

    db = connect()
    db.execute("PRAGMA journal_mode=WAL")
    for migration in MIGRATIONS:
        db.executescript(migration.read_text(encoding="utf-8"))

    users = [("provider", "provider")] + [(f"p{i}", "parent") for i in range(12)]
    for uid, role in users:
        db.execute("INSERT INTO users(id,email,password_hash,role) VALUES(?,?,?,?)", (uid, uid + "@example.test", "x", role))
    db.execute("""INSERT INTO activities(id,provider_id,title,status,data_json)
               VALUES('activity','provider','Trial','pending_registrations',
               '{"deadline":"2099-01-01","trialDate":"2099-01-02"}')""")
    for sid in ("a", "b"):
        db.execute("""INSERT INTO trial_sessions
            (id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc)
            VALUES(?,'activity','2099-01-02T10:00','2099-01-02T11:00','Sydney',1,2,
                   '2099-01-01T23:00:00.000Z','2099-01-02T00:00:00.000Z')""", (sid,))

    barrier = threading.Barrier(10)
    def register(i):
        connection = connect()
        try:
            barrier.wait(timeout=10)
            result = connection.execute("""INSERT INTO interests
                (id,activity_id,parent_id,child_name,child_age,session_id)
                SELECT ?,'activity',?,?,8,'a'
                WHERE (SELECT COUNT(*) FROM interests WHERE session_id='a' AND status='interested')<2
                """, (f"interest{i}", f"p{i}", f"Child {i}"))
            return result.rowcount == 1
        except sqlite3.IntegrityError:
            return False
        finally:
            connection.close()

    with ThreadPoolExecutor(max_workers=10) as pool:
        outcomes = list(pool.map(register, range(10)))
    assert sum(outcomes) == 2, f"Concurrent registrations exceeded capacity: {outcomes}"
    assert db.execute("SELECT COUNT(*) FROM interests WHERE session_id='a' AND status='interested'").fetchone()[0] == 2
    assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0] == 4, "Rejected registrations emitted notifications"

    # A separate session can accept a different child, but not the same active child.
    winner = outcomes.index(True)
    db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('b1','activity','p11','Other',8,'b')")
    try:
        db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('dup','activity',?, ?,8,'b')",
                   (f"p{winner}", f" child {winner} "))
        raise AssertionError("Duplicate active child across sessions accepted")
    except sqlite3.IntegrityError:
        pass
    assert db.execute("SELECT COUNT(*) FROM interests WHERE id='dup'").fetchone()[0] == 0

    # A transfer to a full session is rejected atomically, including the outbox.
    db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('b2','activity','p10','Other 2',8,'b')")
    count = db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]
    try:
        db.execute("UPDATE interests SET session_id='b' WHERE id=?", (f"interest{winner}",))
        raise AssertionError("Transfer into full session accepted")
    except sqlite3.IntegrityError:
        pass
    assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0] == count

    # Withdrawal releases a place; a new parent can register.
    db.execute("UPDATE interests SET status='withdrawn' WHERE id=?", (f"interest{winner}",))
    db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('replacement','activity','p10','Replacement',8,'a')")
    assert db.execute("SELECT COUNT(*) FROM interests WHERE session_id='a' AND status='interested'").fetchone()[0] == 2

    # Session cancellation notifies only current active parents and provider.
    count = db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]
    db.execute("UPDATE trial_sessions SET status='cancelled' WHERE id='a'")
    assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0] == count + 3
    count = db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]
    db.execute("UPDATE trial_sessions SET status='cancelled' WHERE id='a'")
    assert db.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0] == count, "No-op cancellation emitted duplicate notifications"
    try:
        db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES('late','activity','p9','Late',8,'a')")
        raise AssertionError("Registration in cancelled session accepted")
    except sqlite3.IntegrityError:
        pass

    # Providers must not receive child names in the durable interest messages.
    provider_messages = db.execute("SELECT message FROM session_notification_outbox WHERE user_id='provider'").fetchall()
    assert provider_messages and all("Child " not in message[0] for message in provider_messages)
    db.close()

print("PASS: migrations 0001–0011, 10 concurrent writers, capacity, uniqueness, rollback, outbox, cancellation and privacy")
