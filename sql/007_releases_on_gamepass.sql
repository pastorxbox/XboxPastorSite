SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE gp_releases
    ADD COLUMN on_gamepass TINYINT(1) NOT NULL DEFAULT 0 AFTER body;

UPDATE gp_releases
SET on_gamepass = 1
WHERE on_ultimate = 1
   OR on_premium = 1
   OR on_essential = 1
   OR on_pc = 1;
