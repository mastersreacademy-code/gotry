CREATE TABLE IF NOT EXISTS auth_attempts (
 email_hash TEXT PRIMARY KEY,
 attempts INTEGER NOT NULL DEFAULT 0,
 window_started INTEGER NOT NULL,
 blocked_until INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS auth_attempts_blocked ON auth_attempts(blocked_until);
