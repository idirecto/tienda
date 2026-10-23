-- =============================================================================
-- 011 · Administracion completa de banners y avisos
-- -----------------------------------------------------------------------------
--  Que hace (todo aditivo, sin borrar ni renombrar nada y sin tocar filas):
--
--   1) `mt_banners.name`: nombre interno para localizar el banner en el panel.
--      No se muestra en la tienda.
--
--   2) `mt_banners.alt_text`: texto alternativo de la imagen.
--
--   3) `mt_banners.status`: estado editorial `draft|scheduled|active|inactive`.
--      Los registros existentes se quedan en NULL y el codigo deduce su estado
--      con `active` y las fechas, sin reescribirlos.
--
--   4) `mt_banners.overlay_opacity`: intensidad del velo del hero entre 0 y 70.
--      NULL significa 35 (valor por defecto en la aplicacion).
--
--   5) Campos de presentacion de `mt_notices`: variante, tamano de texto,
--      badge, icono e imagen opcional mediante `mt_media`. Tambien quedan NULL
--      en los avisos existentes y la aplicacion usa los valores por defecto.
--
--  Idempotente: MySQL 8 no admite "ADD COLUMN IF NOT EXISTS", asi que cada
--  ALTER se genera solo si falta (information_schema). Sin transaccion: MySQL
--  hace commit implicito en DDL.
--
--  Reversion manual (NO se ejecuta automaticamente):
--    ALTER TABLE mt_banners
--      DROP COLUMN name, DROP COLUMN alt_text, DROP COLUMN status,
--      DROP COLUMN overlay_opacity;
--    ALTER TABLE mt_notices
--      DROP COLUMN variant, DROP COLUMN text_size, DROP COLUMN badge_text,
--      DROP COLUMN icon, DROP COLUMN media_id, DROP COLUMN image_url;
--  El codigo anterior ignora las columnas nuevas, asi que un rollback de
--  aplicacion no necesita tocar el esquema.
--
--  No se toca ninguna tabla del mayorista ni ninguna fila historica.
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- Banners
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_banners` ADD COLUMN `name` varchar(120) DEFAULT NULL COMMENT ''Nombre interno del banner (no publico)'' AFTER `store_id`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_banners' AND COLUMN_NAME = 'name');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_banners` ADD COLUMN `alt_text` varchar(255) DEFAULT NULL COMMENT ''Texto alternativo de la imagen'' AFTER `subtitle`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_banners' AND COLUMN_NAME = 'alt_text');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_banners` ADD COLUMN `status` varchar(12) DEFAULT NULL COMMENT ''draft|scheduled|active|inactive; NULL = legado segun active y fechas'' AFTER `active`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_banners' AND COLUMN_NAME = 'status');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_banners` ADD COLUMN `overlay_opacity` tinyint unsigned DEFAULT NULL COMMENT ''Opacidad del velo del hero (0-70); NULL = 35'' AFTER `status`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_banners' AND COLUMN_NAME = 'overlay_opacity');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- Avisos: presentacion y media opcional
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_notices` ADD COLUMN `variant` varchar(12) DEFAULT NULL COMMENT ''soft|solid|outline'' AFTER `type`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_notices' AND COLUMN_NAME = 'variant');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_notices` ADD COLUMN `text_size` varchar(10) DEFAULT NULL COMMENT ''sm|md|lg'' AFTER `variant`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_notices' AND COLUMN_NAME = 'text_size');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_notices` ADD COLUMN `badge_text` varchar(40) DEFAULT NULL COMMENT ''Texto del badge, por ejemplo OFERTA'' AFTER `message`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_notices' AND COLUMN_NAME = 'badge_text');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_notices` ADD COLUMN `icon` varchar(40) DEFAULT NULL COMMENT ''Clave de icono del juego interno'' AFTER `badge_text`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_notices' AND COLUMN_NAME = 'icon');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_notices` ADD COLUMN `media_id` bigint unsigned DEFAULT NULL COMMENT ''Imagen opcional en mt_media'' AFTER `icon`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_notices' AND COLUMN_NAME = 'media_id');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_notices` ADD COLUMN `image_url` varchar(700) DEFAULT NULL COMMENT ''URL de la imagen opcional'' AFTER `media_id`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_notices' AND COLUMN_NAME = 'image_url');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
