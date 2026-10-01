# Esquema de las tablas propias (`mt_`)

Generado desde la base de datos de desarrollo. Todas son `utf8mb4_unicode_ci` y,
salvo `mt_migrations`, `mt_plans` y `mt_themes`, tienen FK a `mt_stores(id)` con
`ON DELETE CASCADE`.

> Las tablas del mayorista (`productos`, `precios`, `stock`, `almacenes`,
> `marcas`, `categorias`, `subcategorias`, `productos_ext`, `productos_resenas`)
> son de **solo lectura** y no se documentan aquí.
>
> **Única excepción:** al enviar líneas de un pedido se escriben filas en
> `pedidos_addr`, `pedidos` y `pedidos_det` (y se descuenta `stock`/`reserva` en
> los almacenes tipo 0 y 4), replicando el flujo web de idirecto. Solo lo hace
> `Core/Idirecto/OrderGateway` y se puede desactivar con `IDIRECTO_ENABLED=false`
> o `IDIRECTO_RESERVE_STOCK=false`.

## Tiendas, planes y usuarios

**`mt_plans`** — planes comerciales.
`id`, `code`, `name`, `description`, `own_products_quota` (**-1 = ilimitado**),
`max_banners`, `max_notices`, `allow_custom_domain`, `allow_own_gateway`,
`price_monthly`, `active`, `sort`, `created_at`

**`mt_stores`** — la tienda. Tabla central del modelo.
`id`, `slug` (63, único), `name`, `legal_name`, `email`, `phone`, `whatsapp`,
`id_plan`, `status`, `theme`, `color_primary`, `color_secondary`, `font`,
`header_style`, `logo_url`, `logo_key`, `favicon_url`, `tagline`, `about`,
`address`, `city`, `province`, `postal_code`, `country`, `currency`, `tax_rate`,
`meta_title`, `meta_description`, `show_prices`, `allow_orders`,
`own_products_quota_override`, `created_at`, `updated_at`, y la venta y el cobro:
`markup` (beneficio sobre su tarifa), `shipping_flat`, `free_shipping_from`,
`pay_transfer`, `pay_cod`, `pay_pickup`, `bank_details`.

- `own_products_quota_override` gana sobre la cuota del plan (`-1` = ilimitado).
- `show_prices` / `allow_orders` permiten una tienda "escaparate" sin compra (y sin
  botones de carrito).
- **`markup`** decide el precio de venta: `tarifa de la tienda × (1 + markup/100)`.
  En la web se muestra **con IVA incluido** (`Catalog::forStore(..., true)`) y en el
  pedido se guarda la base sin IVA.
- `id_tienda_idirecto` → `tiendas.id` (la cuenta con la que la tienda compra al
  mayorista) e `id_margen` → `precios.id_margen` (su tarifa). Los edita el dueño en
  Ajustes; la tarifa vacía cae a `tiendas.id_margen` y, si no, a
  `IDIRECTO_DEFAULT_ID_MARGEN` (12).
- Las tiendas **nuevas se dan de alta en `/registro`** (`Core/Registration`), y
  solo con una **cuenta activa del mayorista**: el tendero entra con su email y
  contraseña de idirecto (`Account::login()`, `activo = 2` y la firma
  `hash('sha256', md5(sha1($clave)))`) y la tienda nace ya enlazada, con los datos
  de la cuenta y su tarifa. Una cuenta = una tienda.

**`mt_store_users`** — usuarios del panel.
`id`, `store_id`, `name`, `email`, `password_hash` (*bcrypt/argon*), `role`,
`active`, `last_login_at`, `created_at`

- El registro crea un `owner` con el email de la cuenta del mayorista y una
  contraseña propia; la del mayorista no se guarda.

**`mt_settings`** — ajustes clave/valor por tienda: `id`, `store_id`, `key`, `value`

**`mt_themes`** — catálogo cerrado de temas.
`id` (varchar 40), `name`, `folder`, `description`, `preview_url`, `accent`,
`active`, `sort`

## Contenido de la tienda

**`mt_banners`** — `id`, `store_id`, `title`, `subtitle`, `link`, `media_id`,
`image_url`, `position`, `sort`, `active`, `starts_at`, `ends_at`, `created_at`

**`mt_notices`** — `id`, `store_id`, `type`, `message`, `visible`, `starts_at`,
`ends_at`, `created_at`

**`mt_content_blocks`** — páginas tipo `/pagina/{slug}`.
`id`, `store_id`, `slug`, `title`, `body`, `media_id`, `sort`, `active`,
`updated_at`

**`mt_own_products`** — productos propios de la tienda (los que limitan los
planes). `id`, `store_id`, `sku`, `name`, `description`, `price`, `sale_price`,
`tax_rate`, `stock`, `supplier`, `category`, `media_id`, `image_url`, `status`,
`created_at`, `updated_at`

