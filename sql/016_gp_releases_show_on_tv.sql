SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE gp_releases
    ADD COLUMN show_on_tv TINYINT(1) NOT NULL DEFAULT 0 AFTER trailer_url;
