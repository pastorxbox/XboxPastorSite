SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE gp_releases
    ADD COLUMN trailer_url VARCHAR(500) NULL AFTER store_url;
