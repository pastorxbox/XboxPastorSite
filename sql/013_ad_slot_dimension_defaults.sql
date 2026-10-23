SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

UPDATE ad_slots SET width_px = 1200, height_px = 250 WHERE slug = 'header';
UPDATE ad_slots SET width_px = 340, height_px = 280 WHERE slug IN ('sidebar_top', 'sidebar_bottom');
UPDATE ad_slots SET width_px = 832, height_px = 250 WHERE slug IN ('in_feed', 'in_article');
