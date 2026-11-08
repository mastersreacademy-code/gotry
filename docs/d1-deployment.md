# GoTry D1 setup — manual only

The repository deliberately has **no production D1 database ID**. No remote database is provisioned or migrated by GitHub. To enable authenticated workflows:

1. In Cloudflare Dashboard → Storage & databases → D1, create a database named `gotry`.
2. In the Worker settings → Bindings, add a **D1 database binding** named exactly `DB`, pointing to that database. This must match `env.DB` in `src/api.js`.
3. Apply migrations **0001_initial.sql**, **0002_interest.sql**, and **0003_auth_throttle.sql** to the selected database in order, using Cloudflare D1's SQL console or Wrangler. Back up existing data before applying to an existing database.
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
2. Confirm D1 migrations were applied, including `0003_auth_throttle.sql` before testing login.
3. Test parent/provider/admin authorization, registration, withdrawal, maximum capacity, and the admin lifecycle actions with synthetic data.
4. Verify desktop and mobile layouts and confirm no production secrets appear in GitHub.
5. Deploy manually only after these checks; a merge does not apply migrations automatically.
