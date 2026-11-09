# Local tests without Cloudflare or GitHub Actions

Use a local checkout of the repository with Python 3 (SQLite built in) and Node 20+.
No Wrangler, Cloudflare account, Playwright, ZIP or Codespaces is required for these tests.

## Run all available Python SQLite smoke tests

```bash
set -o pipefail
for test in tests/*sqlite*.py; do
  echo "=== $test ==="
  python3 "$test" || exit 1
done
```

Also run the existing SQLite-focused files that do not contain `sqlite` in their filenames:

```bash
python3 tests/provider_roster_privacy_sqlite.py
python3 tests/roster_privacy_sqlite_smoke.py
python3 tests/transfer_duplicate_guard_sqlite_smoke.py
python3 tests/reactivation_idempotency_sqlite_smoke.py
```

The tests that load every SQL migration from `migrations/` (including `tests/multi_session_sqlite_smoke.py`, `tests/session_capacity_sqlite_smoke.py` and `tests/concurrent_capacity_sqlite_smoke.py`) validate the complete chain from an empty database. The concurrency smoke uses a temporary WAL SQLite database and multiple connections; it is not a guarantee of D1 production behavior.

## Run Node tests

```bash
node --test tests/*.test.js
```

If a test fails, keep its complete traceback/output. Distinguish an outdated source-string assertion from an actual API or SQL failure; never change a production guard merely to satisfy a stale assertion. Do not run `npm run deploy` or `wrangler deploy` as part of these checks.

These commands run locally and do not configure GitHub Actions or deploy anything.
