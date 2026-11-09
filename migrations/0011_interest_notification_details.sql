-- Action-specific, privacy-safe interest-change notifications.
-- Provider notifications deliberately omit child names and parent contact details.
DROP TRIGGER IF EXISTS interest_changed_outbox;
CREATE TRIGGER interest_changed_outbox AFTER UPDATE OF status,session_id ON interests
WHEN OLD.status<>NEW.status OR OLD.session_id IS NOT NEW.session_id
BEGIN
 INSERT INTO session_notification_outbox(id,user_id,activity_id,kind,title,message)
 VALUES(lower(hex(randomblob(16))),NEW.parent_id,NEW.activity_id,
 CASE WHEN NEW.status='withdrawn' THEN 'interest_withdrawn' WHEN OLD.session_id IS NOT NEW.session_id THEN 'interest_transferred' ELSE 'interest_registered' END,
 CASE WHEN NEW.status='withdrawn' THEN 'Interest withdrawn' WHEN OLD.session_id IS NOT NEW.session_id THEN 'Trial session changed' ELSE 'Interest restored' END,
 CASE WHEN NEW.status='withdrawn' THEN 'Your trial interest has been withdrawn. No payment was collected.'
 WHEN OLD.session_id IS NOT NEW.session_id THEN 'Your new trial session is '||COALESCE((SELECT starts_at||' at '||venue||' (Sydney time)' FROM trial_sessions WHERE id=NEW.session_id),'not yet assigned')||'. Please review your Parent dashboard.'
 ELSE 'Your trial interest is active again. This is not a confirmed booking.' END);
 INSERT INTO session_notification_outbox(id,user_id,activity_id,kind,title,message)
 SELECT lower(hex(randomblob(16))),a.provider_id,NEW.activity_id,
 CASE WHEN NEW.status='withdrawn' THEN 'interest_withdrawn' WHEN OLD.session_id IS NOT NEW.session_id THEN 'interest_transferred' ELSE 'interest_registered' END,
 CASE WHEN NEW.status='withdrawn' THEN 'Trial interest withdrawn' WHEN OLD.session_id IS NOT NEW.session_id THEN 'Trial session changed' ELSE 'Trial interest restored' END,
 CASE WHEN NEW.status='withdrawn' THEN 'An expression of interest was withdrawn. Review the updated session roster and available places.'
 WHEN OLD.session_id IS NOT NEW.session_id THEN 'An expression of interest moved between trial sessions. Review updated attendance and availability.'
 ELSE 'An expression of interest was restored. Review updated attendance and availability.' END
 FROM activities a WHERE a.id=NEW.activity_id;
END;
