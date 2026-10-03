# Menú de la tienda: elegir categorías y crear las propias — auditoría y propuesta (2026-10-03)

> **Estado: implementado** (2026-10-03). Las cuatro decisiones del punto 4 están
> confirmadas por el dueño y el resultado se verifica en `tools/verify.php`
> (**TODO OK 200**, 14 comprobaciones nuevas) y en navegador real.
> Regla que se sigue: primero reutilizar lo que ya existe; solo se crea estructura
> nueva cuando no hay equivalente. El cierre está en el §6.

---

## 1. La petición

> «En el panel de la tienda, se puede mostrar el menú completo o dar a elegir qué
> categorías se mostrarán, pero todas las categorías y subcategorías se basan en las
> que ya cuenta `/var/www/html/idirecto` o `/var/www/html/puntobyze`, que son de la
> misma base de datos; si la tienda decide no mostrar una subcategoría o categoría eso
> se debe guardar en algún lado. Además, dar la posibilidad de que agreguen una
> categoría y subcategoría, por ejemplo "productos propios" o "Servicio", que ellos
> quieran agregar, y que solo esos valores se muestren en su web.»

Se confirma que **el catálogo de categorías y subcategorías es el compartido**
(`categorias`, `subcategorias`, `productos` de `idirecto_db`, que es la misma base que
usa PuntoByZE): es de **solo lectura** y no se toca.

---

## 2. Lo que ya existe (comprobado en la base de datos, no en la documentación)

| Necesidad | Dónde ya vive | Estado |
|---|---|---|
| Árbol de menú editable | `mt_menu_items` (465 nodos hoy, todos de la tienda demo) | ✅ |
| Activar / desactivar un nodo | `mt_menu_items.active` | ✅ |
| Nodos propios de una tienda | `mt_menu_items.store_id` (= tienda); `NULL` = compartido de la plataforma | ✅ |
| Visibilidad de un compartido por tienda | `visibility` (`todas\|solo\|excepto`) + `mt_menu_item_stores` | ⚠️ **La controla la plataforma, no la tienda** |
| Borrador + publicación | `mt_menu_published` (el escaparate lee el snapshot) | ✅ |
| Productos propios de la tienda | `mt_own_products` + `OwnProduct::publishedForStore()` | ✅ (pero solo en la portada) |
| Páginas de contenido | `mt_content_blocks` + `/pagina/{slug}` | ✅ |
| Nivel de cliente | `mt_menu_items.min_customer_level` contra `categoria_cliente` | ✅ |
| Ocultar categoría vacía | `mt_menu_items.hide_empty` | ✅ |

**Lo que la tienda NO puede hacer hoy**

1. **Ocultar o renombrar un nodo que no sea suyo.** `MenuAdmin::canEdit()` solo deja
   tocar nodos con `store_id` = la tienda; los compartidos son de solo lectura para
   ella. Es el pendiente §7.6.2 del documento `MENU-ADMIN-2026-10-02.md`.
2. **Dar destino propio a una categoría o subcategoría propia.** `Menu::buildTree()`
   ignora el destino en los niveles 1 y 2: la ruta sale *siempre* de
   `CatalogUrl::categoryPath($target_id) ?? '/catalogo'` (nivel 1) o del ancla de
   subcategoría / del padre (nivel 2). Por eso «Servicio» o «Productos propios»
   acaban en `/catalogo`. Además, una categoría sin grupos y un grupo sin enlaces se
   descartan, así que una categoría propia vacía no se pinta.
3. **Un listado de «Productos propios»**: no existe `/propios`; `OwnProduct` solo se
   usa en la portada y en el catálogo mezclado con el central.
4. **Colgar un nodo propio dentro de una categoría del catálogo**: `validate()` y
   `move()` lo prohíben expresamente («No puedes colgar nodos de una categoria de la
   plataforma»).

---

## 3. Propuesta (solo lo nuevo)

### 3.1 Migración `008` (aditiva e idempotente, sin borrar nada)

| Estructura | Tipo | Para qué |
|---|---|---|
| `mt_stores.menu_scope` | `varchar(12) NOT NULL DEFAULT 'completo'` | `completo` = se ve todo el menú; `elegido` = se ve solo lo que la tienda marque |
| `mt_menu_item_overrides` | tabla nueva | La decisión de **cada tienda** sobre **cada nodo**, sin tocar la fila compartida |

