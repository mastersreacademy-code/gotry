"""Local migration + concurrency smoke: python3 tests/session_concurrency_sqlite.py
Run from a full repository checkout. SQLite mirrors D1 SQL semantics; Wrangler
and browser E2E must be run separately.
"""
import sqlite3
import tempfile
import threading
from pathlib import Path

root=Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory() as tmp:
    path=str(Path(tmp)/'trial.db')
    setup=sqlite3.connect(path,timeout=10)
    setup.execute('PRAGMA foreign_keys=ON')
    setup.execute('PRAGMA journal_mode=WAL')
    for migration in sorted((root/'migrations').glob('*.sql')):
        setup.executescript(migration.read_text(encoding='utf-8'))
    setup.execute("INSERT INTO users(id,email,password_hash,role) VALUES('provider','provider@example.test','x','provider')")
    for n in range(6):
        setup.execute("INSERT INTO users(id,email,password_hash,role) VALUES(?,?,?,?)",(f'parent{n}',f'parent{n}@example.test','x','parent'))
    setup.execute("INSERT INTO activities(id,provider_id,title,status,data_json) VALUES('activity','provider','Trial','pending_registrations','{}')")
    for sid in ('slot','alternative'):
        setup.execute("INSERT INTO trial_sessions(id,activity_id,starts_at,ends_at,venue,min_participants,max_participants,starts_at_utc,ends_at_utc) VALUES(?,'activity','2099-01-01T10:00','2099-01-01T11:00','Sydney',1,2,'2098-12-31T23:00:00.000Z','2099-01-01T00:00:00.000Z')",(sid,))
    setup.commit()
    results=[]
    barrier=threading.Barrier(6)
    def register(n):
        db=sqlite3.connect(path,timeout=15)
        db.execute('PRAGMA busy_timeout=15000')
        barrier.wait()
        try:
            db.execute("INSERT INTO interests(id,activity_id,parent_id,child_name,child_age,session_id) VALUES(?, 'activity', ?, 'Child', 8, 'slot')",(f'i{n}',f'parent{n}'))
            db.commit()
            results.append('ok')
        except sqlite3.IntegrityError:
            db.rollback()
            results.append('full')
        finally:
            db.close()
    threads=[threading.Thread(target=register,args=(n,)) for n in range(6)]
    for t in threads:t.start()
    for t in threads:t.join()
    assert results.count('ok')==2,results
    assert results.count('full')==4,results
    assert setup.execute("SELECT COUNT(*) FROM interests WHERE session_id='slot' AND status='interested'").fetchone()[0]==2
    winner=setup.execute("SELECT id FROM interests WHERE session_id='slot' LIMIT 1").fetchone()[0]
    setup.execute("UPDATE interests SET session_id='alternative' WHERE id=?",(winner,))
    assert setup.execute("SELECT COUNT(*) FROM interests WHERE session_id='slot' AND status='interested'").fetchone()[0]==1
    assert setup.execute("SELECT COUNT(*) FROM interests WHERE session_id='alternative' AND status='interested'").fetchone()[0]==1
    setup.execute("UPDATE trial_sessions SET status='confirmed' WHERE id='alternative'")
    setup.commit()
    assert setup.execute("SELECT COUNT(*) FROM session_notification_outbox").fetchone()[0]>=6
    print('PASS: all migrations; 6 concurrent registrations, exactly 2 accepted; transfer and notification outbox')
