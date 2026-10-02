-- =============================================================================
--  005 - Navegacion: arbol de menu por tienda y estilo de menu
-- -----------------------------------------------------------------------------
--  Que hace:
--   1) Anade a `mt_stores` el estilo de menu de la tienda:
--        menu_style  'compacto' | 'catalogo'  (exactamente dos, sin tercera)
--   2) Crea `mt_menu_items`: el arbol de navegacion de cada tienda, con los
--      tres niveles y el destino de cada nodo.
--
--  El arbol es UNICO: los dos menus (Compacto y Catalogo) pintan exactamente la
--  misma informacion; lo que cambia es la presentacion. La tienda elige cual
--  quiere desde el panel.
--
--  IMPORTANTE
--   - Solo crea tablas `mt_` y columnas nuevas en `mt_`. No modifica ninguna
--     tabla del mayorista (categorias, subcategorias, marcas, websites_*, ...).
--   - Idempotente: MySQL 8 no admite "ADD COLUMN IF NOT EXISTS", asi que cada
--     ALTER se genera solo si la columna no existe (information_schema).
--   - Sin transaccion: MySQL hace commit implicito en DDL.
-- =============================================================================

SET NAMES utf8mb4;

SET @db := DATABASE();

-- -----------------------------------------------------------------------------
-- 1) Estilo de menu de la tienda
-- -----------------------------------------------------------------------------
SET @sql := (SELECT IF(COUNT(*) = 0,
  'ALTER TABLE `mt_stores` ADD COLUMN `menu_style` varchar(20) NOT NULL DEFAULT ''catalogo'' COMMENT ''compacto|catalogo (unica eleccion de menu)'' AFTER `header_style`',
  'DO 0') FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'menu_style');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 2) Arbol de navegacion por tienda
--
--    level 1  categoria comercial    (target obligatorio: su categoria real)
--    level 2  grupo editorial        (target opcional: subcategoria de anclaje)
--    level 3  destino                (subcategoria | marca | filtro | etiqueta | url)
--
--    `slug` es el segmento de la URL SEO de los niveles 1 y 2.
--    El destino del nivel 3 se resuelve con target_* :
--       subcategoria -> target_id = subcategorias.id
--       marca        -> target_id = marcas.id          (+ anclaje del ancestro)
--       filtro       -> target_id = filtros.id, target_extra = subfiltros.id
--       etiqueta     -> target_key = ofertas|novedades|destacados
--       url          -> url (enlace libre del tendero)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mt_menu_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int unsigned NOT NULL,
  `parent_id` int unsigned DEFAULT NULL COMMENT 'NULL = nivel 1',
  `level` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '1,2,3',
  `label` varchar(120) NOT NULL,
  `slug` varchar(160) DEFAULT NULL COMMENT 'segmento SEO de los niveles 1 y 2',
  `target_type` varchar(20) NOT NULL DEFAULT 'subcategoria'
      COMMENT 'categoria|subcategoria|marca|filtro|etiqueta|url',
  `target_id` int unsigned DEFAULT NULL,
  `target_extra` int unsigned DEFAULT NULL COMMENT 'segundo id (subfiltro)',
  `target_key` varchar(40) DEFAULT NULL COMMENT 'etiqueta: ofertas|novedades|destacados',
  `url` varchar(500) DEFAULT NULL,
  `badge` varchar(40) DEFAULT NULL COMMENT 'NUEVO, Oportunidad...',
  `sort` smallint NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `note` varchar(255) DEFAULT NULL COMMENT 'aviso de la siembra (destino no resuelto)',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mt_menu_items_store` (`store_id`, `level`, `active`, `sort`),
  KEY `idx_mt_menu_items_parent` (`parent_id`, `sort`),
  CONSTRAINT `fk_mt_menu_items_store` FOREIGN KEY (`store_id`)
    REFERENCES `mt_stores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mt_menu_items_parent` FOREIGN KEY (`parent_id`)
    REFERENCES `mt_menu_items` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Arbol de navegacion de la tienda (3 niveles)';
