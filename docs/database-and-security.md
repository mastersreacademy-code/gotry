# GoTry D1 + authentication foundation
## D1 setup (not provisioned)
Create a D1 database through Cloudflare when approved. Add its database ID as a `DB` binding in wrangler.jsonc (binding name must be DB). Run `wrangler d1 migrations apply <database-name> --local` for local tests. Do not run --remote against production until explicitly approved.
## Roles
- Public signup allows parent or provider only; no self-service admin role.
- Admin role must be provisioned through an audited, separate privileged operation (not implemented).
- Provider creates activities in pending_approval state; only admins may approve/reject.
- No payment or booking API.
## Security limitations before production
- Registration/login rate limits, bot challenge, email verification, password reset and session rotation policy are NOT yet implemented.
- Provider submissions should have additional moderation, field constraints and audit logging.
- Front-end wizard currently saves locally and is NOT connected to these APIs.
- D1 binding is intentionally not configured; API returns 503 until database is bound.
- Cookies are HttpOnly/Secure/SameSite=Lax; mutations require same-origin Origin and JSON.
- No Cloudflare deployment is configured in this PR.