## Pedidos

**`mt_orders`** — pedidos que la tienda recibe de **sus** clientes.
`id`, `store_id`, `customer_id` (cliente de `mt_customers`; vacío si lo creó el
tendero a mano), `code` (número visible `P26-00001`), `status`
(`0` borrador, `1` activo, `2` preparado, `3` enviado, `4` facturado,
`5` cancelado, `6` borrado/papelera), `customer_name`, `customer_email`,
`customer_phone`, `customer_tax_id`, la dirección de **envío** (`ship_name`,
`ship_tax_id`, `ship_address`, `ship_detail` —portal/escalera—, `ship_city`,
`ship_province`, `ship_postal_code`, `ship_country`, `ship_phone`, `ship_mobile` y
los ids del mayorista `ship_country_id`, `ship_province_id`, `ship_poblacion_id`),
la de **facturación** (`bill_*`, si está vacía se factura a la de envío), `notes`
(nota interna del tendero), `customer_note` (lo que escribió el cliente),
`subtotal`, `tax_total`, `shipping`, `total`, el cobro (`payment_method`
`transferencia`|`cod`|`pickup`, `payment_status` 0/1, `paid_at`),
`id_tienda_idirecto` (cuenta usada al enviar), `idirecto_ref`
(`TIENDA-<slug>-<código>`), `sent_at`, `created_at`, `updated_at`.

**`mt_order_items`** — líneas del pedido.
`id`, `order_id`, `store_id`, `line_no`, `source` (`catalog`|`own`), `product_id`
(`productos.id` o `mt_own_products.id`), `sku`, `name`, `qty`, `price_customer`
(lo que paga el cliente **sin IVA**), `tax_rate`, `price_idirecto` (tarifa de la
tienda), `cost_idirecto` (coste del mayorista), `sent_at`, `idirecto_pedido_id`
(`pedidos.id` creado) y `created_at`.

**`mt_customers`** — clientes finales de cada tienda.
`id`, `store_id`, `email` (único por tienda), `password_hash` (NULL en invitados),
`name`, `tax_id`, `phone`, `mobile`, `is_guest`, `active`, `last_login_at`,
`created_at`, `updated_at`.

**`mt_customer_addresses`** — libreta de direcciones del cliente.
`id`, `store_id`, `customer_id`, `label`, `name`, `tax_id`, `address`, `detail`,
`postal_code`, `city`, `province`, `country`, ids del mayorista (`id_pais`,
`id_provincia`, `id_poblacion`), `phone`, `mobile`, `is_default_ship`,
`is_default_bill`, `active` (0 = borrada por el cliente), `created_at`, `updated_at`.

- El envío al mayorista es **por línea**: `sent_at` + `idirecto_pedido_id` marcan
  cada línea ya enviada, de modo que un pedido de 4 líneas se puede mandar en dos
  veces (2+2) sin repetir nada.
- Los productos `own` **no** se envían al mayorista (no están en su catálogo).
- El **precio de venta** de la web es la tarifa de la tienda + su beneficio y **con
  IVA**; en la línea se guarda la base sin IVA y el IVA se suma en `tax_total`, así
  el total coincide con lo que vio el cliente.
- Al enviar a `pedidos_addr` se le pasa la **dirección del cliente** (con sus ids y
  el email en `localidad`) y su comentario en `pedidos.detalles`.

## Medios

**`mt_media`** — **en la base de datos solo hay claves y URLs, nunca el binario**.
`id`, `store_id`, `folder`, `driver` (`local`|`s3`), `file_key`, `url`, `mime`,
`bytes`, `width`, `height`, `created_at`

## Dominios y DNS

**`mt_domains`** — `id`, `store_id`, `domain`, `type`, `method` (`A`|`CNAME`|`BOTH`),
`is_primary`, `expected_a`, `expected_cname`, `status`, `last_check`,
`last_result`, `token`, `verified_at`, `created_at`

**`mt_dns_log`** — historial de comprobaciones: `id`, `domain_id`, `status`,
`records_json`, `message`, `created_at`

## Control

**`mt_migrations`** — `id`, `filename`, `applied_at`

---

## Cómo evolucionar el esquema

1. Crea `database/migrations/00N_descripcion.sql` usando `CREATE TABLE IF NOT
   EXISTS` / `ALTER TABLE` idempotente (ver helpers en `001_schema.sql`).
2. Ejecuta `php database/migrate.php` (y `--seed` si añades datos de ejemplo).
3. **No** uses transacciones alrededor de DDL: MySQL hace *commit* implícito.
4. Documenta el cambio en `.agents/CHANGELOG.md`.
