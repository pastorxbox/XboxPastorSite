SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE gp_releases
    ADD COLUMN platform_xbox_one TINYINT(1) NOT NULL DEFAULT 0 AFTER platform_console,
    ADD COLUMN platform_handheld TINYINT(1) NOT NULL DEFAULT 0 AFTER platform_xbox_one;
