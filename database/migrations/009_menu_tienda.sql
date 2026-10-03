-- =============================================================================
-- 009 · La tienda elige que categorias se ven y puede renombrarlas
-- -----------------------------------------------------------------------------
--  Que hace (todo aditivo, sin borrar ni renombrar nada):
--
--   1) `mt_stores.menu_scope`:
--        completo -> se ve todo el menu del catalogo (comportamiento de siempre)
--        elegido  -> se ve SOLO lo que la tienda marque como visible
--
--   2) `mt_menu_item_overrides`: la decision de CADA tienda sobre CADA nodo,
--      sin tocar la fila del nodo (que puede ser compartida de la plataforma):
--        state = 'visible' | 'oculto'
--        label = nombre propio de la tienda (NULL = el del nodo)
--
--  Por que una tabla de anulaciones y no duplicar nodos: el arbol puede ser
--  compartido; duplicarlo por tienda obligaria a mantener dos copias
--  sincronizadas de un arbol de cientos de nodos. Aqui solo se guarda lo que
--  cambia y deshacerlo es borrar una fila.
--
--  Idempotente: MySQL 8 no admite "ADD COLUMN IF NOT EXISTS", asi que cada
--  ALTER se genera solo si falta (information_schema). Sin transaccion: MySQL
--  hace commit implicito en DDL.
--
--  No se toca ninguna tabla del mayorista (categorias, subcategorias, ...).
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- 1) Modo de menu de la tienda: completo | elegido
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `menu_scope` varchar(12) NOT NULL DEFAULT ''completo'' COMMENT ''completo|elegido: se ve todo o solo lo que la tienda marque'' AFTER `menu_style`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'menu_scope');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2) Anulaciones por tienda de cada nodo del menu
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_menu_item_overrides` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `state` varchar(8) NOT NULL DEFAULT 'visible' COMMENT 'visible|oculto para esta tienda',
  `label` varchar(120) DEFAULT NULL COMMENT 'nombre propio de la tienda (NULL = el del nodo)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_menu_item_overrides` (`store_id`, `item_id`),
  KEY `idx_mt_menu_item_overrides_item` (`item_id`),
  CONSTRAINT `fk_mt_menu_item_overrides_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mt_menu_item_overrides_item` FOREIGN KEY (`item_id`)
    REFERENCES `mt_menu_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lo que cada tienda decide sobre un nodo: mostrarlo/ocultarlo y su nombre';
