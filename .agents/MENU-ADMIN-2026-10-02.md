# Menú administrable desde el panel — auditoría y propuesta (2026-10-02)

> **Estado: implementado** (2026-10-02). Este documento fue la propuesta previa; se
> aprobaron las cuatro decisiones del punto 5 y el resultado está en el código y
> verificado. El detalle de lo construido está al final (§7).
> Regla que se siguió: primero reutilizar lo que ya existe; solo se crea estructura
> nueva cuando no hay equivalente.

---

## 1. Qué tablas ya existen y para qué se reutilizan

Comprobado contra `idirecto_db` (no contra documentación).

| Necesidad | Tabla existente | Filas | Cómo se reutiliza |
|---|---|---|---|
| **Categorías** | `categorias` (`id`, `categoria`, `orden`) | 36 | destino de los nodos de nivel 1 y 2. **Solo lectura** (es del mayorista) |
| **Subcategorías** | `subcategorias` (`id`, `id_categoria`, `subcategoria`, `sujeto`, `orden`, `canon`) | 363 | destino de los nodos de nivel 3 |
| **Productos** | `productos` (`id`, `nombre`, `id_categoria`, `id_subcategoria`, `marca`, `id_marca`, `etiqueta`, `fecha_alta`, `estado`…) | 235.165 | contadores, etiquetas (ofertas, novedades, destacados) y «categoría vacía» |
| **Marcas** | `marcas` (`id`, `marca`, `imagen`…) | 1.296 | destinos `m/marca/{id}` y fichas de marca |
| **Tiendas** | `mt_stores` | 1 | **ya tiene `menu_style`** (`compacto\|catalogo`) y `header_style`: el «tipo de menú» **no necesita tabla nueva** |
| **Configuración de tiendas** | `mt_settings` (`store_id`, `key`, `value`) | 0 | clave/valor por tienda: `menu.hide_empty`, `menu.brand_limit`… |
| **Usuarios** | `mt_store_users` (`store_id`, `email`, `password_hash`, **`role`** default `owner`, `active`) | 1 | la columna `role` ya existe: se le puede añadir el rol de plataforma sin tabla nueva |
| **Banners** | `mt_banners` (`store_id`, `title`, `subtitle`, `link`, `media_id`, `image_url`, `position`, `sort`, `active`, `starts_at`, `ends_at`) | 0 | el «banner» del menú es una referencia a un banner ya existente (`position = 'menu'`, que ya usa el código) |
| **Niveles de cliente** | `categoria_cliente` (`id`, `descripcion`, `orden`, `deleted`) | 4 | el «nivel mínimo de cliente» referencia aquí: **10** Tiendas de Informática, **11** Tiendas de telefonía, **12** Tiendas de Papelería (13 está borrado) |
| **Nivel de cada tienda** | `tiendas.id_categ_cliente` / `id_subCateg_cliente` | 9.539 | de aquí sale el nivel real de la tienda dueña (`mt_stores.id_tienda_idirecto`) |
| **Clientes** | `mt_customers` (nuestro) + `clientes`, `e_clientes`, `tiendas_clientes` (mayorista) | 0 / 2.156 / 3.094 / 70 | se usan tal cual; el menú no necesita tabla de clientes |
| **Nodos del menú** | `mt_menu_items` (`store_id`, `parent_id`, `level`, `label`, `slug`, `target_type`, `target_id`, `target_extra`, `target_key`, `url`, `badge`, `sort`, `active`, `note`, timestamps) | 465 | es la tabla del árbol: se **amplía** en vez de crear otra |
| **Menú editorial de referencia** | `websites_menu` → `websites_list` → `websites_item` | — | fuente de la siembra (solo lectura) |
| **Exclusión por tienda del feed** | `feed_tienda_subcategorias_excluidas` (`id_tienda`, `id_subcategoria`, `motivo`) | 32 | referencia de cómo el mayorista oculta subcategorías por tienda; **no** se toca (es suya y solo sirve para su feed) |

