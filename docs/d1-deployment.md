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
