# Local D1 integration test plan
No Cloudflare production database should be created or modified by CI.
Use a disposable Wrangler local D1 binding with a synthetic database ID and run migrations locally.
Tests should exercise registration, login, role isolation, provider submission, admin approval, and audit history.
Admin users must be provisioned by a test-only fixture, never by the public registration API.
Do not put a production D1 database ID, Cloudflare API token, or real email address in CI.
Production auto-deploy remains unverified; do not merge this branch solely on green CI.
