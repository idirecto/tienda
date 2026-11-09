-- =============================================================================
-- 013 · Beneficio fijo por tienda (ademas del porcentual)
-- -----------------------------------------------------------------------------
--  El precio de venta de cada tienda pasa a calcularse asi:
--
--      precio base = (tarifa de la tienda + markup_fixed) * (1 + markup / 100)
--
--  y despues se suma el IVA cuando el precio se muestra en la web.
--
--  `markup` ya existia y sigue siendo el beneficio PORCENTUAL. La columna nueva
--  `markup_fixed` es el beneficio FIJO en euros y se suma a la tarifa antes de
--  aplicar el porcentaje. El valor por defecto es 0,00 y las tiendas existentes
--  conservan exactamente el precio que tenian.
--
--  Solo se modifica `mt_stores` (tabla propia).
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `markup_fixed` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT ''Beneficio fijo en EUR sumado a la tarifa antes del porcentaje'' AFTER `markup`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'markup_fixed');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
