SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE gp_releases
    ADD COLUMN play_anywhere TINYINT(1) NOT NULL DEFAULT 0 AFTER platform_cloud,
    ADD COLUMN supports_pt_br TINYINT(1) NOT NULL DEFAULT 0 AFTER play_anywhere;
