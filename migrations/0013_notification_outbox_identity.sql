-- Give each outbox event a durable identity independent of notification primary keys.
ALTER TABLE notifications ADD COLUMN outbox_id TEXT;
CREATE UNIQUE INDEX IF NOT EXISTS notifications_outbox_id_unique ON notifications(outbox_id) WHERE outbox_id IS NOT NULL;
-- Preserve already-materialized legacy notifications during upgrade.
UPDATE notifications SET outbox_id=(
 SELECT o.id FROM session_notification_outbox o
 WHERE notifications.id=substr(o.id,1,8)||'-'||substr(o.id,9,4)||'-'||substr(o.id,13,4)||'-'||substr(o.id,17,4)||'-'||substr(o.id,21,12)
 AND notifications.user_id=o.user_id AND notifications.activity_id=o.activity_id
 AND notifications.kind=o.kind AND notifications.title=o.title
 AND notifications.message=o.message AND notifications.created_at=o.created_at
 LIMIT 1
) WHERE outbox_id IS NULL AND EXISTS (
 SELECT 1 FROM session_notification_outbox o
 WHERE notifications.id=substr(o.id,1,8)||'-'||substr(o.id,9,4)||'-'||substr(o.id,13,4)||'-'||substr(o.id,17,4)||'-'||substr(o.id,21,12)
 AND notifications.user_id=o.user_id AND notifications.activity_id=o.activity_id
 AND notifications.kind=o.kind AND notifications.title=o.title
 AND notifications.message=o.message AND notifications.created_at=o.created_at
);