De los 17 campos que pediste guardar, **8 ya existen**: tipo de menú, categoría padre,
nombre, slug, orden, activo, texto de badge y fechas de creación/actualización.

---

## 2. Lo que falta y se propone (estructuras nuevas)

### 2.1 Ampliación de `mt_menu_items` (aditiva, con valor por defecto)

| Columna | Tipo | Para qué |
|---|---|---|
| `icon` | `varchar(40) NULL` | clave del icono del set SVG propio (`grid`, `bolt`, `star`…) |
| `badge_color` | `varchar(9) NULL` | color del badge (hex o token `--c-*`); el **texto** ya está en `badge` |
| `banner_id` | `int unsigned NULL` + FK a `mt_banners` | banner del nodo, reutilizando los banners de la tienda |
| `banner_url` | `varchar(500) NULL` | enlace alternativo cuando el banner es una imagen externa, no un `mt_banners` |
| `min_customer_level` | `tinyint unsigned NULL` | nivel mínimo de cliente (`categoria_cliente.id`); `NULL` = para todos |
| `hide_empty` | `tinyint(1) NOT NULL DEFAULT 0` | configuración de categorías vacías (no mostrarla si no tiene productos) |
| `visibility` | `varchar(10) NOT NULL DEFAULT 'todas'` | `todas` \| `solo` \| `excepto` (se combina con la tabla 2.2) |
| `store_id` | pasa a **`NULL` admisible** | `NULL` = nodo de la plataforma (compartido); con valor = nodo propio de esa tienda (como hasta ahora) |

El cambio de `store_id` a nulable es reversible y no borra nada: solo se suelta y se
vuelve a crear su clave foránea.

### 2.2 `mt_menu_item_stores` (nueva, pequeña)

Qué tiendas ven un nodo cuando `visibility` no es `todas`.

```
item_id, store_id, created_at     PK(id) + UNIQUE(item_id, store_id)
FK → mt_menu_items(id) ON DELETE CASCADE, FK → mt_stores(id) ON DELETE CASCADE
```

### 2.3 `mt_menu_published` (nueva): borrador y publicación

```
store_id (PK), version, payload LONGTEXT, published_at, published_by
```

- El **panel edita las filas** de `mt_menu_items` (ese es el **borrador**).
- **Publicar** valida el árbol, lo serializa a JSON en `payload` y sube `version`.
- El **escaparate lee una sola fila** (el árbol publicado): más rápido, sin `JOIN`, y
  el visitante nunca ve un menú a medio editar.
- Si una tienda no ha publicado nunca, se sigue usando el árbol actual (nada se rompe).
- Opcional (fase 2): `mt_menu_revisions` para guardar el histórico de publicaciones.

**Por qué así y no duplicando filas con un campo `status`:** duplicar obliga a
mantener dos copias sincronizadas de un árbol de 465 nodos en cada guardado; el
snapshot publicado ocupa una fila y hace trivial el «descartar borrador».

---

## 3. Las 15 capacidades del panel, mapeadas

| # | Capacidad | Cómo se resuelve |
|---|---|---|
| 1 | Elegir Compacto / Catálogo | `mt_stores.menu_style`, saneado contra `Menu::styles()` (**existen solo esos dos**) |
| 2 | Reordenar con drag & drop | `sort` + `parent_id`; el arrastre envía el orden nuevo y se guarda en una transacción |
| 3 | Mover entre niveles | `parent_id` + `level` recalculado del subárbol afectado (sin ciclos) |
| 4 | Crear / editar / eliminar nodos | alta/baja/edición sobre `mt_menu_items`; el borrado de un padre borra su rama (FK en cascada) |
| 5 | Activar / desactivar | `active` |
| 6 | Nombres y slugs | `label`, `slug` (únicos por nivel dentro del padre) |
| 7 | Iconos | `icon` |
| 8 | Badges NUEVO / OFERTA / TOP | `badge` (texto) + `badge_color` (color) |
| 9 | Banners | `banner_id` (a `mt_banners`) o `banner_url` |
| 10 | Qué ve cada tienda | `visibility` + `mt_menu_item_stores` |
| 11 | Qué ve cada nivel de cliente | `min_customer_level` contra `categoria_cliente` |
| 12 | Previsualizar escritorio y móvil | vista previa con el mismo parcial (`_menu.php`) dentro de un `iframe` a dos anchos (1280 y 390) |
| 13 | Guardar borrador | guardar filas sin tocar `mt_menu_published` |
| 14 | Publicar | `POST /panel/menu/publicar` → valida + snapshot + `version++` |
| 15 | Invalidar caché al publicar | `Menu::invalidate()` (borra `storage/cache/menu_tree_v*_<store>.json`) |

