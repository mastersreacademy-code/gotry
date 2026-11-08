-- Sessions are individually scheduled slots under a reviewed activity.
-- Existing expressions of interest remain linked to their original activity.
CREATE TABLE IF NOT EXISTS trial_sessions (
 id TEXT PRIMARY KEY,
 activity_id TEXT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
 starts_at TEXT NOT NULL,
 ends_at TEXT NOT NULL,
 venue TEXT NOT NULL,
 min_participants INTEGER NOT NULL CHECK(min_participants BETWEEN 1 AND 100),
 max_participants INTEGER NOT NULL CHECK(max_participants BETWEEN min_participants AND 100),
 status TEXT NOT NULL DEFAULT 'open' CHECK(status IN ('open','confirmed','cancelled')),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS trial_sessions_activity ON trial_sessions(activity_id,starts_at);
ALTER TABLE interests ADD COLUMN session_id TEXT REFERENCES trial_sessions(id);
CREATE INDEX IF NOT EXISTS interests_session ON interests(session_id,status);
