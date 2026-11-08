CREATE TABLE IF NOT EXISTS interests (
 id TEXT PRIMARY KEY,
 activity_id TEXT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
 parent_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 child_name TEXT NOT NULL,
 child_age INTEGER NOT NULL CHECK(child_age BETWEEN 1 AND 18),
 status TEXT NOT NULL DEFAULT 'interested' CHECK(status IN ('interested','withdrawn')),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(activity_id,parent_id,child_name)
);
CREATE INDEX IF NOT EXISTS interests_activity ON interests(activity_id,status);
CREATE INDEX IF NOT EXISTS interests_parent ON interests(parent_id,status);