---

## 4. Aislamiento entre tiendas (regla dura)

1. **El `store_id` nunca viene de la petición**: sale de la sesión (`Auth::storeId()`),
   como ya hace todo el panel. Ningún formulario lleva `store_id`.
2. Toda consulta de escritura lleva `store_id` en el `WHERE` (y `mt_menu_item_stores`
   se valida contra la tienda de la sesión), así que una tienda **no puede leer ni
   escribir** filas de otra aunque manipule los `id`.
3. Las pruebas de verificación incluirán un caso con **dos tiendas**: A no ve ni puede
   modificar nodos ni visibilidad de B.
4. Los nodos de plataforma (`store_id NULL`) son de **solo lectura** para la tienda;
   solo el rol de plataforma los edita.

---

## 5. Decisiones que necesitan tu visto bueno

1. **Modelo del menú**: global de plataforma con visibilidad por tienda (lo que piden
   los puntos 10 y 11) o árbol independiente por tienda.
2. **Quién es «el administrador»**: hoy solo existe el rol `owner` de cada tienda y no
   hay panel maestro.
3. **Borrador y publicación**: snapshot publicado (propuesto) o aplicar al guardar.
4. **Nomenclatura**: mantener `compacto|catalogo` (ya está en la base de datos) o
   cambiarlo a `compact|catalog`.

---

## 6. Plan de implementación (tras tu OK)

- **Fase A** — migración `006` (ampliación + 2 tablas + `store_id` nulable) y modelo
  `Menu` leyendo el árbol publicado.
- **Fase B** — panel: estilo, árbol editable (crear/editar/borrar, activar/desactivar,
  iconos, badges, banners), visibilidad por tienda y por nivel de cliente.
- **Fase C** — drag & drop (orden y cambio de nivel) y guardado en transacción.
- **Fase D** — borrador/publicar + invalidación de caché + vista previa escritorio/móvil.
- **Fase E** — ampliar `tools/verify.php` (incluido el caso de dos tiendas) y pruebas en
  navegador real del editor.


---

## 7. Cierre: qué se aprobó y qué se construyó

### 7.1 Decisiones aprobadas

| Pregunta | Decisión |
|---|---|
| Modelo del menú | **Global de plataforma + visibilidad por tienda** (`store_id` NULL = nodo compartido) |
| Quién administra | **Rol `platform`** en `mt_store_users` (columna que ya existía) |
| Borrador/publicación | **Borrador editable + publicación** con snapshot y version |
| Nomenclatura del tipo | **Se mantienen** `compacto` y `catalogo` |

### 7.2 Estructuras creadas (solo las necesarias)

- `006_menu_admin.sql`: 7 columnas en `mt_menu_items` (`icon`, `badge_color`,
  `banner_id`, `banner_url`, `min_customer_level`, `hide_empty`, `visibility`),
  `store_id` nulable (soltando y recreando la clave foránea, sin borrar filas) y las
  tablas `mt_menu_item_stores`, `mt_menu_published` y `mt_menu_revisions`.
- `007_menu_badge_color.sql`: `badge_color` a `varchar(32)` (los tokens `--c-*` no
  cabían en 9 y el editor daba un 500 al guardar).

