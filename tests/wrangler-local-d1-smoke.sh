#!/usr/bin/env bash
# Local-only D1 smoke: never invokes --remote or deploy.
set -euo pipefail
cd "$(dirname "$0")/.."
CONFIG=wrangler.ci.jsonc
DB=gotry-ci
if [[ ! -x node_modules/.bin/wrangler ]]; then
  echo "Install dependencies first: npm ci" >&2
  exit 2
fi
WRANGLER=./node_modules/.bin/wrangler
"$WRANGLER" d1 migrations apply "$DB" --local --config "$CONFIG"
# Use D1 itself (Miniflare SQLite), not a Python approximation.
"$WRANGLER" d1 execute "$DB" --local --config "$CONFIG" --command "PRAGMA foreign_key_check;"
"$WRANGLER" d1 execute "$DB" --local --config "$CONFIG" --command "SELECT name FROM sqlite_master WHERE type='table' AND name IN ('users','activities','trial_sessions','interests','notifications','session_notification_outbox') ORDER BY name;"
"$WRANGLER" d1 execute "$DB" --local --config "$CONFIG" --command "SELECT name FROM sqlite_master WHERE type='trigger' AND name IN ('session_parent_outbox','session_provider_outbox','interest_registered_outbox','interest_changed_outbox','interest_session_capacity_insert','interest_session_capacity_update') ORDER BY name;"
"$WRANGLER" d1 execute "$DB" --local --config "$CONFIG" --command "SELECT version, name FROM d1_migrations ORDER BY version;"
echo "D1 migration commands completed. Review output for six tables, six triggers, migrations 0001-0009 and no foreign key violations."
