SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tv_video_translations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    video_id INT UNSIGNED NOT NULL,
    lang ENUM('en', 'es') NOT NULL,
    tag VARCHAR(40) NOT NULL DEFAULT '',
    comment VARCHAR(255) NOT NULL DEFAULT '',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_tv_video_translation (video_id, lang),
    INDEX idx_tv_video_translation_lookup (video_id, lang, status),
    CONSTRAINT fk_tv_video_translations_video
        FOREIGN KEY (video_id) REFERENCES tv_videos (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
