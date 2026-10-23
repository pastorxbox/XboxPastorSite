SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE ad_slots
    ADD COLUMN width_px INT UNSIGNED NOT NULL DEFAULT 0 AFTER enabled,
    ADD COLUMN height_px INT UNSIGNED NOT NULL DEFAULT 0 AFTER width_px;

UPDATE ad_slots SET width_px = 1200, height_px = 250 WHERE slug = 'header';
UPDATE ad_slots SET width_px = 340, height_px = 280 WHERE slug = 'sidebar_top';
UPDATE ad_slots SET width_px = 340, height_px = 280 WHERE slug = 'sidebar_bottom';
UPDATE ad_slots SET width_px = 832, height_px = 250 WHERE slug = 'in_feed';
UPDATE ad_slots SET width_px = 832, height_px = 250 WHERE slug = 'in_article';
