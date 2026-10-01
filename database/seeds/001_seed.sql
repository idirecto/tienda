-- =============================================================================
--  PROYECTO TIENDA - Semillas iniciales
--  Idempotente: puede ejecutarse varias veces.
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Planes (2 = cuota productos propios)
-- -----------------------------------------------------------------------------
INSERT INTO `mt_plans`
  (`code`, `name`, `description`, `own_products_quota`, `max_banners`, `max_notices`,
   `allow_custom_domain`, `allow_own_gateway`, `price_monthly`, `active`, `sort`)
VALUES
  ('basico',  'Basico',  'Catalogo idirecto. Sin productos propios.',          0,  2,  3, 0, 0,  0.00, 1, 1),
  ('medio',   'Medio',   'Catalogo mixto con hasta 50 productos propios.',    50,  5, 10, 1, 1, 19.90, 1, 2),
  ('premium', 'Premium', 'Productos propios ilimitados y dominio propio.',    -1, 20, 50, 1, 1, 49.90, 1, 3)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `description` = VALUES(`description`),
  `own_products_quota` = VALUES(`own_products_quota`), `max_banners` = VALUES(`max_banners`),
  `max_notices` = VALUES(`max_notices`), `allow_custom_domain` = VALUES(`allow_custom_domain`),
  `allow_own_gateway` = VALUES(`allow_own_gateway`), `price_monthly` = VALUES(`price_monthly`),
  `active` = VALUES(`active`), `sort` = VALUES(`sort`);

-- -----------------------------------------------------------------------------
-- Plantillas (3)
-- -----------------------------------------------------------------------------
INSERT INTO `mt_themes` (`id`, `name`, `folder`, `description`, `accent`, `active`, `sort`)
VALUES
  ('idirecto', 'I-Directo', 'idirecto', 'Diseno base inspirado en idirecto.es. Limpio y comercial.', '#e30613', 1, 1),
  ('moderno',  'Moderno',   'moderno',  'Tarjetas grandes, cabecera fija y tipografia actual.',        '#2563eb', 1, 2),
  ('minimal',  'Minimal',   'minimal',  'Estilo sobrio centrado en producto y conversion.',            '#111827', 1, 3)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `folder` = VALUES(`folder`), `description` = VALUES(`description`),
  `accent` = VALUES(`accent`), `active` = VALUES(`active`), `sort` = VALUES(`sort`);

-- -----------------------------------------------------------------------------
-- Tienda demo (permite ver el storefront sin configurar hostname)
-- -----------------------------------------------------------------------------
INSERT INTO `mt_stores`
  (`slug`, `name`, `legal_name`, `email`, `phone`, `whatsapp`, `id_plan`, `status`,
   `theme`, `color_primary`, `color_secondary`, `tagline`, `about`,
   `address`, `city`, `province`, `postal_code`, `meta_title`, `meta_description`)
VALUES
  ('idirecto-demo', 'Mi Tienda', 'Mi Tienda S.L.', 'hola@mitienda.test', '600 000 000', '34600000000',
   (SELECT id FROM mt_plans WHERE code = 'premium' LIMIT 1), 1,
   'idirecto', '#e30613', '#1f2937',
   'Tecnologia y accesorios al mejor precio',
   'Somos una tienda especializada en informatica y telefonia. Trabajamos con las mejores marcas y ofrecemos atencion personalizada.',
   'Calle Mayor 1', 'Zaragoza', 'Zaragoza', '50001',
   'Mi Tienda', 'Tienda online de informatica y telefonia')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `tagline` = VALUES(`tagline`), `about` = VALUES(`about`);

-- -----------------------------------------------------------------------------
-- Usuario demo del panel: admin@demo.test / demo1234
-- -----------------------------------------------------------------------------
INSERT INTO `mt_store_users` (`store_id`, `name`, `email`, `password_hash`, `role`, `active`)
SELECT s.id, 'Administrador', 'admin@demo.test',
       '$2y$12$t813uLbZUzNiGUr6Ox86GOyOr5KwpzygJrNn6HGPSCPDNSVIhqhIS', 'owner', 1
FROM mt_stores s WHERE s.slug = 'idirecto-demo'
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- -----------------------------------------------------------------------------
-- Contenido de ejemplo para la demo
-- -----------------------------------------------------------------------------
INSERT INTO `mt_content_blocks` (`store_id`, `slug`, `title`, `body`, `sort`, `active`)
SELECT s.id, 'sobre-nosotros', 'Sobre nosotros',
       'Texto de ejemplo editable desde el panel: cuenta aqui quien eres, que vendes y por que comprar en tu tienda.',
       1, 1
FROM mt_stores s WHERE s.slug = 'idirecto-demo'
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`);

INSERT INTO `mt_notices` (`store_id`, `type`, `message`, `visible`)
SELECT s.id, 'promo', 'Envio gratis en pedidos superiores a 50 EUR', 1
FROM mt_stores s WHERE s.slug = 'idirecto-demo'
  AND NOT EXISTS (SELECT 1 FROM mt_notices n WHERE n.store_id = s.id);