```
mt_menu_item_overrides
  id, store_id, item_id,
  state varchar(8)  'visible' | 'oculto'
  label varchar(120) NULL   -- renombrado solo para esta tienda
  created_at, updated_at
  UNIQUE (store_id, item_id)
  FK store_id -> mt_stores(id)      ON DELETE CASCADE
  FK item_id  -> mt_menu_items(id)  ON DELETE CASCADE
```

**Por qué una tabla de anulaciones y no duplicar nodos:** el árbol puede ser compartido
de la plataforma; duplicarlo por tienda obligaría a mantener dos copias sincronizadas.
La anulación guarda solo lo que cambia (mostrar/ocultar y el nombre) y es trivial de
deshacer (borrar la fila).

### 3.2 Cómo se decide qué se ve (una sola función)

Para la tienda, un nodo se pinta si:

1. Pasa los filtros que ya existían: `active = 1`, visibilidad de plataforma
   (`visibility`), nivel de cliente (`min_customer_level`) y `hide_empty`.
2. No está **oculto** por la tienda (anulación propia o de un ancestro suyo).
3. Según el modo:
   - `completo`: se pinta (es el comportamiento de hoy).
   - `elegido`: se pinta solo si está **marcado como visible** o es ancestro/descendiente
     de uno marcado (marcar una categoría muestra su rama; marcar un enlace muestra su
     camino). Los nodos **creados por la tienda** nacen marcados como visibles.

El renombrado por tienda (`label`) se aplica en la lectura, así que el escaparate pinta
el nombre propio sin tocar el nodo compartido.

### 3.3 Destinos nuevos para las categorías y subcategorías propias

Se añaden dos tipos de destino al editor:

- `propios` → `/propios`, listado **solo con los productos propios publicados** de la
  tienda (ruta y vista nuevas). Es el «Productos propios» de la petición.
- `pagina` → `/pagina/{slug}` de una **página de contenido de la tienda**
  (`mt_content_blocks`). Es el «Servicio» de la petición (o cualquier página suya).

Y `Menu::buildTree()` pasa a respetar el destino en los niveles 1 y 2, de modo que una
categoría o un grupo con destino propio (enlace libre, `/propios` o página) se pinta
aunque no tenga hijos, con la ruta que le corresponde.

### 3.4 Poder colgar nodos propios de categorías del catálogo

Se levanta la prohibición de `validate()` y `move()` para que la tienda pueda crear un
grupo o un enlace **dentro de una categoría compartida** (p. ej. «Servicio» dentro de
«Componentes»). El nodo nuevo es suyo (`store_id` = tienda) y solo lo ve ella; el nodo
compartido sigue siendo de solo lectura.

### 3.5 Panel de la tienda

- Tarjeta **«Qué categorías se ven»** con los dos modos («Menú completo» / «Solo lo que
  yo elija») y, debajo, la lista de categorías y subcategorías con un interruptor
  **Mostrar / Ocultar** (escribe la anulación) y un campo para **renombrar** cada una.
- El árbol de edición se mantiene para ordenar, crear, borrar, iconos, badges y
  banners, con los botones **«+ Categoría propia»**, **«+ Productos propios»** y
  **«+ Página de la tienda»**.
- Todo sigue el flujo de borrador + **Publicar** que ya existe.

### 3.6 Aislamiento entre tiendas (regla dura, no cambia)

- El `store_id` sale **siempre** de la sesión, nunca del formulario.
- Las anulaciones se leen y se escriben con `store_id` en el `WHERE`.
- Una tienda no puede leer ni escribir anulaciones de otra, ni tocar nodos compartidos.
- `verify.php` lo comprueba con **dos tiendas** (transacción deshecha).

---

## 4. Decisiones confirmadas por el dueño (2026-10-03)

| Pregunta | Decisión |
|---|---|
| Cómo elige la tienda sus categorías | **Modo «completo» / «elegido» + interruptor Mostrar/Ocultar por nodo** |
| Dónde pueden colgar las categorías propias | **También dentro de categorías del catálogo** |
| Destinos de una categoría propia | **Productos propios (`/propios`) + páginas de contenido + enlace libre** |
| Qué se personaliza de los nodos compartidos | **Ocultar/mostrar y renombrar** (no reordenar) |

---

## 5. Plan de implementación

- **Migración `008`**: `menu_scope` + `mt_menu_item_overrides`.
- **`Menu`**: modo, anulaciones, renombrado y destinos propios en los niveles 1 y 2.
- **`MenuAdmin`**: nodos propios bajo padres compartidos, tipos `propios`/`pagina`,
  API de anulación y alta marcada como visible.
