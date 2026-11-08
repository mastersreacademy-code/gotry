-- Durable per-recipient outbox for session lifecycle events.
-- Events are inserted in the same SQLite transaction as a session UPDATE.
CREATE TABLE IF NOT EXISTS session_notification_outbox (
 id TEXT PRIMARY KEY,
 user_id TEXT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
 activity_id TEXT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
 kind TEXT NOT NULL,
 title TEXT NOT NULL,
 message TEXT NOT NULL,
 delivered_at TEXT,
 created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS session_outbox_pending ON session_notification_outbox(user_id,delivered_at,created_at);
CREATE TRIGGER IF NOT EXISTS session_parent_outbox AFTER UPDATE OF status,starts_at,ends_at,venue ON trial_sessions
WHEN OLD.status<>NEW.status OR OLD.starts_at<>NEW.starts_at OR OLD.ends_at<>NEW.ends_at OR OLD.venue<>NEW.venue
BEGIN
 INSERT INTO session_notification_outbox(id,user_id,activity_id,kind,title,message)
 SELECT lower(hex(randomblob(16))),i.parent_id,NEW.activity_id,
 CASE WHEN NEW.status<>OLD.status THEN 'session_status' ELSE 'session_rescheduled' END,
 CASE WHEN NEW.status='cancelled' THEN 'Trial session cancelled' WHEN NEW.status='confirmed' THEN 'Trial session confirmed' ELSE 'Trial schedule changed' END,
 CASE WHEN NEW.status='cancelled' THEN 'Your selected trial session has been cancelled. No payment was collected.'
 WHEN NEW.status='confirmed' THEN 'Minimum interest reached. This is not a confirmed payment or booking.'
 ELSE 'New Sydney time: '||NEW.starts_at||' at '||NEW.venue||'. Please review your trial details.' END
 FROM (SELECT DISTINCT parent_id FROM interests WHERE session_id=NEW.id AND status='interested') i;
END;
CREATE TRIGGER IF NOT EXISTS session_provider_outbox AFTER UPDATE OF status,starts_at,ends_at,venue ON trial_sessions
WHEN OLD.status<>NEW.status OR OLD.starts_at<>NEW.starts_at OR OLD.ends_at<>NEW.ends_at OR OLD.venue<>NEW.venue
BEGIN
 INSERT INTO session_notification_outbox(id,user_id,activity_id,kind,title,message)
 SELECT lower(hex(randomblob(16))),a.provider_id,NEW.activity_id,
 CASE WHEN NEW.status<>OLD.status THEN 'session_status' ELSE 'session_rescheduled' END,
 CASE WHEN NEW.status='cancelled' THEN 'Trial session cancelled' WHEN NEW.status='confirmed' THEN 'Trial session confirmed' ELSE 'Trial schedule changed' END,
 CASE WHEN NEW.status='cancelled' THEN 'A trial session has been cancelled.'
 WHEN NEW.status='confirmed' THEN 'Admin confirmed the minimum for a trial session. No payment was collected.'
 ELSE 'Sydney time updated to '||NEW.starts_at||' at '||NEW.venue END
 FROM activities a WHERE a.id=NEW.activity_id;
END;
