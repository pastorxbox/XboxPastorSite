SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE gp_releases
    ADD COLUMN store_url VARCHAR(500) NULL AFTER cover_url;
