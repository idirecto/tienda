-- =============================================================================
--  004 - Compra como cliente final: clientes, direcciones y pedido de la web
-- -----------------------------------------------------------------------------
--  Que hace:
--   1) Ajustes de venta de la tienda en `mt_stores`:
--        markup              beneficio sobre la tarifa del mayorista (%)
--        shipping_flat       gastos de envio fijos
--        free_shipping_from  envio gratis a partir de X euros (NULL = nunca)
--        pay_transfer/pay_cod/pay_pickup  formas de pago que acepta
--        bank_details        datos para la transferencia
--   2) Crea `mt_customers`           (clientes finales de cada tienda)
--   3) Crea `mt_customer_addresses`  (su libreta de direcciones)
--   4) Anade a `mt_orders` el cliente, la direccion de facturacion, los ids de
--      la direccion de envio, el comentario del cliente y el cobro.
--
--  IMPORTANTE
--   - Solo crea tablas `mt_` y columnas nuevas en tablas `mt_`. No modifica
--     ninguna tabla del mayorista (pedidos, pedidos_addr, pedidos_det, ...).
--   - Idempotente: MySQL 8 no admite "ADD COLUMN IF NOT EXISTS", asi que cada
--     ALTER se genera solo si la columna no existe (information_schema).
--   - Sin transaccion: MySQL hace commit implicito en DDL.
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- 1) Ajustes de venta de la tienda
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `markup` decimal(5,2) NOT NULL DEFAULT 15.00 COMMENT ''Beneficio sobre la tarifa del mayorista (%)'' AFTER `tax_rate`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'markup');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `shipping_flat` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Gastos de envio fijos'' AFTER `markup`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'shipping_flat');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `free_shipping_from` decimal(12,2) DEFAULT NULL COMMENT ''Envio gratis desde este importe (NULL = nunca)'' AFTER `shipping_flat`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'free_shipping_from');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `pay_transfer` tinyint(1) NOT NULL DEFAULT 1 COMMENT ''Acepta transferencia bancaria'' AFTER `free_shipping_from`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'pay_transfer');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `pay_cod` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''Acepta contra reembolso'' AFTER `pay_transfer`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'pay_cod');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `pay_pickup` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''Acepta recogida en tienda'' AFTER `pay_cod`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'pay_pickup');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `bank_details` text COMMENT ''Datos de la transferencia (titular, IBAN, instrucciones)'' AFTER `pay_pickup`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'bank_details');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2) Clientes finales de la tienda
--
--   is_guest = 1 mientras nadie ha puesto contrasena: es el cliente que nace al
--   comprar como invitado. Si despues se registra con el mismo email, se
--   "reclama" (se le pone contrasena y pasa a 0) y conserva sus pedidos.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `email` varchar(180) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL COMMENT 'NULL en clientes invitados',
  `name` varchar(150) NOT NULL DEFAULT '',
  `tax_id` varchar(30) DEFAULT NULL COMMENT 'DNI/NIF/CIF',
  `phone` varchar(40) DEFAULT NULL,
  `mobile` varchar(40) DEFAULT NULL,
  `is_guest` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_customers_store_email` (`store_id`, `email`),
  KEY `idx_mt_customers_store` (`store_id`, `created_at`),
  CONSTRAINT `fk_mt_customers_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Clientes finales de la tienda (registrados o de una compra como invitado)';

