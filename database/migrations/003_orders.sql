-- =============================================================================
--  003 - Pedidos de la tienda + envio de lineas al mayorista (idirecto)
-- -----------------------------------------------------------------------------
--  Que hace:
--   1) Anade a `mt_stores` el enlace con la cuenta del mayorista:
--        id_tienda_idirecto -> tiendas.id   (la tienda compra con esa cuenta)
--        id_margen          -> precios.id_margen (su tarifa)
--   2) Crea `mt_orders`      (pedidos que recibe la tienda de SUS clientes)
--   3) Crea `mt_order_items` (lineas del pedido, con el estado de envio a
--      idirecto por linea: sent_at + idirecto_pedido_id)
--
--  IMPORTANTE
--   - Solo crea tablas `mt_` y columnas nuevas en `mt_stores`. No modifica
--     ninguna tabla del mayorista.
--   - Idempotente: MySQL 8 no admite "ADD COLUMN IF NOT EXISTS", asi que cada
--     ALTER se genera solo si la columna no existe (information_schema).
--   - Sin transaccion: MySQL hace commit implicito en DDL.
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- Cuenta del mayorista (tiendas.id) y tarifa (precios.id_margen)
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `id_tienda_idirecto` smallint unsigned DEFAULT NULL COMMENT ''Cuenta de la tienda en el mayorista (tiendas.id)'' AFTER `id_plan`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'id_tienda_idirecto');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `id_margen` tinyint unsigned DEFAULT NULL COMMENT ''Tarifa de la tienda en el mayorista (precios.id_margen)'' AFTER `id_tienda_idirecto`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'id_margen');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- Pedidos de la tienda (los que recibe de sus clientes)
--
--   status: 0 borrador | 1 activo | 2 preparado | 3 enviado | 4 facturado
--           5 cancelado | 6 borrado (papelera: se puede restaurar)
--
--   Los importes son los que paga el cliente final (price_customer). La
--   tarifa del mayorista de cada linea vive en `mt_order_items` para poder
--   enviar el pedido con los numeros reales de idirecto.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_orders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `code` varchar(24) DEFAULT NULL COMMENT 'Numero visible del pedido',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '0 borrador,1 activo,2 preparado,3 enviado,4 facturado,5 cancelado,6 borrado',

  -- Cliente que hizo el pedido
  `customer_name` varchar(150) NOT NULL,
  `customer_email` varchar(180) DEFAULT NULL,
  `customer_phone` varchar(40) DEFAULT NULL,
  `customer_tax_id` varchar(30) DEFAULT NULL,

  -- Datos de envio (si estan vacios se usan los del cliente)
  `ship_name` varchar(150) DEFAULT NULL,
  `ship_tax_id` varchar(30) DEFAULT NULL,
  `ship_address` varchar(250) DEFAULT NULL,
  `ship_city` varchar(120) DEFAULT NULL,
  `ship_province` varchar(120) DEFAULT NULL,
  `ship_postal_code` varchar(20) DEFAULT NULL,
  `ship_country` varchar(60) NOT NULL DEFAULT 'Espana',

  `notes` text,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,

  -- Trazabilidad del envio al mayorista
  `id_tienda_idirecto` smallint unsigned DEFAULT NULL COMMENT 'Cuenta del mayorista con la que se envio',
  `idirecto_ref` varchar(100) DEFAULT NULL COMMENT 'Referencia escrita en pedidos.referencia',
  `sent_at` datetime DEFAULT NULL COMMENT 'Ultima vez que se envio algo a idirecto',

  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_orders_store_code` (`store_id`, `code`),
  KEY `idx_mt_orders_store_status` (`store_id`, `status`, `id`),
  KEY `idx_mt_orders_customer` (`store_id`, `customer_name`),
  KEY `idx_mt_orders_sent` (`store_id`, `sent_at`),
  CONSTRAINT `fk_mt_orders_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pedidos que la tienda recibe de sus clientes';

-- -----------------------------------------------------------------------------
-- Lineas del pedido
--
--   source: catalog (productos del mayorista: se pueden enviar a idirecto)
--           own     (producto propio de la tienda: NO se puede enviar)
--
--   price_customer  -> lo que paga el cliente de la tienda
--   price_idirecto  -> tarifa de la tienda en el mayorista (precios.id_margen)
--   cost_idirecto   -> coste del mayorista (stock.costo)
--
--   sent_at + idirecto_pedido_id: envio por linea, para poder mandar solo
--   algunas lineas (p. ej. 2 de 4) y no repetirlas en el siguiente envio.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_order_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int unsigned NOT NULL,
  `store_id` int unsigned NOT NULL,
  `line_no` smallint unsigned NOT NULL DEFAULT 1,
  `source` varchar(12) NOT NULL DEFAULT 'catalog' COMMENT 'catalog|own',
  `product_id` int unsigned DEFAULT NULL COMMENT 'productos.id o mt_own_products.id',
  `sku` varchar(60) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `qty` int unsigned NOT NULL DEFAULT 1,
  `price_customer` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio de venta al cliente final',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 21.00,
  `price_idirecto` decimal(12,2) DEFAULT NULL COMMENT 'Tarifa de la tienda en el mayorista',
  `cost_idirecto` decimal(12,2) DEFAULT NULL COMMENT 'Coste del mayorista (stock.costo)',
  `sent_at` datetime DEFAULT NULL COMMENT 'Cuando se envio esta linea a idirecto',
  `idirecto_pedido_id` int unsigned DEFAULT NULL COMMENT 'pedidos.id creado en idirecto',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_mt_order_items_order` (`order_id`, `line_no`),
  KEY `idx_mt_order_items_store` (`store_id`, `sent_at`),
  CONSTRAINT `fk_mt_order_items_order` FOREIGN KEY (`order_id`)
    REFERENCES `mt_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mt_order_items_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lineas de los pedidos de la tienda';
