-- Legacy local timestamps remain for backwards compatibility; new sessions store UTC.
ALTER TABLE trial_sessions ADD COLUMN starts_at_utc TEXT;
ALTER TABLE trial_sessions ADD COLUMN ends_at_utc TEXT;
ALTER TABLE trial_sessions ADD COLUMN timezone TEXT NOT NULL DEFAULT 'Australia/Sydney';
ALTER TABLE trial_sessions ADD COLUMN updated_at TEXT NOT NULL DEFAULT '1970-01-01 00:00:00';
CREATE INDEX IF NOT EXISTS trial_sessions_utc ON trial_sessions(starts_at_utc);