- **Escaparate**: ruta `/propios` + vista con la rejilla de productos propios.
- **Panel**: tarjeta de modo + lista con Mostrar/Ocultar y renombrar; altas rápidas.
- **Verificación**: `verify.php` (estructura, modos, anulaciones, aislamiento con dos
  tiendas, destinos propios) y navegador real (Chrome por CDP).

---

## 6. Cierre: qué se construyó (2026-10-03)

### 6.1 Estructuras creadas

- **`009_menu_tienda.sql`** (idempotente, sin borrar nada): `mt_stores.menu_scope`
  (`completo|elegido`, por defecto `completo`) y `mt_menu_item_overrides`
  (`store_id`, `item_id`, `state` `visible|oculto`, `label` NULL, `UNIQUE(store_id,
  item_id)`, claves foráneas en cascada).
- **`config/menu.php`**: `scopes` y `fallback_scope` (los dos modos, saneados).
- Nada más: el catálogo (`categorias`, `subcategorias`, `productos`) sigue siendo
  **solo lectura**, y se reutilizan `mt_menu_items`, `mt_content_blocks` y
  `mt_own_products`.

### 6.2 Cómo decide el escaparate

- `Menu::scope()` / `Menu::scopeForStore()` sanean el modo; `Menu::choiceList()` arma
  la lista del panel (niveles 1 y 2, con el estado efectivo de cada uno).
- `Menu::visibleRows()` aplica a las filas los filtros que ya existían (activo,
  visibilidad de plataforma, nivel de cliente) y después `applyStoreChoice()`:
  renombra, oculta y, en modo «elegido», deja solo lo marcado más su rama
  (descendientes) y su camino (ancestros). Un `oculto` explícito manda y se lleva
  su rama. `publish()` usa esa misma función, así que el snapshot publicado ya sale
  filtrado y la caché se invalida al publicar.
- `MenuAdmin::setOverride()` guarda la decisión **sin tocar la fila del nodo** (que
  puede ser compartida); `clearOverride()` la deshace. El `store_id` sale siempre de
  la sesión.

### 6.3 Categorías y subcategorías propias

- Nuevos destinos `propios` (listado `/propios`, ruta y vista nuevas) y `pagina`
  (`/pagina/{slug}` de una página de contenido de la tienda), además del enlace libre.
- `Menu::buildTree()` respeta el destino en los niveles 1 y 2: una categoría o un
  grupo propio con destino se pinta **aunque no tenga hijos**, y las categorías del
  catálogo conservan su ruta y su panel promocional (compatible con instantáneas
  publicadas antes de este cambio).
- La tienda **ya puede colgar sus nodos dentro de una categoría compartida** (se
  levantó la prohibición en `validate()` y `move()`); el nodo nuevo es suyo y solo lo
  ve ella, y el compartido sigue siendo de solo lectura.
- `menu_href()` respeta los enlaces absolutos (antes un `https://…` se convertía en
  `https://local.tiendahttps://…`).

### 6.4 Panel de la tienda

- Tarjeta **«Qué categorías se ven»**: modo (completo/elegido), interruptor
  Mostrar/Ocultar y nombre propio por categoría y subcategoría, y «Volver al árbol».
- Botones **«+ Productos propios»** y **«+ Página de la tienda»** con el formulario
  preconfigurado.
- El editor rellena ahora el destino al editar (antes se perdía) y solo muestra los
  campos del tipo elegido. Los nodos compartidos muestran «Compartida: la gestiona la
  plataforma» en vez de botones que no funcionaban.

### 6.5 Verificación

- `php tools/verify.php` → **TODO OK (200)**, con 14 comprobaciones nuevas (migración,
  modos, anulación por tienda, rama oculta, aislamiento con dos tiendas, destinos
  propios y nodo bajo compartido).
- Navegador real (Chrome por CDP): tarjeta con 14 categorías y 84 subcategorías,
  interruptor Ocultar/Mostrar y «Volver al árbol», editor con destino precargado y
  presets, sin errores de JavaScript.
- HTTP real: `/propios` 200, categoría propia «Productos propios» enlazando a
  `/propios`, subcategoría propia dentro de una categoría del catálogo pintada con su
  página, y el menú Compacto y Catálogo sin regresiones (14 paneles promocionales).

### 6.6 Pendiente

- **Reordenar** las categorías del catálogo solo para una tienda (se decidió no
  incluirlo ahora): la anulación guarda mostrado/oculto y nombre, no `sort`.
