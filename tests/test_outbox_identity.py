"""Standalone SQLite regression tests for notification outbox migration 0013.
Run: python3 -m unittest discover -s tests -p 'test_outbox_identity.py'
No external dependencies or deployed services.
"""
import pathlib
import sqlite3
import unittest

MIGRATION = pathlib.Path(__file__).resolve().parents[1] / 'migrations' / '0013_notification_outbox_identity.sql'
OUTBOX_ID = '0123456789abcdef0123456789abcdef'
LEGACY_ID = '01234567-89ab-cdef-0123-456789abcdef'


class OutboxIdentityTests(unittest.TestCase):
    def setUp(self):
        self.db = sqlite3.connect(':memory:')
        self.db.execute('PRAGMA foreign_keys=ON')
        self.db.executescript('''
            CREATE TABLE notifications(id TEXT PRIMARY KEY,user_id TEXT,activity_id TEXT,kind TEXT,title TEXT,message TEXT,created_at TEXT);
            CREATE TABLE session_notification_outbox(id TEXT PRIMARY KEY,user_id TEXT,activity_id TEXT,kind TEXT,title TEXT,message TEXT,created_at TEXT,delivered_at TEXT);
        ''')
        self.db.execute('INSERT INTO session_notification_outbox VALUES (?,?,?,?,?,?,?,NULL)', (OUTBOX_ID, 'parent', 'activity', 'registered', 'Title', 'Message', '2026-01-01'))
        self.db.execute('INSERT INTO notifications VALUES (?,?,?,?,?,?,?)', (LEGACY_ID, 'parent', 'activity', 'registered', 'Title', 'Message', '2026-01-01'))
        self.db.executescript(MIGRATION.read_text())

    def tearDown(self):
        self.db.close()

    def test_backfill_existing_notification(self):
        self.assertEqual(self.db.execute('SELECT outbox_id FROM notifications WHERE id=?', (LEGACY_ID,)).fetchone()[0], OUTBOX_ID)

    def test_unique_outbox_identity(self):
        with self.assertRaises(sqlite3.IntegrityError):
            self.db.execute('INSERT INTO notifications VALUES (?,?,?,?,?,?,?,?)', ('another', 'parent', 'activity', 'registered', 'Title', 'Message', '2026-01-01', OUTBOX_ID))

    def test_cross_recipient_isolation(self):
        self.db.execute('INSERT INTO session_notification_outbox VALUES (?,?,?,?,?,?,?,NULL)', ('f'*32, 'other', 'activity', 'registered', 'Title', 'Message', '2026-01-01'))
        self.db.execute('INSERT INTO notifications VALUES (?,?,?,?,?,?,?,?)', ('new', 'other', 'activity', 'registered', 'Title', 'Message', '2026-01-01', 'f'*32))
        self.db.execute('''UPDATE session_notification_outbox SET delivered_at=CURRENT_TIMESTAMP
            WHERE user_id=? AND delivered_at IS NULL AND EXISTS (
              SELECT 1 FROM notifications n WHERE n.outbox_id=session_notification_outbox.id
              AND n.user_id=session_notification_outbox.user_id AND n.activity_id=session_notification_outbox.activity_id
              AND n.kind=session_notification_outbox.kind AND n.title=session_notification_outbox.title
              AND n.message=session_notification_outbox.message AND n.created_at=session_notification_outbox.created_at)''', ('other',))
        self.assertIsNotNone(self.db.execute("SELECT delivered_at FROM session_notification_outbox WHERE user_id='other'").fetchone()[0])
        self.assertIsNone(self.db.execute("SELECT delivered_at FROM session_notification_outbox WHERE user_id='parent'").fetchone()[0])

    def test_collision_recovers_without_cross_user_leak(self):
        # Legacy notification already owns the deterministic UUID for an unrelated recipient.
        self.db.execute('UPDATE notifications SET outbox_id=NULL WHERE id=?', (LEGACY_ID,))
        self.db.execute('UPDATE notifications SET user_id=? WHERE id=?', ('other', LEGACY_ID))
        self.db.execute('''INSERT OR IGNORE INTO notifications(id,user_id,activity_id,kind,title,message,created_at,outbox_id)
            SELECT 'recovered-id',user_id,activity_id,kind,title,message,created_at,id
            FROM session_notification_outbox WHERE user_id=? AND delivered_at IS NULL''', ('parent',))
        self.assertEqual(self.db.execute("SELECT user_id FROM notifications WHERE id='recovered-id'").fetchone()[0], 'parent')
        self.assertEqual(self.db.execute("SELECT COUNT(*) FROM notifications WHERE outbox_id=?", (OUTBOX_ID,)).fetchone()[0], 1)
        self.db.execute('''INSERT OR IGNORE INTO notifications(id,user_id,activity_id,kind,title,message,created_at,outbox_id)
            SELECT 'another-id',user_id,activity_id,kind,title,message,created_at,id
            FROM session_notification_outbox WHERE user_id=? AND delivered_at IS NULL''', ('parent',))
        self.assertEqual(self.db.execute("SELECT COUNT(*) FROM notifications WHERE outbox_id=?", (OUTBOX_ID,)).fetchone()[0], 1)

    def test_mismatched_payload_does_not_acknowledge(self):
        self.db.execute('UPDATE notifications SET message=? WHERE id=?', ('Changed', LEGACY_ID))
        self.db.execute('''UPDATE session_notification_outbox SET delivered_at=CURRENT_TIMESTAMP
            WHERE user_id=? AND delivered_at IS NULL AND EXISTS (
              SELECT 1 FROM notifications n WHERE n.outbox_id=session_notification_outbox.id
              AND n.user_id=session_notification_outbox.user_id AND n.activity_id=session_notification_outbox.activity_id
              AND n.kind=session_notification_outbox.kind AND n.title=session_notification_outbox.title
              AND n.message=session_notification_outbox.message AND n.created_at=session_notification_outbox.created_at)''', ('parent',))
        self.assertIsNone(self.db.execute('SELECT delivered_at FROM session_notification_outbox WHERE id=?', (OUTBOX_ID,)).fetchone()[0])

    def test_transaction_rollback(self):
        with self.assertRaises(sqlite3.IntegrityError):
            with self.db:
                self.db.execute('INSERT INTO notifications VALUES (?,?,?,?,?,?,?,?)', ('rollback', 'parent', 'activity', 'registered', 'Title', 'Message', '2026-01-01', 'e'*32))
                self.db.execute('INSERT INTO notifications VALUES (?,?,?,?,?,?,?,?)', ('rollback', 'parent', 'activity', 'registered', 'Title', 'Message', '2026-01-01', 'd'*32))
        self.assertEqual(self.db.execute("SELECT count(*) FROM notifications WHERE id='rollback'").fetchone()[0], 0)


if __name__ == '__main__':
    unittest.main()
