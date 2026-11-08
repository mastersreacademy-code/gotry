-- Enforce session capacity at the database boundary, not only in API predicates.
-- Requires migration 0008. SQLite serializes writes; these triggers guard all writers.
CREATE TRIGGER IF NOT EXISTS interest_session_capacity_insert
BEFORE INSERT ON interests
WHEN NEW.status='interested' AND NEW.session_id IS NOT NULL
BEGIN
 SELECT RAISE(ABORT,'trial session unavailable or full')
 WHERE NOT EXISTS (
  SELECT 1 FROM trial_sessions s JOIN activities a ON a.id=s.activity_id
  WHERE s.id=NEW.session_id AND s.activity_id=NEW.activity_id
    AND s.status='open' AND a.status='pending_registrations'
    AND s.starts_at_utc IS NOT NULL AND s.starts_at_utc>strftime('%Y-%m-%dT%H:%M:%fZ','now')
    AND (SELECT COUNT(*) FROM interests i WHERE i.session_id=s.id AND i.status='interested')<s.max_participants
 );
END;
CREATE TRIGGER IF NOT EXISTS interest_session_capacity_update
BEFORE UPDATE OF session_id,status ON interests
WHEN NEW.status='interested' AND NEW.session_id IS NOT NULL
 AND (OLD.status<>'interested' OR OLD.session_id IS NOT NEW.session_id)
BEGIN
 SELECT RAISE(ABORT,'trial session unavailable or full')
 WHERE NOT EXISTS (
  SELECT 1 FROM trial_sessions s JOIN activities a ON a.id=s.activity_id
  WHERE s.id=NEW.session_id AND s.activity_id=NEW.activity_id
    AND s.status='open' AND a.status='pending_registrations'
    AND s.starts_at_utc IS NOT NULL AND s.starts_at_utc>strftime('%Y-%m-%dT%H:%M:%fZ','now')
    AND (SELECT COUNT(*) FROM interests i WHERE i.session_id=s.id AND i.status='interested' AND i.id<>OLD.id)<s.max_participants
 );
END;
