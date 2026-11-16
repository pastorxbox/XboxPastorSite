-- Fase 2 — Catálogo Xbox (Manager → site)
-- Aditivo: não remove colunas; não altera price_brl existente.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_games (
  id VARCHAR(64) NOT NULL,
  manager_game_id VARCHAR(64) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  title VARCHAR(255) NOT NULL,
  synopsis MEDIUMTEXT NULL,
  cover_url VARCHAR(1024) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
  franchise_name VARCHAR(255) NULL,
  franchise_slug VARCHAR(190) NULL,
  synced_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_games_manager (manager_game_id),
  UNIQUE KEY uq_catalog_games_slug (slug),
  KEY idx_catalog_games_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS catalog_store_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  catalog_game_id VARCHAR(64) NOT NULL,
  store_product_id VARCHAR(64) NOT NULL,
  store_url VARCHAR(1024) NULL,
  edition_name VARCHAR(255) NULL,
  product_type VARCHAR(32) NOT NULL DEFAULT 'UNKNOWN',
  market VARCHAR(8) NOT NULL DEFAULT 'BR',
  currency VARCHAR(8) NOT NULL DEFAULT 'BRL',
  active TINYINT(1) NOT NULL DEFAULT 1,
  price_current DECIMAL(12,2) NULL,
  price_reference DECIMAL(12,2) NULL,
  price_status VARCHAR(32) NULL,
  price_checked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_product_store (store_product_id, market),
  KEY idx_catalog_product_game (catalog_game_id),
  CONSTRAINT fk_catalog_product_game
    FOREIGN KEY (catalog_game_id) REFERENCES catalog_games (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preço informativo do catálogo (espelho). NÃO usar para sobrescrever gp_releases.price_brl no MVP.
CREATE TABLE IF NOT EXISTS catalog_price_mirror (
  store_product_id VARCHAR(64) NOT NULL,
  market VARCHAR(8) NOT NULL DEFAULT 'BR',
  current_price DECIMAL(12,2) NULL,
  reference_price DECIMAL(12,2) NULL,
  currency VARCHAR(8) NOT NULL DEFAULT 'BRL',
  status VARCHAR(32) NULL,
  checked_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (store_product_id, market)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vínculo opcional calendário → catálogo (nullable).
-- O deploy script aplica este ALTER só se a coluna ainda não existir.
ALTER TABLE gp_releases
  ADD COLUMN catalog_game_id VARCHAR(64) NULL AFTER price_brl;

ALTER TABLE gp_releases
  ADD KEY idx_gp_releases_catalog_game (catalog_game_id);
