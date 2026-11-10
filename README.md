# GoTry

Development is moving to **Laravel 13 / PHP 8.3+ / MySQL** on cPanel, on the `dev` branch. No Node installation or frontend build is required on the hosting server. Start with [the cPanel setup guide](docs/cpanel-laravel.md).

## Migration status

- Laravel application entry points, configuration, MySQL-compatible auth/session/cache migrations, Blade pages and parent/provider authentication are implemented.
- Role-protected parent/provider/admin dashboard shells are implemented. Admin self-registration is forbidden.
- Existing Worker code in `src/`, D1 SQL in `migrations/`, Node/Python tests and earlier documentation are **legacy references**, not the PHP backend. `php artisan migrate` uses only `database/migrations/`.
- Activities, provider wizard, approvals, registration capacity, schedules, notification outbox, account recovery and email verification still require migration. No automatic import of D1 accounts or activity data is included.
- Noindex is enforced in Laravel middleware, page metadata and Apache headers; robots.txt disallows crawling.

## Validation

`composer test` runs Laravel feature tests using an in-memory SQLite database. PHP 8.3+, Composer and PDO SQLite are needed for tests; PDO MySQL is needed for hosting. Verified with isolated PHP 8.4.27 and Composer 2.11.1: 7 Laravel feature tests / 82 assertions pass; PHP lint, route loading, Blade compilation and strict Composer validation pass. Composer platform targets PHP 8.3.33. MySQL and cPanel/Apache have not yet been exercised. Do not treat legacy Node/Python results as Laravel test results.

A verified `composer.lock` is included; use `composer install`, not `composer update`, for installation and repeat deployments. No GitHub Actions or automatic deployment is configured by this migration.
