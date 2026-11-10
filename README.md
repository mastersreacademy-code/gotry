# GoTry

Development is moving to **Laravel 13 / PHP 8.3+ / MySQL** on cPanel, on the `dev` branch. No Node installation or frontend build is required on the hosting server. Start with [the cPanel setup guide](docs/cpanel-laravel.md).

## Migration status

- Laravel application entry points, configuration, MySQL-compatible auth/session/cache migrations, Blade pages and parent/provider authentication are implemented.
- The provider five-step wizard, session-scoped drafts, trial/regular format separation, schedule choices, pricing/discount previews, provider declarations and idempotent submission are implemented.
- Providers can see their own activities. Admins can approve/reject with a reason; transitions and their audit events commit atomically. Approved activities have public pages that exclude private business contacts. Admin self-registration is forbidden; `php artisan gotry:create-admin` creates a new admin interactively.
- Existing Worker code in `src/`, D1 SQL in `migrations/`, earlier Node/Python tests and deployment documentation are **legacy references**, not the PHP backend. `php artisan migrate` uses only `database/migrations/`.
- Parents can register interest, withdraw and explicitly restore it from their own dashboard. Group capacity is enforced under an activity-row lock; each state change and versioned audit event commit together. Individual trials collect separate enquiries without a shared one-seat cap.
- The super-admin workspace includes overview metrics, searchable review/account lists, protected suspension/reactivation, read-only audit history, group proceeding/cancellation controls and an operations guide. Below-minimum withdrawals return proceeding groups to review.
- In-app notifications use a transactional, recipient-scoped outbox and idempotent inbox delivery. Registration changes and admin decisions enqueue notices atomically with their audit records.
- Multi-session appointments/transfers, payments, account recovery and email verification remain. No automatic import of D1 accounts or activity data is included.
- Noindex is enforced in Laravel middleware, page metadata and Apache headers; robots.txt disallows crawling.

## Validation

`composer test` runs Laravel feature tests using an in-memory SQLite database. PHP 8.3+, Composer and PDO SQLite are needed for tests; PDO MySQL is needed for hosting. Verified with isolated PHP 8.4.27 and Composer 2.11.1: 54 Laravel feature tests / 728 assertions pass; PHP lint, route loading, Blade compilation and strict Composer validation pass. Composer platform targets PHP 8.3.33. Five frontend pricing tests and group/individual HTTP signup-to-submission smoke tests also pass. All five migrations and real InnoDB concurrency/audit rollback checks pass on isolated MariaDB 11.8.6. Parent HTTP checks pass with real cookies/CSRF, group capacity, withdrawal/restore, stale forms and account isolation. Native InnoDB checks also cover concurrent inbox delivery, acknowledgement rollback, composite recipient ownership, confirmation/withdrawal races and mutual admin suspensions. HTTP checks cover the admin workspace, lifecycle controls, suspension/reactivation and inbox permissions. Existing account/password preservation is covered by a migration-upgrade test. The actual cPanel database version and Apache remain unverified. Chromium could not launch because the workspace blocks a required socket, so browser layout/E2E is not claimed. Do not treat legacy Node/Python results as Laravel test results.

A verified `composer.lock` is included; use `composer install`, not `composer update`, for installation and repeat deployments. No GitHub Actions or automatic deployment is configured by this migration.

Additional local checks (not required on the cPanel host):

```bash
node --test tests/programme-pricing.test.mjs
python3 tests/http_provider_smoke.py http://127.0.0.1:8123
python3 tests/http_parent_smoke.py http://127.0.0.1:8123 database/browser-test.sqlite
GOTRY_TEST_URL=http://127.0.0.1:8123 node tests/Browser/provider-wizard.cjs
```

The HTTP/browser scripts create synthetic provider accounts and listings; use an isolated, migrated localhost database with `SESSION_SECURE_COOKIE=false` for HTTP testing. The browser script requires Playwright and a working Chromium installation.

See [parent registration and capacity](docs/parent-registration.md) for InnoDB requirements, audit semantics and the guarded concurrency test. Run `php artisan gotry:check-database` after deployment migrations to verify transactional engines.

See [super-admin operations](docs/super-admin.md) and [notification delivery](docs/notification-outbox.md). In production, the existing `admin` role is displayed as Super admin; no accounts, roles or passwords are seeded or reset by these migrations.