-- -----------------------------------------------------------------------------
-- 3) Libreta de direcciones del cliente
--
--   Mismos datos que usa el mayorista en `pedidos_addr` para que el pedido se
--   pueda enviar con la direccion real del cliente sin inventar nada:
--   nombre, nif_cif, direccion, cp, poblacion (+ id), provincia (+ id), pais
--   (+ id), telefono y celular. `detail` es el portal/escalera/piso.
--   Los ids (id_pais/id_provincia/id_poblacion) son de las tablas del mayorista
--   (paises/provincias/poblaciones) y son opcionales: los clientes de fuera
--   pueden escribir su provincia y poblacion a mano.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_customer_addresses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `customer_id` int unsigned NOT NULL,
  `label` varchar(60) DEFAULT NULL COMMENT 'Nombre para reconocerla (Casa, Oficina...)',
  `name` varchar(150) NOT NULL COMMENT 'Nombre y apellidos de quien recibe',
  `tax_id` varchar(30) DEFAULT NULL,
  `address` varchar(250) NOT NULL,
  `detail` varchar(200) DEFAULT NULL COMMENT 'Portal, escalera, piso...',
  `postal_code` varchar(20) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL COMMENT 'Poblacion (texto tal cual lo escriba el cliente)',
  `province` varchar(120) DEFAULT NULL,
  `country` varchar(60) NOT NULL DEFAULT 'Espana',
  `id_pais` smallint unsigned DEFAULT NULL,
  `id_provincia` smallint unsigned DEFAULT NULL,
  `id_poblacion` int unsigned DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `mobile` varchar(40) DEFAULT NULL,
  `is_default_ship` tinyint(1) NOT NULL DEFAULT 0,
  `is_default_bill` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = borrada por el cliente',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_mt_cust_addr_customer` (`customer_id`, `active`),
  KEY `idx_mt_cust_addr_store` (`store_id`),
  CONSTRAINT `fk_mt_cust_addr_customer` FOREIGN KEY (`customer_id`)
    REFERENCES `mt_customers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mt_cust_addr_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Direcciones de envio/facturacion de los clientes de la tienda';

-- -----------------------------------------------------------------------------
-- 4) Datos del pedido que llegan de la web
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `customer_id` int unsigned DEFAULT NULL COMMENT ''Cliente de mt_customers (NULL si lo creo el tendero a mano)'' AFTER `store_id`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'customer_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Direccion de facturacion (si esta vacia se usa la de envio)
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `bill_name` varchar(150) DEFAULT NULL AFTER `customer_tax_id`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'bill_name');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `bill_tax_id` varchar(30) DEFAULT NULL AFTER `bill_name`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'bill_tax_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `bill_address` varchar(250) DEFAULT NULL AFTER `bill_tax_id`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'bill_address');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `bill_detail` varchar(200) DEFAULT NULL AFTER `bill_address`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'bill_detail');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `bill_city` varchar(120) DEFAULT NULL AFTER `bill_detail`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'bill_city');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `bill_province` varchar(120) DEFAULT NULL AFTER `bill_city`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'bill_province');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `bill_postal_code` varchar(20) DEFAULT NULL AFTER `bill_province`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'bill_postal_code');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `bill_country` varchar(60) DEFAULT NULL AFTER `bill_postal_code`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'bill_country');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `bill_phone` varchar(40) DEFAULT NULL AFTER `bill_country`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'bill_phone');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Envio: detalle de la direccion, contacto y los ids del mayorista
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `ship_detail` varchar(200) DEFAULT NULL AFTER `ship_address`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'ship_detail');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `ship_phone` varchar(40) DEFAULT NULL AFTER `ship_country`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'ship_phone');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `ship_mobile` varchar(40) DEFAULT NULL AFTER `ship_phone`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'ship_mobile');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `ship_country_id` smallint unsigned DEFAULT NULL COMMENT ''paises.id'' AFTER `ship_mobile`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'ship_country_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `ship_province_id` smallint unsigned DEFAULT NULL COMMENT ''provincias.id'' AFTER `ship_country_id`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'ship_province_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `ship_poblacion_id` int unsigned DEFAULT NULL COMMENT ''poblaciones.id'' AFTER `ship_province_id`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'ship_poblacion_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Comentario del cliente y estado del cobro
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `customer_note` text COMMENT ''Comentario que deja el cliente al finalizar'' AFTER `notes`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'customer_note');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `payment_method` varchar(20) DEFAULT NULL COMMENT ''transferencia|cod|pickup'' AFTER `customer_note`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'payment_method');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `payment_status` tinyint NOT NULL DEFAULT 0 COMMENT ''0 pendiente de pago, 1 pagado'' AFTER `payment_method`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'payment_status');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD COLUMN `paid_at` datetime DEFAULT NULL AFTER `payment_status`', 'DO 0')
  FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND COLUMN_NAME = 'paid_at');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Clave del cliente en el pedido (si se borra el cliente, el pedido se queda)
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD KEY `idx_mt_orders_customer_id` (`customer_id`)', 'DO 0')
  FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND INDEX_NAME = 'idx_mt_orders_customer_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_orders` ADD CONSTRAINT `fk_mt_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `mt_customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'DO 0') FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_orders' AND CONSTRAINT_NAME = 'fk_mt_orders_customer');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
