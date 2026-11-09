"""Run: python3 tests/roster_privacy_sqlite_smoke.py
Executes the provider roster query on real SQLite for open, confirmed and cancelled states.
"""
import sqlite3

db=sqlite3.connect(":memory:")
db.row_factory=sqlite3.Row
db.executescript("""
CREATE TABLE activities(id TEXT,provider_id TEXT,status TEXT);
CREATE TABLE trial_sessions(id TEXT,activity_id TEXT,status TEXT);
CREATE TABLE interests(id TEXT,session_id TEXT,status TEXT,child_name TEXT,created_at TEXT);
INSERT INTO activities VALUES('a','provider','pending_registrations');
INSERT INTO trial_sessions VALUES('s','a','open');
INSERT INTO interests VALUES('i1','s','interested','Private Child','2026-01-01');
INSERT INTO interests VALUES('i2','s','withdrawn','Withdrawn Child','2026-01-02');
""")
query = """SELECT CASE WHEN s.status='confirmed' AND a.status NOT LIKE 'cancelled%' THEN i.child_name ELSE NULL END AS child_name,
 (SELECT COUNT(*) FROM interests all_i WHERE all_i.session_id=s.id AND all_i.status='interested') AS total,
 CASE WHEN s.status='confirmed' AND a.status NOT LIKE 'cancelled%' THEN 1 ELSE 0 END AS names_available
 FROM trial_sessions s JOIN activities a ON a.id=s.activity_id
 LEFT JOIN interests i ON i.session_id=s.id AND i.status='interested'
 WHERE s.id=? AND a.provider_id=? ORDER BY i.created_at ASC LIMIT 100"""
def roster(owner):
    return [dict(row) for row in db.execute(query,('s',owner))]
assert roster('intruder') == []
assert roster('provider') == [{'child_name':None,'total':1,'names_available':0}]
db.execute("UPDATE trial_sessions SET status='confirmed'")
assert roster('provider') == [{'child_name':'Private Child','total':1,'names_available':1}]
db.execute("UPDATE activities SET status='cancelled_by_provider'")
assert roster('provider') == [{'child_name':None,'total':1,'names_available':0}]
db.execute("UPDATE activities SET status='pending_registrations'")
db.execute("UPDATE trial_sessions SET status='cancelled'")
assert roster('provider') == [{'child_name':None,'total':1,'names_available':0}]
print("PASS: provider roster privacy, withdrawn exclusion, cancelled states and unauthorized owner")
