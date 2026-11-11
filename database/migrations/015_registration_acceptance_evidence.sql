-- =============================================================================
-- 015 - Evidencia tecnica de la aceptacion electronica
-- -----------------------------------------------------------------------------
-- La tabla `mt_contract_acceptances` ya guardaba version, fecha, hashes y datos
-- declarados, pero no la IP ni el user-agent que exige la auditoria del alta.
--
-- Cambio aditivo e idempotente. No modifica filas existentes ni tablas del
-- mayorista. No se envuelve en transaccion porque MySQL hace COMMIT implicito
-- en DDL.
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_contract_acceptances` ADD COLUMN `source_template_sha256` char(64) DEFAULT NULL COMMENT ''SHA-256 del HTML fuente usado para generar el PDF'' AFTER `template_sha256`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mt_contract_acceptances'
    AND COLUMN_NAME = 'source_template_sha256');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_contract_acceptances` ADD COLUMN `ip_address` varchar(45) DEFAULT NULL COMMENT ''IP del evento de aceptacion'' AFTER `timezone`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mt_contract_acceptances'
    AND COLUMN_NAME = 'ip_address');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_contract_acceptances` ADD COLUMN `user_agent` varchar(512) DEFAULT NULL COMMENT ''User-agent del navegador en la aceptacion'' AFTER `ip_address`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mt_contract_acceptances'
    AND COLUMN_NAME = 'user_agent');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
