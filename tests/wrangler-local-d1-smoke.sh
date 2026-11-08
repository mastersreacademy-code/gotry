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
"$WRANGLER" d1 execute "$DB" --local --config "$CONFIG" --command "SELECT id, name FROM d1_migrations ORDER BY id;"
echo "D1 migration commands completed; verifying required migration and triggers."
MIGRATIONS=$("$WRANGLER" d1 execute "$DB" --local --config "$CONFIG" --command "SELECT name FROM d1_migrations ORDER BY id;")
echo "$MIGRATIONS" | grep -q '0009_session_capacity_guards.sql' || { echo "Missing migration 0009" >&2; exit 1; }
TRIGGERS=$("$WRANGLER" d1 execute "$DB" --local --config "$CONFIG" --command "SELECT name FROM sqlite_master WHERE type='trigger';")
for name in session_parent_outbox session_provider_outbox interest_registered_outbox interest_changed_outbox interest_session_capacity_insert interest_session_capacity_update; do
  echo "$TRIGGERS" | grep -q "$name" || { echo "Missing trigger $name" >&2; exit 1; }
done
echo "PASS: Wrangler local D1 migrations 0001-0009 and six required triggers."
