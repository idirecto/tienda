-- =============================================================================
-- ROLLBACK 014 - Alta pendiente, contrato aceptado y verificacion
-- -----------------------------------------------------------------------------
-- DESTRUCTIVO: elimina las tablas y el indice creados por la migracion 014.
-- Perdera todos los datos de altas, aceptaciones y verificaciones.
--
-- No se ejecuta automaticamente desde migrate.php. Requiere autorizacion
-- expresa y copia de seguridad previa acordada.
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

DROP TABLE IF EXISTS `mt_registration_verifications`;
DROP TABLE IF EXISTS `mt_contract_acceptances`;
DROP TABLE IF EXISTS `mt_store_registrations`;
DROP TABLE IF EXISTS `mt_contract_versions`;

SET @sql := (SELECT IF(COUNT(*) > 0,
  'ALTER TABLE `mt_stores` DROP INDEX `uq_mt_stores_id_tienda_idirecto`',
  'DO 0') FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mt_stores'
    AND INDEX_NAME = 'uq_mt_stores_id_tienda_idirecto');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

DELETE FROM `mt_migrations`
 WHERE `filename` = '014_registration_lifecycle.sql';
