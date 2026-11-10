# Parent interest registration

## Supported flow

Approved activity pages show aggregate interest and remaining group places. A parent signs in, provides a child's first name/nickname and age, reviews the details and registers free interest. This does not confirm a booking or collect payment.

The parent dashboard lists only that parent's registrations. Withdrawing requires explicit confirmation and releases a group place. Restoring requires reviewing current activity details, current availability and the child's age eligibility. Individual trials collect separate enquiries; they do not share a single group seat. Waiting lists, payment requests and multi-session scheduling remain unavailable. Super admins can now mark group trials proceeding or cancel them; minimum-loss withdrawals return proceeding trials to review. In-app notifications are queued with the source change.

Within one parent account/activity, names are whitespace-normalized and lowercased for a unique hashed child key. A repeated active submission returns the existing interest without another audit event. This nickname-based identity is not a verified global child identity. Another parent's registrations never appear on a parent dashboard or public page; providers currently receive no child roster through these pages.

## Transactional guarantees

- Register, withdraw and restore all lock the activity row first, then the relevant interest row. This serializes group-capacity decisions on InnoDB across accounts/devices; counts come from active rows rather than a separate counter.
- Group capacity comes from the saved trial maximum, not client input. The server checks approval, activity version, Sydney registration deadline, future group start and age eligibility. The deadline day is inclusive, but an already-started group trial is closed.
- Each interest has a version. A mutation increments it and inserts one audit event in the same transaction; a unique `(interest_id, version)` constraint prevents duplicate version events. Audit events contain record/actor IDs and states, not child names or ages.
- An immediate retry of a successful withdrawal/restore is harmless. Old withdrawal forms cannot undo a later restore. An old create form cannot reactivate a withdrawn registration; restoration is a separate explicit action.
- Withdrawal remains available after closure. Restoration rechecks all current acceptance rules and capacity.
- All parent write routes use CSRF, parent-only authorization and per-account throttling. Server-selected parent/activity IDs cannot be overridden by submitted fields.

## Hosting requirements

Apply `2026_11_10_000003_create_parent_interests.php` with `php artisan migrate --force`. MySQL configuration explicitly requests InnoDB. Run `php artisan gotry:check-database` after migrations: it verifies required tables are present and use InnoDB without changing data. Non-transactional engines cannot provide these guarantees. Existing table-engine conversion is not performed automatically.

## Actual verification

- 54 Laravel feature tests / 728 assertions pass on PHP 8.4.27 and SQLite, including registration/restore capacity, duplicate retries, stale versions, deadlines/ages/roles, CSRF, throttling, XSS escaping, account isolation, and audit-failure rollback for all three writes.
- All five Laravel migrations passed on isolated MariaDB 11.8.6. The real service was exercised by separate PHP processes behind a start barrier: eight parents competing for two places; eight simultaneous duplicate submissions; withdrawal versus new registration; and restore versus new registration. Capacity and audit counts remained consistent.
- MariaDB trigger failures proved registration, withdrawal and restoration—including their versions—roll back when audit insertion fails. The InnoDB deployment check passed.
- Real localhost HTTP signup/registration/withdrawal/restore checks passed with database sessions and CSRF, including parent isolation and public privacy.
- The actual cPanel MySQL/MariaDB version and Apache are not verified. Browser layout/E2E remains pending because Chromium could not launch under workspace socket restrictions in the preceding batch.

The guarded InnoDB test creates synthetic records in an already migrated **isolated localhost database whose name ends `_test`**. It refuses other environments. It temporarily creates/drops a test audit-failure trigger, so use a dedicated test database/user, never the hosting database:

```bash
APP_ENV=testing DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=gotry_interest_test DB_USERNAME=YOUR_TEST_USER DB_PASSWORD=YOUR_TEST_PASSWORD \
php tests/Concurrency/parent_interests.php
```

For HTTP verification, start a separate migrated SQLite Laravel instance with database sessions, `SESSION_SECURE_COOKIE=false` for HTTP localhost, and the exact `database/browser-test.sqlite` file. Then run:

```bash
python3 tests/http_parent_smoke.py http://127.0.0.1:8123 database/browser-test.sqlite
```
