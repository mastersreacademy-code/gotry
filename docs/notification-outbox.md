# Transactional in-app notification outbox

The inbox is at `/notifications`. No email, push, cron or persistent queue worker is used. Opening the page materializes up to 50 pending messages for the current user and shows remaining queued messages separately. Refresh loads another batch; marking read is an explicit, CSRF-protected POST scoped to the recipient.

Interest and activity audit events are the source identities. An outbox row is unique per `(source_key, recipient_id)` and is written inside the same transaction as the source change and audit record. Failure to enqueue any recipient rolls back the entire change, including earlier recipient rows. Immediate retries do not create new source events or duplicate messages. Existing pre-migration events are not automatically backfilled.

Delivery locks that recipient's pending outbox rows, creates/reuses a notice by unique `outbox_id`, verifies recipient/activity/kind/title/message/original timestamp, and then acknowledges the source row in the same transaction. A composite foreign key binds each inbox row to the outbox ID **and its recipient**, preventing cross-recipient materialization even at the database layer.

Insertion, mismatched payload, ID collision or acknowledgement failures leave the outbox pending rather than losing it. Transaction rollback removes partial notice inserts. A subsequent retry can recover a transient failure. A persistently mismatched payload is an integrity error requiring investigation; it is never silently acknowledged. Matching pre-existing notices retain their ID and read state during recovery.

Parent/provider copies contain no child names, ages or contact details. Recipient IDs and event references remain internal; there is no raw-outbox endpoint or client-selected recipient parameter. Notification links use fixed, authorized dashboard routes and all displayed text is Blade-escaped.

The feature tests exercise partial enqueue rollback, source/audit rollback, duplicate source events, delivery/ack failure recovery, cross-recipient foreign-key rejection, payload mismatch, notice-ID collision, bounded delivery, read idempotence, CSRF and privacy. Real MariaDB workers verified eight simultaneous inbox requests produced exactly one notice per source and did not acknowledge another user's pending messages. Actual InnoDB trigger failures verified acknowledgement rollback/recovery.
