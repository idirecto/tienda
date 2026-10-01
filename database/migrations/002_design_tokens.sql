-- =============================================================================
--  002 - Sistema de diseno por tienda (design tokens)
--
--  Anade a `mt_stores` los tokens que el storefront inyecta como variables CSS.
--  Solo columnas nuevas y con valores por defecto: no toca datos existentes ni
--  las tablas del mayorista.
--
--  Idempotente: MySQL 8 no admite "ADD COLUMN IF NOT EXISTS", asi que cada
--  ALTER se genera solo si la columna no existe (information_schema).
-- =============================================================================

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- Colores de marca
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `color_accent` varchar(9) DEFAULT NULL COMMENT ''Color de acento (detalles y focos)'' AFTER `color_secondary`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'color_accent');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- Paleta clara: fondo, tarjetas, texto y bordes (NULL = valor del tema)
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `color_bg` varchar(9) DEFAULT NULL COMMENT ''Fondo de pagina (NULL = tema)'' AFTER `color_accent`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'color_bg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `color_surface` varchar(9) DEFAULT NULL COMMENT ''Fondo de tarjetas (NULL = tema)'' AFTER `color_bg`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'color_surface');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `color_text` varchar(9) DEFAULT NULL COMMENT ''Color de texto (NULL = tema)'' AFTER `color_surface`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'color_text');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `color_border` varchar(9) DEFAULT NULL COMMENT ''Color de bordes (NULL = tema)'' AFTER `color_text`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'color_border');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- Modo de color, forma y tokens libres
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `color_scheme` varchar(10) NOT NULL DEFAULT ''light'' COMMENT ''light|dark|auto'' AFTER `color_border`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'color_scheme');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `radius_scale` varchar(12) NOT NULL DEFAULT ''standard'' COMMENT ''compact|standard|rounded'' AFTER `color_scheme`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'radius_scale');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `theme_tokens` text DEFAULT NULL COMMENT ''Tokens de diseno libres (JSON): pisa cualquier variable del tema'' AFTER `radius_scale`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'theme_tokens');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `custom_css` text DEFAULT NULL COMMENT ''CSS propio de la tienda (avanzado)'' AFTER `theme_tokens`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'custom_css');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- Identidad de la tienda demo: preset "tecnologico oscuro" (tipo Caseking),
-- para que se vea el potencial del sistema sin configurar nada.
-- -----------------------------------------------------------------------------
UPDATE `mt_stores`
   SET `color_accent` = COALESCE(NULLIF(`color_accent`, ''), '#00aff0'),
       `color_scheme` = CASE WHEN `color_scheme` = '' THEN 'light' ELSE `color_scheme` END
 WHERE `slug` = 'idirecto-demo';
