-- ============================================================================
-- 008 · Destinos de filtro del catalogo
--
-- Los filtros estructurados del mayorista (filtros/subfiltros por subcategoria)
-- ya se resuelven en Catalog y Menu, asi que los destinos de filtro que la
-- siembra dejo desactivados con su aviso dejan de estar pendientes.
--
-- Idempotente: solo activa los nodos que seguian pendientes por ese motivo.
-- El escaparate lee la version publicada del menu, asi que el tendero debe
-- publicar para verlos (o volver a copiar el menu de referencia).
-- ============================================================================

UPDATE mt_menu_items
   SET active = 1,
       note = NULL
 WHERE target_type = 'filtro'
   AND target_id > 0
   AND note LIKE '%pendiente%';
