# GoTry Laravel setup on cPanel

## Tonight's environment preparation

1. Use PHP **8.3 or newer**, Composer 2, MySQL and Apache rewriting, as for Music Studio. Enable Laravel's standard PHP extensions and `pdo_mysql`; enable `pdo_sqlite` for the isolated test suite.
2. Create a **separate empty MySQL database and user** for GoTry. Grant that user access to that database. Do not reuse Music Studio's database or apply the legacy `migrations/*.sql` files.
3. Create the chosen GoTry subdomain. Its document root must point to the checkout's **`public/`** directory. Keep `.env`, `vendor`, `storage` and the source tree outside the served document root. Use HTTPS.
4. Clone the repository's `dev` branch into your chosen directory. Substitute your actual directory below; do not use the Music Studio checkout.

```bash
git clone --branch dev git@github.com:mastersreacademy-code/gotry.git gotry
cd gotry
cp .env.example .env
```

Set these values in `.env` using cPanel File Manager:

```dotenv
APP_NAME=GoTry
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-GOTRY-SUBDOMAIN
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=YOUR_CPANEL_DATABASE
DB_USERNAME=YOUR_CPANEL_DATABASE_USER
DB_PASSWORD="YOUR_DATABASE_PASSWORD"
SESSION_DRIVER=database
SESSION_COOKIE=gotry_session
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
MAIL_MAILER=log
```

Keep database credentials and APP_KEY out of GitHub. GoTry's cookie name is separate from Music Studio's. Do not set a shared parent-domain SESSION_DOMAIN.

## First installation and verification

**Verified locally:** PHP 8.4.27, Composer 2.11.1, Laravel 13.40.0; 33 feature tests / 527 assertions passed, including SQLite migrations, database sessions/cache, CSRF and role isolation. Strict Composer validation, PHP lint, route loading and Blade compilation passed. Dependencies target PHP 8.3.33. Frontend pricing tests (5) and localhost HTTP group/individual flows also pass. All migrations and real InnoDB concurrency/audit rollback checks pass on isolated MariaDB 11.8.6. Parent HTTP signup/registration/withdrawal/restore/privacy checks pass. The actual cPanel database version and Apache remain unverified; Chromium launch was blocked by workspace socket restrictions, so browser E2E is not claimed. This branch is a development foundation; confirm the checks below before exposing accounts to other users.

A Composer lock is included. First install the locked dependencies including dev dependencies, so the feature tests can run. This does not migrate the database or generate accounts automatically:

```bash
composer install --prefer-dist --no-interaction
composer validate --strict
php artisan key:generate
php artisan test
```

The test configuration forcibly uses SQLite `:memory:` plus array cache/sessions, not the configured MySQL database. If PDO SQLite is unavailable, enable it before running tests. Use `composer install` for future updates; intentional dependency upgrades should go through a tested PR with an updated lock file.

After tests pass, initialize the **new GoTry database**:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan migrate:status
php artisan gotry:check-database
php artisan config:cache
php artisan view:cache
```

`storage/` and `bootstrap/cache/` must be writable by your cPanel PHP user. Use the hosting account's ownership/permissions; do not make them world writable. Do not regenerate APP_KEY on later deployments.

Check `/health`, `/account` and `/register`. Create synthetic parent and provider accounts, sign out/in, confirm each lands on its own dashboard and receives 403 on the other role's dashboard and `/admin`. Verify noindex response headers and that `.env` is not accessible through the web. No admin account is seeded and there is no default password. To create a NEW admin interactively after migration, run `php artisan gotry:create-admin`. Passwords are entered in hidden prompts; the command does not promote or overwrite existing accounts.

Providers can create an activity through `/provider/new`, review pricing and submit for approval. Admins review at `/admin`; approved listings appear at `/activities`. Parents can register interest on an approved activity and withdraw/restore from `/parent`. Group capacity is enforced transactionally; individual trials collect enquiries. Session management, confirmations and payments are not yet available. Legacy D1 users cannot sign in to the new database; there is no import in this batch. Keep the old service unchanged until migration verification and an explicit cutover.

## Subsequent manual updates

For later updates, use:

```bash
cd /YOUR/ACTUAL/GOTRY/PATH
bash deploy.sh
```

The script accepts only `dev`, rejects an unclean checkout, pulls fast-forward, installs locked Composer dependencies, clears caches, applies new Laravel migrations, verifies required tables use InnoDB, and rebuilds config/view caches. It never runs Node or Wrangler. Back up an existing database before migration updates. No deployment has been performed by the migration author.

## Next migration batches

1. Port multi-session management, confirmation/cancellation, rescheduling and transfers.
2. Add the notification outbox and provider/admin lifecycle views, with concurrency tests.
3. Verify the actual cPanel database/Apache setup and desktop/mobile browser flows.
4. Add recovery/email flows and a controlled legacy-data import if required.

## Parent registration migration

Run `php artisan migrate --force` to apply `2026_11_10_000003_create_parent_interests.php`, then `php artisan gotry:check-database`. New MySQL tables explicitly use InnoDB. The check refuses missing or non-InnoDB tables; it does not convert existing engines or alter data. See [parent-registration.md](parent-registration.md) for the supported flow and tests.
