SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_translations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type ENUM('gamepass', 'event', 'history') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    lang ENUM('en', 'es') NOT NULL,
    title VARCHAR(500) NOT NULL DEFAULT '',
    body MEDIUMTEXT NULL,
    context VARCHAR(500) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_calendar_translation (entity_type, entity_id, lang),
    INDEX idx_calendar_translation_lookup (entity_type, entity_id, lang, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
