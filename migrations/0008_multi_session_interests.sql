-- Permit the same child to express interest in separate trial sessions.
-- Preserve existing interest IDs and status. Apply only after 0007.
CREATE TABLE interests_v2 (
 id TEXT PRIMARY KEY,
 activity_id TEXT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
 parent_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 child_name TEXT NOT NULL,
 child_age INTEGER NOT NULL CHECK(child_age BETWEEN 1 AND 18),
 status TEXT NOT NULL DEFAULT 'interested' CHECK(status IN ('interested','withdrawn')),
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
 session_id TEXT REFERENCES trial_sessions(id)
);
INSERT INTO interests_v2(id,activity_id,parent_id,child_name,child_age,status,created_at,updated_at,session_id)
SELECT id,activity_id,parent_id,child_name,child_age,status,created_at,updated_at,session_id FROM interests;
DROP TABLE interests;
ALTER TABLE interests_v2 RENAME TO interests;
CREATE UNIQUE INDEX interests_child_session_unique ON interests(activity_id,parent_id,child_name,session_id) WHERE session_id IS NOT NULL;
CREATE UNIQUE INDEX interests_child_legacy_unique ON interests(activity_id,parent_id,child_name) WHERE session_id IS NULL;
CREATE INDEX interests_activity ON interests(activity_id,status);
CREATE INDEX interests_parent ON interests(parent_id,status);
CREATE INDEX interests_session ON interests(session_id,status);

-- Atomic notifications for registration, withdrawal and moving a child.
CREATE TRIGGER interest_registered_outbox AFTER INSERT ON interests
WHEN NEW.status='interested'
BEGIN
 INSERT INTO session_notification_outbox(id,user_id,activity_id,kind,title,message)
 VALUES(lower(hex(randomblob(16))),NEW.parent_id,NEW.activity_id,'interest_registered','Interest registered','Interest recorded. This is not a confirmed booking.');
 INSERT INTO session_notification_outbox(id,user_id,activity_id,kind,title,message)
 SELECT lower(hex(randomblob(16))),a.provider_id,NEW.activity_id,'interest_registered','New trial interest','A parent registered interest in a trial session.'
 FROM activities a WHERE a.id=NEW.activity_id;
END;
CREATE TRIGGER interest_changed_outbox AFTER UPDATE OF status,session_id ON interests
WHEN OLD.status<>NEW.status OR OLD.session_id IS NOT NEW.session_id
BEGIN
 INSERT INTO session_notification_outbox(id,user_id,activity_id,kind,title,message)
 VALUES(lower(hex(randomblob(16))),NEW.parent_id,NEW.activity_id,
 CASE WHEN NEW.status='withdrawn' THEN 'interest_withdrawn' WHEN OLD.session_id IS NOT NEW.session_id THEN 'interest_transferred' ELSE 'interest_registered' END,
 CASE WHEN NEW.status='withdrawn' THEN 'Interest withdrawn' WHEN OLD.session_id IS NOT NEW.session_id THEN 'Trial session changed' ELSE 'Interest restored' END,
 'Your trial interest was updated. This is not a confirmed booking.');
 INSERT INTO session_notification_outbox(id,user_id,activity_id,kind,title,message)
 SELECT lower(hex(randomblob(16))),a.provider_id,NEW.activity_id,'interest_changed','Trial interest changed','A parent updated their trial interest.'
 FROM activities a WHERE a.id=NEW.activity_id;
END;
