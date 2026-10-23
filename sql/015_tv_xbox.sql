SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tv_videos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    youtube_url VARCHAR(500) NOT NULL,
    youtube_id VARCHAR(20) NOT NULL,
    tag VARCHAR(40) NOT NULL DEFAULT '',
    comment VARCHAR(255) NOT NULL DEFAULT '',
    show_on_tv TINYINT(1) NOT NULL DEFAULT 1,
    gp_release_id INT UNSIGNED NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tv_videos_gp_release (gp_release_id),
    INDEX idx_tv_videos_player (status, show_on_tv),
    INDEX idx_tv_videos_youtube (youtube_id),
    CONSTRAINT fk_tv_videos_gp_release
        FOREIGN KEY (gp_release_id) REFERENCES gp_releases (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
