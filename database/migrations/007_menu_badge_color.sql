-- =============================================================================
-- 007 · El color del badge admite tokens de la identidad visual
--
-- `--c-primary-contrast` son 20 caracteres y no cabian en `varchar(9)`. Se
-- amplia la columna (aditivo: ningun dato se pierde). Si algun dia se guardan
-- solo hex, el cambio sigue siendo valido.
-- =============================================================================

SET @db := DATABASE();

SET @sql := (SELECT IF(CHARACTER_MAXIMUM_LENGTH < 32,
    'ALTER TABLE `mt_menu_items` MODIFY `badge_color` varchar(32) NULL COMMENT ''color del badge: #rrggbb o token --c-*''',
    'DO 0')
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mt_menu_items' AND COLUMN_NAME = 'badge_color');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
