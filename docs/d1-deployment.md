# GoTry D1 setup — manual only

The repository deliberately has **no production D1 database ID**. No remote database is provisioned or migrated by GitHub. To enable authenticated workflows:

1. In Cloudflare Dashboard → Storage & databases → D1, create a database named `gotry`.
2. In the Worker settings → Bindings, add a **D1 database binding** named exactly `DB`, pointing to that database. This must match `env.DB` in `src/api.js`.
3. Apply migrations **0001_initial.sql**, **0002_interest.sql**, and **0003_auth_throttle.sql**, **0004_notifications.sql**, **0005_trial_sessions.sql** to the selected database in order, using Cloudflare D1's SQL console or Wrangler. Back up existing data before applying to an existing database.
4. Deploy the current `main` source manually, then check `/health`, `/account`, `/provider`, `/parent`, `/admin`, and `/activities`.
5. Do not create admin accounts through public registration; a privileged manual provisioning process is still required.
6. Do not use real credentials or real children's personal data yet. Login throttling now uses D1, but registration abuse prevention, email verification, privacy and retention policies remain incomplete.

To test locally, use `wrangler.ci.jsonc` and `wrangler d1 migrations apply gotry-ci --local --config wrangler.ci.jsonc`. Do **not** use `--remote` for tests.

**Important:** GitHub Actions CI is intentionally disabled at the owner's request. A merge to main might still trigger Cloudflare auto-deploy if that Git integration remains enabled; verify the Cloudflare dashboard if you want manual-only deployments.

## Trial lifecycle (manual admin control)

- Providers submit activities for admin approval. Approved trials become `pending_registrations`.
- Parents can register interest while there is capacity; the backend enforces maximum capacity using a conditional D1 statement.
- Admin can mark a trial `proceeding_pending_payments` only after minimum interest is reached. **This is an administrative status, not proof of payment.** No PayPal or payment processing is enabled.
- Admin can mark `cancelled_low_numbers` only if interest is below minimum and the registration deadline has passed.
- Providers may cancel their own activities while pending approval or registrations.
- This prototype does not send notifications, refund payments, guarantee booking, or collect real children's data safely. Avoid live users until the security/privacy review is complete.

## Manual checks before deployment

1. Check `npm run check` locally or in a disposable environment (GitHub Actions remains disabled).
2. Confirm D1 migrations were applied, including `0003_auth_throttle.sql` before testing login and `0004_notifications.sql` before testing submissions or approvals.
3. Test parent/provider/admin authorization, registration, withdrawal, maximum capacity, and the admin lifecycle actions with synthetic data.
4. Verify desktop and mobile layouts and confirm no production secrets appear in GitHub.
5. Deploy manually only after these checks; a merge does not apply migrations automatically.

## In-app notifications

Migration `0004_notifications.sql` adds per-account notifications. New activity submissions, admin approval decisions, interest registrations, provider cancellation, and trial minimum decisions create notifications. The `/notifications` page lists only the signed-in user's notifications and allows marking them read. No emails or push notifications are sent.

**Before manual deployment:** apply migration 0004. If it is missing, activity submissions and status changes can fail after the main database write. Test on a disposable D1 instance first. These operations are not yet transactionally coupled; production-grade notification reliability requires an outbox or equivalent.

**Privacy:** Do not use actual children's names or ages during prototype testing. This version is not ready for public registrations. The app currently lacks verified emails, full abuse prevention, account recovery, privacy retention/deletion controls and independent end-to-end validation.

## Multi-session trial schedules (migration 0005)

1. Back up D1 before applying `migrations/0005_trial_sessions.sql`. It creates `trial_sessions` and adds `session_id` to `interests`.
2. A Provider can add multiple sessions to an activity **while it is pending admin approval** using the Provider Dashboard. Sessions require a future start/end, venue, and minimum/maximum participants.
3. Once the activity is approved, parents choose a session when expressing interest. Each session has its own capacity. Existing legacy activities without sessions still use the original single-trial date.
4. Admin reviews scheduled sessions separately. Confirming a session requires minimum interest. Cancelling for low numbers is allowed only after the session date and while the minimum has not been met.
5. Parents see the selected session's date and venue. Notifications appear in-app only.

**Current limitations:** one child nickname can have only one active interest per activity, even if that activity has multiple sessions; to switch sessions the parent must withdraw first. Session start/end are local wall-clock strings without a timezone identifier; do not use the schedule for real bookings until timezone handling is added. No automated attendance, emails, payments, or calendar invitations. The local SQL smoke check does not substitute for a full D1/Playwright end-to-end test.

