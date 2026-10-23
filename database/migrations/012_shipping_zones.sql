-- =============================================================================
-- 012 · Zonas comerciales de venta y envio por tienda
-- -----------------------------------------------------------------------------
--  Cambios aditivos:
--
--   1) `mt_store_shipping_zones`: una fila por tienda y zona. Las zonas son
--      exactamente peninsula, ceuta, melilla y canarias. `cost` y `free_from`
--      NULL significan "usar la tarifa general de `mt_stores`".
--
--   2) `mt_orders.ship_zone`: zona aplicada al pedido, para conservar la
--      trazabilidad aunque la configuracion de la tienda cambie despues.
--
--  Las tiendas existentes quedan con las cuatro zonas activas y sin tarifa
--  propia, por lo que siguen usando `shipping_flat`/`free_shipping_from` hasta
--  que el tendero configure cada zona.
--
--  Baleares (id 9) pertenece a la zona peninsula por decision confirmada.
--  No se modifica ninguna regla fiscal ni ningun importe.
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `mt_store_shipping_zones` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `zone_code` varchar(20) NOT NULL COMMENT 'peninsula|ceuta|melilla|canarias',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `cost` decimal(12,2) DEFAULT NULL COMMENT 'NULL = usar shipping_flat de la tienda',
  `free_from` decimal(12,2) DEFAULT NULL COMMENT 'NULL = usar free_shipping_from de la tienda',
  `delivery_days_min` smallint unsigned DEFAULT NULL,
  `delivery_days_max` smallint unsigned DEFAULT NULL,
  `conditions` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_shipping_zone_store_code` (`store_id`, `zone_code`),
  KEY `idx_mt_shipping_zone_store` (`store_id`, `enabled`),
  CONSTRAINT `fk_mt_shipping_zone_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Zonas de venta y configuracion de envio por tienda';

-- Tiendas existentes: las cuatro zonas activas. La tarifa propia queda NULL
-- para heredar la configuracion general que ya tenian.
INSERT IGNORE INTO `mt_store_shipping_zones` (`store_id`, `zone_code`, `enabled`)
SELECT s.`id`, z.`zone_code`, 1
FROM `mt_stores` s
CROSS JOIN (
  SELECT 'peninsula' AS `zone_code`
  UNION ALL SELECT 'ceuta'
  UNION ALL SELECT 'melilla'
  UNION ALL SELECT 'canarias'
) z;

-- Zona aplicada al pedido. Es informativa/auditable; no interviene en impuestos.
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `ship_zone` varchar(20) DEFAULT NULL COMMENT ''peninsula|ceuta|melilla|canarias'' AFTER `ship_poblacion_id`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'ship_zone');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
