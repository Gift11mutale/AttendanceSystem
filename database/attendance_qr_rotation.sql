-- Run once against the application database.
-- Existing sessions use the 15-second default.
ALTER TABLE attendance_sessions
    ADD COLUMN qr_refresh_seconds INT NOT NULL DEFAULT 15 AFTER qr_token;
