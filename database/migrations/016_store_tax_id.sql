-- =============================================================================
--  016 - NIF/CIF del responsable de cada tienda
--
--  La politica de cookies necesita identificar al responsable con su NIF/CIF.
--  Hasta ahora ese dato solo estaba en `tiendas.nif_cif` (tabla de solo
--  lectura) y las tiendas sin cuenta enlazada no podian completarlo.
--
--  Solo se anade una columna a `mt_stores` y se rellena desde la cuenta
--  enlazada. No se escribe en `tiendas` ni en ninguna tabla del mayorista.
--
--  Idempotente: MySQL 8 no admite "ADD COLUMN IF NOT EXISTS", asi que el
--  ALTER se genera solo si la columna no existe (information_schema).
-- =============================================================================

SET @db := DATABASE();

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `tax_id` varchar(30) DEFAULT NULL COMMENT ''NIF/CIF del responsable'' AFTER `legal_name`',
  'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mt_stores'
    AND COLUMN_NAME = 'tax_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE mt_stores s
JOIN tiendas t ON t.id = s.id_tienda_idirecto
SET s.tax_id = LEFT(t.nif_cif, 30)
WHERE (s.tax_id IS NULL OR s.tax_id = '')
  AND t.nif_cif IS NOT NULL
  AND t.nif_cif <> '';
