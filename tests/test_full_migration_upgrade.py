"""Execute the actual migration files in order, including a historical-data upgrade.
Run: python3 -m unittest discover -s tests -p 'test_full_migration_upgrade.py'
"""
import pathlib
import sqlite3
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[1]
FILES = sorted((ROOT / 'migrations').glob('*.sql'))


class FullMigrationUpgradeTests(unittest.TestCase):
    def test_fresh_database_all_migrations(self):
        self.assertEqual(len(FILES), 13, 'Update this test when adding a migration')
        db = sqlite3.connect(':memory:')
        try:
            db.execute('PRAGMA foreign_keys=ON')
            for path in FILES:
                with self.subTest(migration=path.name):
                    db.executescript(path.read_text())
            self.assertEqual(db.execute('PRAGMA foreign_key_check').fetchall(), [])
            self.assertIn('outbox_id', [x[1] for x in db.execute('PRAGMA table_info(notifications)')])
        finally:
            db.close()

    def test_upgrade_from_0012_with_legacy_notification(self):
        db = sqlite3.connect(':memory:')
        try:
            db.execute('PRAGMA foreign_keys=ON')
            for path in FILES[:-1]:
                db.executescript(path.read_text())
            db.execute("INSERT INTO users(id,email,password_hash,role) VALUES ('parent','parent@example.test','x','parent')")
            db.execute("INSERT INTO users(id,email,password_hash,role) VALUES ('provider','provider@example.test','x','provider')")
            db.execute("INSERT INTO activities(id,provider_id,title,data_json,status) VALUES ('act','provider','Trial','{}','pending_registrations')")
            event_id = '0123456789abcdef0123456789abcdef'
            legacy_id = '01234567-89ab-cdef-0123-456789abcdef'
            db.execute("INSERT INTO session_notification_outbox(id,user_id,activity_id,kind,title,message,created_at) VALUES (?,?,?,?,?,?,?)", (event_id,'parent','act','interest_registered','Interest registered','Recorded','2026-01-01'))
            db.execute("INSERT INTO notifications(id,user_id,activity_id,kind,title,message,created_at) VALUES (?,?,?,?,?,?,?)", (legacy_id,'parent','act','interest_registered','Interest registered','Recorded','2026-01-01'))
            db.commit()
            db.executescript(FILES[-1].read_text())
            self.assertEqual(db.execute('SELECT outbox_id FROM notifications WHERE id=?',(legacy_id,)).fetchone()[0],event_id)
            self.assertEqual(db.execute('PRAGMA foreign_key_check').fetchall(), [])
            with self.assertRaises(sqlite3.IntegrityError):
                db.execute("INSERT INTO notifications(id,user_id,activity_id,kind,title,message,outbox_id) VALUES ('duplicate','parent','act','interest_registered','Title','Message',?)", (event_id,))
        finally:
            db.close()


if __name__ == '__main__':
    unittest.main()
