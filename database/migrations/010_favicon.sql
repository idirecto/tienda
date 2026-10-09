-- =============================================================================
-- 010 · Favicon: clave del fichero y version de la URL
-- -----------------------------------------------------------------------------
--  Que hace (todo aditivo, sin borrar ni renombrar nada):
--
--   1) `mt_stores.favicon_key`: clave del favicon propio en el almacenamiento
--      (local o S3). Sirve para comprobar que el fichero sigue existiendo (si
--      falta, la web cae al favicon predeterminado de Valduran) y para poder
--      borrarlo al sustituirlo. Puede quedar NULL: la tienda nunca ha subido
--      uno.
--
--   2) `mt_stores.favicon_version`: sello de tiempo (unix) de la ultima vez que
--      cambio el favicon. Se anyade como `?v=` a la URL para que el navegador
--      detecte el fichero nuevo sin dejar de cachear el resto.
--
--  `mt_stores.favicon_url` ya existia en el esquema inicial (001) pero la
--  aplicacion no la gestionaba: se pinta el favicon predeterminado cuando esta
--  vacia. El predeterminado es de la PLATAFORMA (config/brand.php + claves
--  BRAND_FAVICON* del .env) y la tienda no puede tocarlo desde su panel.
--
--  Idempotente: MySQL 8 no admite "ADD COLUMN IF NOT EXISTS", asi que cada
--  ALTER se genera solo si falta (information_schema). Sin transaccion: MySQL
--  hace commit implicito en DDL.
--
--  No se toca ninguna tabla del mayorista.
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- 1) Clave del favicon propio en el almacenamiento
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `favicon_key` varchar(300) DEFAULT NULL COMMENT ''Clave del favicon propio en el almacenamiento (NULL = no tiene)'' AFTER `favicon_url`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'favicon_key');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2) Version de la URL del favicon propio (invalida la cache del navegador)
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `favicon_version` int unsigned DEFAULT NULL COMMENT ''Sello de tiempo del cambio de favicon: se anyade como ?v= a su URL'' AFTER `favicon_key`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'favicon_version');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
