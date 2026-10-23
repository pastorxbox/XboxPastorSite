SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gp_releases (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NULL,
    cover_url VARCHAR(500) NULL,
    store_url VARCHAR(500) NULL,
    trailer_url VARCHAR(500) NULL,
    release_at DATETIME NOT NULL,
    body TEXT NULL,
    on_gamepass TINYINT(1) NOT NULL DEFAULT 0,
    on_ultimate TINYINT(1) NOT NULL DEFAULT 0,
    on_premium TINYINT(1) NOT NULL DEFAULT 0,
    on_essential TINYINT(1) NOT NULL DEFAULT 0,
    on_pc TINYINT(1) NOT NULL DEFAULT 0,
    platform_console TINYINT(1) NOT NULL DEFAULT 0,
    platform_pc TINYINT(1) NOT NULL DEFAULT 0,
    platform_cloud TINYINT(1) NOT NULL DEFAULT 0,
    play_anywhere TINYINT(1) NOT NULL DEFAULT 0,
    supports_pt_br TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_gp_status_release (status, release_at),
    INDEX idx_gp_sort (sort_order, release_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    banner_url VARCHAR(500) NULL,
    starts_at DATETIME NOT NULL,
    body MEDIUMTEXT NULL,
    external_url VARCHAR(500) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_events_status_starts (status, starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS xbox_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    occurred_year SMALLINT UNSIGNED NOT NULL,
    occurred_month TINYINT UNSIGNED NOT NULL,
    occurred_day TINYINT UNSIGNED NOT NULL,
    context VARCHAR(255) NULL,
    body TEXT NULL,
    cover_url VARCHAR(500) NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_history_status_md (status, occurred_month, occurred_day),
    INDEX idx_history_year (occurred_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
