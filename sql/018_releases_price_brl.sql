ALTER TABLE gp_releases
    ADD COLUMN price_brl DECIMAL(10, 2) NULL AFTER store_url;
