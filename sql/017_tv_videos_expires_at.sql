SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE tv_videos
    ADD COLUMN expires_at DATE NULL DEFAULT NULL AFTER show_on_tv,
    ADD INDEX idx_tv_videos_expires (expires_at);
