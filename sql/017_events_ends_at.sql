-- Eventos multi-dia: data de término
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE calendar_events
    ADD COLUMN ends_at DATETIME NULL AFTER starts_at;

UPDATE calendar_events
SET ends_at = starts_at
WHERE ends_at IS NULL;

ALTER TABLE calendar_events
    ADD INDEX idx_events_status_ends (status, ends_at);