## Sydney timezone, rosters and rescheduling (migration 0006)

Before manual Cloudflare deployment, back up D1 and apply `migrations/0006_sydney_schedule.sql` after 0001–0005. New sessions store both Sydney wall time and UTC instants, with timezone `Australia/Sydney`. DST gap and duplicate-hour times are rejected. Existing sessions have null UTC fields until explicitly rescheduled; review and migrate those records before production use.

Provider Dashboard now supports owner-only rosters and rescheduling of open sessions while the activity awaits approval or registrations. Parents with active interest receive an in-app schedule-change notification. Roster data is not public. No emails or payments are sent. Session cancellation/confirmation remains admin-controlled.

**Testing:** run `npm run check` and local Wrangler D1 integration tests before deployment. The code includes Sydney DST unit tests, but end-to-end D1 and browser tests must be independently verified. Prototype is not ready for real children's personal information.

## Local verification after PR #11

No GitHub Actions CI is required. In a checkout with Node and Wrangler installed, run:

```sh
npm install
npm run check
python3 tests/sqlite_d1_smoke.py
npx wrangler d1 migrations apply gotry-ci --local --config wrangler.ci.jsonc
```

The Python script executes **all SQL migration files** against an in-memory SQLite database and checks interest capacity, admin confirmation and closed-session rejection. The Wrangler command applies migrations to an actual **local D1** instance; it does not touch production. It must be run from a repository checkout. Browser E2E tests require a locally running Worker with the local D1 binding.

**Safety fixes:** Activity Discovery no longer treats aggregate interest across multiple sessions as a single capacity limit. The public sessions endpoint hides unapproved activity session counts. Only sessions with UTC instants are available for new registrations; older records created before migration 0006 must be reviewed/rescheduled to populate UTC. Provider cancellation of open sessions notifies interested parents. Admin decisions recheck minimum numbers atomically.

**Known limitation:** Notifications are written after status changes rather than through a transactional outbox; a failed notification insert can leave the status updated. Real children and payments remain out of scope.

## Migration 0007 — durable session notification outbox

Back up D1 and apply `migrations/0007_session_outbox.sql` **after migrations 0001–0006**. SQLite triggers enqueue one durable event per interested parent and the provider **in the same transaction** as a trial session's status, venue, or schedule change. If the enqueue fails, the session update rolls back.

When an authenticated user opens `GET /api/notifications`, D1 `batch()` copies that user's pending events into notifications and acknowledges them in one transaction. The notification ID is deterministically derived from the outbox event ID; repeated requests cannot duplicate messages. This is **pull-based in-app delivery**, not an email sender or background job. Users receive notifications when they next open the notifications screen. Unread messages are still visible through the existing interface.

Run `python3 tests/outbox_sqlite_smoke.py` and `python3 tests/sqlite_d1_smoke.py` after checkout. These run all migrations on local SQLite, validate trigger delivery, idempotency, per-user isolation, and rollback. They are not full Wrangler D1/Playwright E2E tests.

Remaining work: transactionally link parent interest changes to notifications, confirm production migration on a disposable D1 database, test browser flows end-to-end, implement notification retention and privacy review before using real child data.

## Migration 0008 — multi-session interests and parent transfers

**Back up the D1 database before applying this migration.** Apply `migrations/0008_multi_session_interests.sql` after `0007`. The migration rebuilds the interests table while preserving existing IDs, registration statuses and session links. It replaces the old unique constraint (one child per activity) with one child **per trial session**; multiple sessions are now supported.

The Parent Dashboard offers a **Change session** action. The API verifies parent ownership, target activity, available places, future Sydney time, status, age range and duplicate registration before updating the session ID. Parents can still withdraw one specific registration. SQLite triggers enqueue notifications for registration, restoration, transfer and withdrawal to the parent and provider, transactionally with the interest change. Outbox delivery happens when the recipient opens Notifications.

Run `python3 tests/multi_session_sqlite_smoke.py`, `python3 tests/outbox_sqlite_smoke.py`, `python3 tests/sqlite_d1_smoke.py` and `npm run check` from a checkout. Then run the Wrangler local D1 migration and browser tests before any production deployment. **These test files are not evidence of execution**; their results must be checked in the local environment. Real child data and payments are not production-ready.
