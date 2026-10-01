-- =============================================================================
--  PROYECTO TIENDA - Esquema inicial
-- -----------------------------------------------------------------------------
--  Base de datos : la BD central del mayorista (configurable en .env)
--  Prefijo       : mt_   (multi-tienda)
--
--  IMPORTANTE
--   - Este esquema es ADITIVO: solo crea tablas nuevas con prefijo mt_.
--   - NO modifica ni elimina ninguna tabla existente del mayorista
--     (productos, pedidos, clientes, tiendas, webs...).
--   - Idempotente: usa CREATE TABLE IF NOT EXISTS.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Planes/niveles de cliente
--   own_products_quota: -1 = ilimitado | 0 = no permitido | N = limite
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_plans` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `own_products_quota` int NOT NULL DEFAULT 0,
  `max_banners` smallint NOT NULL DEFAULT 3,
  `max_notices` smallint NOT NULL DEFAULT 5,
  `allow_custom_domain` tinyint(1) NOT NULL DEFAULT 0,
  `allow_own_gateway` tinyint(1) NOT NULL DEFAULT 0,
  `price_monthly` decimal(10,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort` smallint NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_plans_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Planes de tienda con cuota de productos propios';

-- -----------------------------------------------------------------------------
-- Plantillas visuales disponibles (catalogo cerrado)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_themes` (
  `id` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `folder` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `preview_url` varchar(500) DEFAULT NULL,
  `accent` varchar(8) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort` smallint NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catalogo de plantillas de storefront';

-- -----------------------------------------------------------------------------
-- Tiendas (tenant) + su configuracion de diseno y datos publicos
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_stores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(63) NOT NULL COMMENT 'subdominio: <slug>.dominio_base',
  `name` varchar(120) NOT NULL,
  `legal_name` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `whatsapp` varchar(40) DEFAULT NULL,

  `id_plan` smallint unsigned DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0 pendiente,1 activa,2 suspendida',

  -- Diseno
  `theme` varchar(40) NOT NULL DEFAULT 'idirecto',
  `color_primary` varchar(9) NOT NULL DEFAULT '#e30613',
  `color_secondary` varchar(9) NOT NULL DEFAULT '#1f2937',
  `font` varchar(40) NOT NULL DEFAULT 'system',
  `header_style` varchar(20) NOT NULL DEFAULT 'classic',
  `logo_url` varchar(500) DEFAULT NULL,
  `logo_key` varchar(300) DEFAULT NULL,
  `favicon_url` varchar(500) DEFAULT NULL,

  -- Contenido / contacto
  `tagline` varchar(200) DEFAULT NULL,
  `about` text,
  `address` varchar(200) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(60) NOT NULL DEFAULT 'Espana',
  `currency` char(3) NOT NULL DEFAULT 'EUR',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 21.00,

  -- SEO y opciones
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` varchar(300) DEFAULT NULL,
  `show_prices` tinyint(1) NOT NULL DEFAULT 1,
  `allow_orders` tinyint(1) NOT NULL DEFAULT 1,

  -- Override de cuota (si es NULL se usa la del plan)
  `own_products_quota_override` int DEFAULT NULL,

  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_stores_slug` (`slug`),
  KEY `idx_mt_stores_status` (`status`),
  CONSTRAINT `fk_mt_stores_plan` FOREIGN KEY (`id_plan`)
    REFERENCES `mt_plans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tiendas (tenant) del proyecto';

-- -----------------------------------------------------------------------------
-- Usuarios del panel de cada tienda
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_store_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'owner' COMMENT 'owner|editor|viewer',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_store_users_email` (`email`),
  KEY `idx_mt_store_users_store` (`store_id`),
  CONSTRAINT `fk_mt_store_users_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Usuarios del panel de tienda';

-- -----------------------------------------------------------------------------
-- Media (ficheros/imagenes) - solo keys y URLs, nunca binarios
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `folder` varchar(60) NOT NULL,
  `driver` varchar(20) NOT NULL DEFAULT 'local' COMMENT 's3|local',
  `file_key` varchar(500) NOT NULL,
  `url` varchar(700) NOT NULL,
  `mime` varchar(100) NOT NULL,
  `bytes` int unsigned NOT NULL DEFAULT 0,
  `width` smallint unsigned DEFAULT NULL,
  `height` smallint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_media_key` (`file_key`),
  KEY `idx_mt_media_store` (`store_id`, `folder`),
  CONSTRAINT `fk_mt_media_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro de ficheros en S3/local';

-- -----------------------------------------------------------------------------
-- Banners
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_banners` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `media_id` bigint unsigned DEFAULT NULL,
  `image_url` varchar(700) DEFAULT NULL,
  `position` varchar(20) NOT NULL DEFAULT 'hero' COMMENT 'hero|middle|footer',
  `sort` smallint NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mt_banners_store` (`store_id`, `position`, `active`),
  CONSTRAINT `fk_mt_banners_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Banners de la tienda';