Todo lo demás se reutiliza: `mt_stores.menu_style`, `mt_banners` (banner del nodo),
`mt_store_users.role`, `categoria_cliente` + `tiendas.id_categ_cliente` (niveles),
`mt_settings` (config de la tienda) y el catálogo del mayorista en solo lectura.

### 7.3 Las 15 capacidades, una a una

| # | Capacidad | Estado |
|---|---|---|
| 1 | Elegir «Menú Compacto» / «Menú Catálogo» | ✅ Panel > Menú (saneado: solo esos dos) |
| 2 | Reordenar con drag & drop | ✅ Se guarda al soltar (petición JSON a `/panel/menu/orden`) |
| 3 | Mover categorías entre niveles | ✅ Con recálculo de la rama, sin ciclos y sin pasar de 3 niveles |
| 4 | Crear, editar y eliminar nodos | ✅ El borrado se lleva la rama (clave foránea en cascada) |
| 5 | Activar / desactivar | ✅ Botón por nodo |
| 6 | Cambiar nombres y slugs | ✅ Slug único entre hermanos, se genera del nombre si se deja vacío |
| 7 | Añadir iconos | ✅ 31 iconos del juego SVG propio (`config/menu.php`) |
| 8 | Badges «NUEVO», «OFERTA», «TOP» | ✅ Texto libre + color (hex o token de la identidad visual) |
| 9 | Añadir banners | ✅ Banner de `mt_banners` (solo de esa tienda) o enlace de banner |
| 10 | Qué categorías ve cada tienda | ✅ `visibility` (todas / solo / excepto) + `mt_menu_item_stores` |
| 11 | Qué categorías ve cada nivel de cliente | ✅ `min_customer_level` contra el `orden` de `categoria_cliente` |
| 12 | Previsualizar en escritorio y móvil | ✅ Dos marcos reales (1280 px y 390 px) con el borrador |
| 13 | Guardar cambios como borrador | ✅ El panel edita el borrador; la tienda no cambia |
| 14 | Publicar cambios | ✅ Snapshot por tienda + version + histórico; publicar en todas si eres plataforma |
| 15 | Invalidar la caché al publicar | ✅ Se borran los ficheros `menu_tree_*_<tienda>_*.json` |

### 7.4 Aislamiento entre tiendas

- El `store_id` sale **siempre** de la sesión, nunca del formulario.
- Cada operación de `MenuAdmin` comprueba la propiedad del nodo; los nodos compartidos
  son de solo lectura para las tiendas y los de otra tienda ni se ven ni se tocan.
- Probado en `verify.php` con dos tiendas (transacción deshecha) y a mano: al intentar
  editar un nodo ajeno, el panel responde «Ese nodo no es de tu tienda».

### 7.5 Verificación

- `php tools/verify.php` → **TODO OK (176)** (36 comprobaciones nuevas del editor).
- Navegador real (Chrome por CDP): **20 comprobaciones del editor** (árbol de tres
  niveles, formulario, drag & drop, vista previa a 1280/390 y el 403 del panel de
  plataforma) + 38 del menú del escaparate, sin errores de JavaScript.
- `bash .agents/scripts/check-privacidad.sh` → OK y los ficheros nuevos responden 403.

### 7.6 Lo que queda para más adelante

1. **Filtros estructurados** (`f/…`) sobre `rel_filtro_producto` para activar los 14
   destinos pendientes y sacar las facetas de la query string. *(Hecho el 2026-10-03:
   migración `008_menu_filtros.sql`.)*
2. **Anulaciones por tienda**: hoy la tienda ve el árbol de la plataforma y sus propios
   nodos, pero no puede renombrar ni ocultar un nodo compartido solo para ella.
   *(Hecho el 2026-10-03: migración `009` + `mt_menu_item_overrides`; ver
   `.agents/MENU-TIENDA-2026-10-03.md`.)*
3. **Editor de banners** dentro del propio nodo (hoy se elige uno ya subido).
4. **Sitemap** que recoja las rutas SEO del menú publicado.
