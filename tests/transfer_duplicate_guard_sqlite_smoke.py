"""Run: python3 tests/transfer_duplicate_guard_sqlite_smoke.py
Verify normalized duplicate-session and stale-source guards using SQLite.
"""
import sqlite3
from pathlib import Path

source=(Path(__file__).resolve().parents[1]/"src"/"api.js").read_text(encoding="utf-8")
assert "lower(trim(other.child_name))=lower(trim(interests.child_name))" in source
db=sqlite3.connect(":memory:")
db.executescript("""
CREATE TABLE interests(id TEXT PRIMARY KEY,activity_id TEXT,parent_id TEXT,child_name TEXT,session_id TEXT,status TEXT);
INSERT INTO interests VALUES('a','activity','parent',' Alex ','s1','interested');
INSERT INTO interests VALUES('b','activity','parent','alex','s2','withdrawn');
""")
query="""UPDATE interests SET session_id=? WHERE id=? AND parent_id=? AND status='interested' AND session_id=?
 AND NOT EXISTS(SELECT 1 FROM interests other WHERE other.activity_id=interests.activity_id
 AND other.parent_id=interests.parent_id
 AND lower(trim(other.child_name))=lower(trim(interests.child_name))
 AND other.session_id=? AND other.id<>interests.id)"""
assert db.execute(query,('s2','a','parent','s1','s2')).rowcount==0
assert db.execute("SELECT session_id FROM interests WHERE id='a'").fetchone()[0]=='s1'
db.execute("DELETE FROM interests WHERE id='b'")
assert db.execute(query,('s2','a','parent','s1','s2')).rowcount==1
assert db.execute(query,('s2','a','parent','s1','s2')).rowcount==0
print("PASS: normalized duplicate guard and stale transfer precondition")