-- -----------------------------------------------------------------------------
-- Avisos / anuncios
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_notices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'info' COMMENT 'info|promo|warning',
  `message` varchar(500) NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mt_notices_store` (`store_id`, `visible`),
  CONSTRAINT `fk_mt_notices_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Avisos y anuncios de la tienda';

-- -----------------------------------------------------------------------------
-- Productos propios (cuota limitada por plan)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_own_products` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `sku` varchar(60) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `description` mediumtext,
  `price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sale_price` decimal(12,2) DEFAULT NULL,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 21.00,
  `stock` int NOT NULL DEFAULT 0,
  `supplier` varchar(150) DEFAULT NULL COMMENT 'proveedor externo (no solo idirecto)',
  `category` varchar(100) DEFAULT NULL,
  `media_id` bigint unsigned DEFAULT NULL,
  `image_url` varchar(700) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '0 borrador,1 publicado,2 oculto',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_own_products_sku` (`store_id`, `sku`),
  KEY `idx_mt_own_products_store` (`store_id`, `status`),
  CONSTRAINT `fk_mt_own_products_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Productos propios de la tienda';

-- -----------------------------------------------------------------------------
-- Dominios propios / subdominios + estado de verificacion DNS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_domains` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `domain` varchar(190) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'custom' COMMENT 'custom|subdomain',
  `method` varchar(10) NOT NULL DEFAULT 'A' COMMENT 'A|CNAME|BOTH',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `expected_a` varchar(45) DEFAULT NULL,
  `expected_cname` varchar(190) DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0 pendiente,1 verificado,2 error',
  `last_check` datetime DEFAULT NULL,
  `last_result` varchar(500) DEFAULT NULL,
  `token` varchar(40) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_domains_domain` (`domain`),
  KEY `idx_mt_domains_store` (`store_id`, `status`),
  CONSTRAINT `fk_mt_domains_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Dominios propios y estado de verificacion DNS';

-- -----------------------------------------------------------------------------
-- Historico de comprobaciones DNS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_dns_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `domain_id` int unsigned NOT NULL,
  `status` tinyint NOT NULL,
  `records_json` json DEFAULT NULL,
  `message` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mt_dns_log_domain` (`domain_id`, `id`),
  CONSTRAINT `fk_mt_dns_log_domain` FOREIGN KEY (`domain_id`)
    REFERENCES `mt_domains` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historico de verificaciones DNS';

-- -----------------------------------------------------------------------------
-- Bloques de contenido (sobre nosotros, envios, condiciones...)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_content_blocks` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `slug` varchar(60) NOT NULL,
  `title` varchar(150) DEFAULT NULL,
  `body` mediumtext,
  `media_id` bigint unsigned DEFAULT NULL,
  `sort` smallint NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_content_store_slug` (`store_id`, `slug`),
  CONSTRAINT `fk_mt_content_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bloques de contenido por tienda';

-- -----------------------------------------------------------------------------
-- Configuracion clave/valor por tienda
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `key` varchar(80) NOT NULL,
  `value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_settings_store_key` (`store_id`, `key`),
  CONSTRAINT `fk_mt_settings_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuracion clave/valor por tienda';

-- -----------------------------------------------------------------------------
-- Control de migraciones aplicadas
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `filename` varchar(190) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_migrations_filename` (`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Migraciones aplicadas';
