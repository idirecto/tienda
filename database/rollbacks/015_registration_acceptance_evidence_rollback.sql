-- Rollback de 015: retira las columnas de evidencia tecnica.
-- No toca datos de otras tablas.

SET @db := DATABASE();

SET @sql := (SELECT IF(COUNT(*) > 0,
  'ALTER TABLE `mt_contract_acceptances` DROP COLUMN `user_agent`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mt_contract_acceptances'
    AND COLUMN_NAME = 'user_agent');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) > 0,
  'ALTER TABLE `mt_contract_acceptances` DROP COLUMN `ip_address`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mt_contract_acceptances'
    AND COLUMN_NAME = 'ip_address');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) > 0,
  'ALTER TABLE `mt_contract_acceptances` DROP COLUMN `source_template_sha256`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'mt_contract_acceptances'
    AND COLUMN_NAME = 'source_template_sha256');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
