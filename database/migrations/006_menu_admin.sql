-- =============================================================================
-- 006 · Menu administrable desde el panel
--
-- Aditiva e idempotente: amplia `mt_menu_items` con los campos del editor y
-- anade la visibilidad por tienda y la publicacion (borrador + publicado).
-- No borra ni renombra nada y se puede volver atras (cada columna es NULL o
-- tiene valor por defecto).
--
-- Decisiones del dueno del proyecto (2026-10-02):
--   · arbol GLOBAL de plataforma con visibilidad por tienda  -> store_id NULL
--     significa "nodo de la plataforma (compartido)"; con valor, "nodo propio
--     de esa tienda" (como hasta ahora).
--   · el estilo de menu sigue en `mt_stores.menu_style` con los valores que ya
--     existen: 'compacto' | 'catalogo'.
--   · borrador (filas de mt_menu_items) + publicacion (snapshot en
--     mt_menu_published); el escaparate lee el snapshot.
-- =============================================================================

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- 1. Campos del editor en el arbol de menu
-- -----------------------------------------------------------------------------
-- Icono del set SVG propio (grid, bolt, star, shield...).
SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD COLUMN `icon` varchar(40) NULL COMMENT ''clave del icono del juego propio'' AFTER `slug`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND COLUMN_NAME = 'icon');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Color del badge (hex o token --c-*). El texto ya vive en `badge`.
SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD COLUMN `badge_color` varchar(9) NULL COMMENT ''color del badge: #rrggbb o --c-token'' AFTER `badge`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND COLUMN_NAME = 'badge_color');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Banner del nodo: se reutiliza la tabla de banners de la tienda.
SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD COLUMN `banner_id` int unsigned NULL COMMENT ''banner de mt_banners que acompana al nodo'' AFTER `badge_color`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND COLUMN_NAME = 'banner_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Enlace del banner (cuando es una imagen externa, no un mt_banners).
SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD COLUMN `banner_url` varchar(500) NULL COMMENT ''enlace del banner externo'' AFTER `banner_id`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND COLUMN_NAME = 'banner_url');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Nivel minimo de cliente (categoria_cliente.id: 10 informatica, 11 telefonia,
-- 12 papeleria). NULL = visible para cualquier nivel.
SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD COLUMN `min_customer_level` tinyint unsigned NULL COMMENT ''nivel minimo de cliente (categoria_cliente.id); NULL = todos'' AFTER `banner_url`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND COLUMN_NAME = 'min_customer_level');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Configuracion de categorias vacias: no pintar el nodo si no tiene productos.
SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD COLUMN `hide_empty` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''ocultar el nodo si no tiene productos'' AFTER `min_customer_level`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND COLUMN_NAME = 'hide_empty');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Visibilidad por tienda: todas | solo | excepto (con mt_menu_item_stores).
SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD COLUMN `visibility` varchar(10) NOT NULL DEFAULT ''todas'' COMMENT ''todas|solo|excepto (ver mt_menu_item_stores)'' AFTER `hide_empty`',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND COLUMN_NAME = 'visibility');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2. `store_id` pasa a admitir NULL (nodo de la plataforma / compartido)
--    Solo se suelta y se vuelve a crear la clave foranea; no se borra ninguna fila.
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 1,
    'ALTER TABLE `mt_menu_items` DROP FOREIGN KEY `fk_mt_menu_items_store`',
    'DO 0')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items'
    AND CONSTRAINT_NAME = 'fk_mt_menu_items_store' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(IS_NULLABLE = 'NO',
    'ALTER TABLE `mt_menu_items` MODIFY `store_id` int unsigned NULL COMMENT ''NULL = nodo de la plataforma; con valor = nodo propio de esa tienda''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND COLUMN_NAME = 'store_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD CONSTRAINT `fk_mt_menu_items_store` FOREIGN KEY (`store_id`) REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE',
    'DO 0')
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items'
    AND CONSTRAINT_NAME = 'fk_mt_menu_items_store' AND CONSTRAINT_TYPE = 'FOREIGN KEY');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indice para la busqueda del arbol global (store_id NULL) por nivel.
SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD KEY `idx_mt_menu_items_scope` (`store_id`, `level`, `active`, `sort`)',
    'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND INDEX_NAME = 'idx_mt_menu_items_scope');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 3. Visibilidad por tienda de cada nodo
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_menu_item_stores` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `item_id` int unsigned NOT NULL,
  `store_id` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_menu_item_stores` (`item_id`, `store_id`),
  KEY `idx_mt_menu_item_stores_store` (`store_id`),
  CONSTRAINT `fk_mt_menu_item_stores_item` FOREIGN KEY (`item_id`)
    REFERENCES `mt_menu_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mt_menu_item_stores_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Que tiendas ven cada nodo del menu (cuando visibility no es todas)';

-- -----------------------------------------------------------------------------
-- 4. Arbol publicado (lo unico que ve el visitante)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_menu_published` (
  `store_id` int unsigned NOT NULL,
  `version` int unsigned NOT NULL DEFAULT 1,
  `payload` longtext NOT NULL COMMENT 'arbol resuelto en JSON para esta tienda',
  `published_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `published_by` int unsigned DEFAULT NULL COMMENT 'mt_store_users.id',
  PRIMARY KEY (`store_id`),
  CONSTRAINT `fk_mt_menu_published_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Version publicada del menu de cada tienda (el escaparate lee esto)';

-- -----------------------------------------------------------------------------
-- 5. Historial de publicaciones (permite volver a una version anterior)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_menu_revisions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `payload` longtext NOT NULL,
  `published_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_menu_revisions` (`store_id`, `version`),
  CONSTRAINT `fk_mt_menu_revisions_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de versiones publicadas del menu';

-- -----------------------------------------------------------------------------
-- 6. Marca del nodo con el que se sembro el arbol (para el editor)
--    Los nodos de la siembra de PuntoByZE quedan como borrador ya publicado en
--    el primer arranque: se publica solo si la tienda todavia no tiene version.
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `mt_menu_items` ADD KEY `idx_mt_menu_items_banner` (`banner_id`)',
    'DO 0')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND INDEX_NAME = 'idx_mt_menu_items_banner');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
