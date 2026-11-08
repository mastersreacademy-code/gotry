# GoTry D1 setup — manual only

The repository deliberately has **no production D1 database ID**. No remote database is provisioned or migrated by GitHub. To enable authenticated workflows:

1. In Cloudflare Dashboard → Storage & databases → D1, create a database named `gotry`.
2. In the Worker settings → Bindings, add a **D1 database binding** named exactly `DB`, pointing to that database. This must match `env.DB` in `src/api.js`.
3. Apply migrations **0001_initial.sql** and **0002_interest.sql** to the selected database in order, using Cloudflare D1's SQL console or Wrangler. Back up existing data before applying to an existing database.
4. Deploy the current `main` source manually, then check `/health`, `/account`, `/provider`, `/parent`, `/admin`, and `/activities`.
5. Do not create admin accounts through public registration; a privileged manual provisioning process is still required.
6. Do not use real credentials or real children's personal data yet. Registration rate limiting, verification, privacy and retention policies remain incomplete.

To test locally, use `wrangler.ci.jsonc` and `wrangler d1 migrations apply gotry-ci --local --config wrangler.ci.jsonc`. Do **not** use `--remote` for tests.

**Important:** GitHub Actions CI is intentionally disabled at the owner's request. A merge to main might still trigger Cloudflare auto-deploy if that Git integration remains enabled; verify the Cloudflare dashboard if you want manual-only deployments.
