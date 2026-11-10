# Super-admin workspace

The existing `admin` role is displayed as **Super admin**. The migration does not create, rename or reset the user's existing accounts. Migrations 000004/000005 add notification and access-management tables/columns. Existing users default to active with access version 1; their names, emails, roles and password hashes are preserved.

| Route | Purpose |
| --- | --- |
| `/admin` | Overview metrics and pending review queue |
| `/admin/activities` | Search and filter activities by current status |
| `/admin/activities/{id}` | Review business/trial/pricing/declarations and make version-checked decisions |
| `/admin/users` | Filter accounts by name/email, role and access; suspend/reactivate with a reason |
| `/admin/audit` | Read-only activity, interest and account access history |
| `/admin/guide` | In-app operations guide and feature boundaries |
| `/notifications` | Current signed-in user's own inbox |

Parent/provider accounts cannot access admin pages or actions. Public registration cannot create admins. Additional admins still use the interactive `php artisan gotry:create-admin` command, without a default password.

## Account controls

Access changes retain account roles and ownership. Suspending an account deletes its GoTry database sessions and the middleware blocks its next authenticated request; login also requires active access. In-flight requests are not cancelled. Provider suspension prevents new registrations and hides its activities from discovery. Reactivation restores access without changing credentials.

An admin cannot suspend their own account. Account actions lock admin identities in a consistent order and recheck the actor, so two admins cannot simultaneously suspend each other. Stale access versions are rejected. Account state, version, session deletion and audit record commit together. Existing table/index/record ownership is retained; no account deletion or role reassignment is exposed.

## Activity decisions

Approval/rejection requires a pending listing and matching activity version. Rejection requires feedback; approval refuses unavailable/expired trials and suspended providers.

For approved **group** trials, marking proceeding requires current minimum interest and a future start. This sets `proceeding_pending_payments`, not paid status. Below-minimum cancellation is allowed only after the registration deadline. Admin cancellation requires a reason and closes further interest registration. All actions use the activity row lock shared with parent writes, plus version checks and transactional audit/outbox records.

If a subsequent withdrawal takes a proceeding group below minimum, the same transaction resets it to `pending_registrations`, increments its version, records `minimum_lost` and queues anonymous notices for the provider and remaining active parents. If the deadline has passed, registrations remain closed and the admin can cancel below minimum. Stale confirmation forms cannot override that newer state.

Individual trials collect enquiries; group-minimum confirmation does not apply. Per-appointment scheduling, transfers, payment checkout and payment reconciliation remain outside this version. There is no manual "mark paid" control that could imply a payment was verified.

## Notifications and audit privacy

Register/withdraw/restore events queue a parent receipt and anonymous provider update. Approve/reject queue provider notices only. Proceeding/cancellation/minimum-loss events notify the provider and each currently active parent once, even when a parent has multiple children. Withdrawn families are excluded from lifecycle broadcasts. Free-form review/cancellation reasons stay on authorized pages and in admin audit history, not in copied notification messages. Child names/ages are not copied into notices or interest audit records.

## Verified checks

- 54 Laravel tests / 728 assertions passed with PHP 8.4.27 and SQLite.
- Existing admin/provider/parent account data and password hashes were checked before and after the access migration.
- All five migrations and InnoDB table checks passed on a fresh, isolated MariaDB 11.8.6 database.
- Separate PHP workers passed capacity, duplicate registration, inbox delivery, minimum-confirmation/withdrawal and mutual admin-suspension races.
- Real localhost HTTP checks passed admin overview/review, approval, suspension/reactivation, lifecycle decisions, minimum loss, audit privacy, inbox delivery/read scope and CSRF.
- Blade compilation, PHP lint, Composer validation and deployment-script syntax passed.

The actual cPanel database/Apache and browser visual layout have not been independently verified here. No hosting update, seed or deployment was performed. Keep noindex during testing.
