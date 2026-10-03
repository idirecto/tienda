<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use Tienda\Core\CatalogUrl;
use Tienda\Core\Config;
use Tienda\Core\Controller;
use Tienda\Core\Str;
use Tienda\Core\View;
use Tienda\Models\Banner;
use Tienda\Models\Catalog;
use Tienda\Models\ContentBlock;
use Tienda\Models\Menu;
use Tienda\Models\Notice;
use Tienda\Models\OwnProduct;

/**
 * Storefront publico de la tienda (resuelto por hostname).
 */
final class StorefrontController extends Controller
{
    public function home(array $params = []): string
    {
        $storeId = $this->tenant->id();
        $base = \Tienda\Core\View::basePath();
        // Los destacados de portada tienen su propio numero: no es un listado
        // paginado y no debe crecer porque se suba `CATALOG_PER_PAGE`.
        $perPage = (int) Config::get('catalog.home_featured', 12);

        $own = OwnProduct::publishedForStore($storeId);
        $central = Catalog::featured($perPage);

        return $this->view($this->themeView('home'), [
            'banners'      => Banner::visibleForStore($storeId, 'hero'),
            'notices'      => Notice::visibleForStore($storeId),
            'blocks'       => ContentBlock::forStore($storeId),
            'ownProducts'  => $own,
            'central'      => $central,
            // Accesos rapidos resueltos contra el catalogo real (con stock).
            'quickLinks'   => Catalog::quickCategories($base),
            'catalogReady' => Catalog::isAvailable(),
            'pageTitle'    => $this->tenant->metaTitle(),
        ], 'shop');
    }

    public function catalog(array $params = []): string
    {
        // Las rutas SEO son el esquema canonico: si llega la version con query
        // string, se responde 301 a su ruta equivalente (una sola redireccion).
        // En las rutas SEO ya canonizadas el destino coincide, asi que no hay
        // bucle; la busqueda (`?q=`) se queda como esta.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $canonical = CatalogUrl::canonicalFromQuery($_GET);
            if ($canonical !== null) {
                $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
                $actual = $this->router->currentPath() . ($query !== '' ? '?' . $query : '');
                if ($canonical !== $actual) {
                    header('Location: ' . $this->url(ltrim($canonical, '/')), true, 301);
                    exit;
                }
            }
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
        $category = isset($_GET['cat']) ? (int) $_GET['cat'] : null;
        $subcategory = isset($_GET['subcat']) ? (int) $_GET['subcat'] : null;

        // Etiqueta comercial (ofertas, novedades, destacados): la usan los
        // accesos rapidos del menu (/ofertas, /novedades, /destacados).
        $tag = Catalog::tagKey(isset($_GET['etiqueta']) ? (string) $_GET['etiqueta'] : null);

        // Filtros avanzados (se sanean contra config/catalog.php) y orden.
        $selection = Catalog::selectionFromQuery($_GET);

        // El listado esta "acotado" cuando hay algo que reduce el numero de
        // candidatos. Solo entonces se ofrece ordenar por precio (ver
        // Catalog::sorts): sobre el catalogo entero esa orden es cara.
        $narrowed = $category !== null
            || $subcategory !== null
            || ($q !== null && $q !== '')
            || $tag !== 'todos'
            || $selection['terms'] !== []
            || $selection['price_min'] !== null
            || $selection['price_max'] !== null;

        $sort = Catalog::sortKey(isset($_GET['orden']) ? (string) $_GET['orden'] : null, $narrowed);

        $menu = Catalog::menuTree();

        // La subcategoria manda: fija tambien su categoria para que filtros,
        // titulo y migas de pan queden coherentes entre si. Si llegan las dos y
        // no concuerdan (p. ej. al cambiar la categoria en el formulario sin
        // quitar la subcategoria), manda la categoria.
        $subInfo = $subcategory !== null ? Catalog::subcategory($subcategory) : null;
        if ($subInfo === null) {
            $subcategory = null;
        } elseif ($category !== null && $category > 0 && (int) $subInfo['categoria_id'] !== $category) {
            $subInfo = null;
            $subcategory = null;
        } else {
            $subcategory = (int) $subInfo['id'];
            $category = (int) $subInfo['categoria_id'];
        }

        $result = Catalog::paginate(
            $page,
            null,
            $q,
            $category,
            $subcategory,
            $selection['terms'],
            $selection['price_min'],
            $selection['price_max'],
            $sort,
            $tag
        );

        // Categoria activa: nombre y subcategorias (menu lateral del catalogo).
        $categoryName = null;
        $subcategories = [];
        if ($category !== null) {
            foreach ($menu as $cat) {
                if ((int) $cat['id'] === $category) {
                    $categoryName = (string) $cat['name'];
                    $subcategories = $cat['subcategories'];
                    break;
                }
            }
        }

        $title = $subInfo['name'] ?? $categoryName ?? 'Catalogo';
        if ($tag !== 'todos') {
            $tagLabel = Catalog::tagLabel($tag);
            $title = ($categoryName !== null && $subInfo === null)
                ? $tagLabel . ' en ' . $categoryName
                : $tagLabel;
        }

        return $this->view($this->themeView('catalog'), [
            'result'        => $result,
            'menu'          => $menu,
            'q'             => $q ?? '',
            'category'      => $category,
            'categoryName'  => $categoryName,
            'subcategory'   => $subInfo,
            'subcategories' => $subcategories,
            'title'         => $title,
            'tag'           => $tag,
            'tags'          => Catalog::tags(),
            'brandId'       => $this->selectedBrandId($selection),
            'facets'        => Catalog::facets($category, $subcategory, $selection, $tag, $q),
            'structuredFacets' => Catalog::structuredFilters($subcategory, $selection, $tag, $q),
            'selection'     => $selection,
            'activeFilters' => $selection['flat'],
            'sorts'         => Catalog::sorts($narrowed),
            'sort'          => $sort,
            'ownProducts'   => OwnProduct::publishedForStore($this->tenant->id()),
            'catalogReady'  => Catalog::isAvailable(),
            'pageTitle'     => $title . ' - ' . $this->tenant->name(),
        ], 'shop');
    }

    /** Id de marca activa en la seleccion de facetas (si la hay). */
    private function selectedBrandId(array $selection): int
    {
        $key = Catalog::brandFacetKey();
        foreach ((array) ($selection['terms'][$key] ?? []) as $value) {
            $id = (int) $value;
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * Listado por ruta SEO (/categoria, /categoria/subcategoria, .../m/46,
     * .../etiqueta/ofertas, .../orden/precio-asc, .../page/2).
     *
     * La ruta se traduce a la misma entrada que usa /catalogo, de modo que hay
     * una sola implementacion del listado.
     */
    public function seoListado(array $params = []): string
    {
        $state = CatalogUrl::parse('/' . (string) ($params['ruta'] ?? ''));
        if ($state === null) {
            return $this->notFound();
        }

        $get = ['page' => $state['page']];
        if ($state['cat'] > 0) {
            $get['cat'] = $state['cat'];
        }
        if ($state['subcat'] > 0) {
            $get['subcat'] = $state['subcat'];
        }
        if ($state['f'] !== []) {
            $get['f'] = $state['f'];
        }
        if ($state['etiqueta'] !== 'todos') {
            $get['etiqueta'] = $state['etiqueta'];
        }
        if ($state['orden'] !== '') {
            $get['orden'] = $state['orden'];
        }

        // Lo que no viene en la ruta se conserva de la query string: facetas
        // (socket, memoria...), rango de precio, orden y busqueda. Asi una ruta
        // SEO con filtros activos no pierde nada al pasar por el controlador.
        foreach (['pmin', 'pmax', 'q', 'orden'] as $key) {
            if (!isset($get[$key]) && isset($_GET[$key]) && (string) $_GET[$key] !== '') {
                $get[$key] = is_array($_GET[$key]) ? $_GET[$key] : (string) $_GET[$key];
            }
        }
        if (!isset($get['page']) || (int) $get['page'] <= 1) {
            if (isset($_GET['page']) && (int) $_GET['page'] > 1) {
                $get['page'] = (int) $_GET['page'];
            }
        }

        $facets = (array) ($get['f'] ?? []);
        $brandKey = Catalog::brandFacetKey();
        foreach ((array) ($_GET['f'] ?? []) as $key => $values) {
            if ((string) $key === $brandKey) {
                continue; // La marca viene en la ruta.
            }
            $facets[$key] = $values;
        }
        if ($facets !== []) {
            $get['f'] = $facets;
        }

        $_GET = $get;

        return $this->catalog();
    }

    /** Listado por etiqueta: /ofertas, /novedades, /destacados. */
    public function tag(array $params = []): string
    {
        $path = trim($this->router->currentPath(), '/');
        if (Catalog::tagKey($path) !== $path) {
            return $this->notFound();
        }

        // La etiqueta reescribe la ruta, pero hay que conservar lo que ya venia
        // en la query (facetas del mayorista y de configuracion, precio, orden
        // y pagina): antes se perdia todo `f[...]` y marcar una marca en
        // /ofertas devolvia el catalogo entero.
        $original = $_GET;
        $_GET = ['etiqueta' => $path, 'page' => max(1, (int) ($original['page'] ?? 1))];
        foreach (['pmin', 'pmax', 'orden'] as $key) {
            if (isset($original[$key]) && (string) $original[$key] !== '') {
                $_GET[$key] = (string) $original[$key];
            }
        }
        if (isset($original['f']) && is_array($original['f']) && $original['f'] !== []) {
            $_GET['f'] = $original['f'];
        }

        return $this->catalog();
    }

    /** Directorio de marcas con stock: /marcas. */
    public function brands(array $params = []): string
    {
        if (!Catalog::isAvailable()) {
            return $this->notFound();
        }

        return $this->view($this->themeView('marcas'), [
            'brands'    => Catalog::brandList(200),
            'pageTitle' => 'Marcas - ' . $this->tenant->name(),
        ], 'shop');
    }

    /** Listado de una marca: /marca/{id}. */
    public function brand(array $params = []): string
    {
        $id = (int) ($params['id'] ?? 0);
        $label = Catalog::brandLabel($id);
        if ($label === null) {
            return $this->notFound();
        }

        // La marca es el destino de la ruta, pero se conservan las facetas,
        // el precio y el orden que ya venian (si no, ordenar o paginar en la
        // pagina de una marca la dejaba sin marca).
        $original = $_GET;
        $_GET = [
            'f'    => [Catalog::brandFacetKey() => [$id]],
            'page' => max(1, (int) ($original['page'] ?? 1)),
        ];
        foreach (['pmin', 'pmax', 'orden'] as $key) {
            if (isset($original[$key]) && (string) $original[$key] !== '') {
                $_GET[$key] = (string) $original[$key];
            }
        }
        foreach ((array) ($original['f'] ?? []) as $key => $values) {
            if ((string) $key !== Catalog::brandFacetKey()) {
                $_GET['f'][$key] = $values;
            }
        }

        return $this->catalog();
    }

    /** 404 del storefront. */
    private function notFound(): string
    {
        http_response_code(404);

        return $this->view('errors/404', ['pageTitle' => 'Pagina no encontrada'], 'shop');
    }

    /**
     * Bloque promocional del panel de una categoria del menu (banner, productos
     * destacados y marcas). El HTML lo genera el servidor con la misma tarjeta
     * que el catalogo, de modo que no hay maquetacion ni colores duplicados en
     * JavaScript.
     */
    public function menuPanel(array $params = []): string
    {
        $categoryId = (int) ($params['id'] ?? 0);

        $label = null;
        foreach (Catalog::menuTree() as $cat) {
            if ((int) $cat['id'] === $categoryId) {
                $label = (string) $cat['name'];
                break;
            }
        }
        if ($label === null) {
            return $this->json(['html' => '', 'error' => 'Categoria no encontrada.'], 404);
        }

        $panel = Menu::panel($this->tenant->id(), $categoryId);
        $html = View::render($this->themeView('_menu_panel'), [
            'banner'   => $panel['banner'],
            'products' => $panel['products'],
            'brands'   => $panel['brands'],
            'tenant'   => $this->tenant,
            'base'     => View::basePath(),
        ]);

        return $this->json(['html' => $html, 'category' => $label]);
    }


    /**
     * Ficha de producto con URL SEO: /producto/{slug}/{id}
     * El slug es cosmetico para posicionamiento; el id es la clave real.
     */
    public function product(array $params = []): string
    {
        $id = (int) ($params['id'] ?? 0);
        $slug = (string) ($params['slug'] ?? '');

        $product = Catalog::detail($id);

        if ($product === null) {
            http_response_code(404);
            return $this->view('errors/404', ['pageTitle' => 'Producto no encontrado'], 'shop');
        }

        // Si el slug no es el canonico, redirige 301 para evitar contenido duplicado.
        if ($slug === '' || $slug !== (string) $product['slug']) {
            header('Location: ' . product_url($product), true, 301);
            exit;
        }

        return $this->view($this->themeView('product'), [
            'product'         => $product,
            'canonical'       => absolute_url(product_url($product)),
            // El nombre del producto es el primer factor SEO del <title>.
            'pageTitle'       => Str::excerpt((string) $product['nombre'], 65) . ' - ' . $this->tenant->name(),
            'pageDescription' => $this->productDescription($product),
        ], 'shop');
    }

    /** /producto/{id} -> 301 a la URL canonica con slug (enlaces antiguos). */
    public function productLegacy(array $params = []): string
    {
        $id = (int) ($params['id'] ?? 0);
        $product = Catalog::find($id);

        if ($product === null) {
            http_response_code(404);
            return $this->view('errors/404', ['pageTitle' => 'Producto no encontrado'], 'shop');
        }

        header('Location: ' . product_url($product), true, 301);
        exit;
    }

    private function productDescription(array $product): string
    {
        $partes = array_filter([
            (string) ($product['marca_nombre'] ?? ''),
            (string) ($product['nombre'] ?? ''),
            (string) ($product['part_number'] ?? ''),
        ]);
        return \Tienda\Core\Str::excerpt(implode(' ', $partes) . '. Compra online en ' . $this->tenant->name() . '.');
    }

    public function contact(array $params = []): string
    {
        return $this->view($this->themeView('contact'), [
            'pageTitle' => 'Contacto - ' . $this->tenant->name(),
        ], 'shop');
    }

    public function page(array $params = []): string
    {
        $slug = (string) ($params['slug'] ?? '');
        $block = ContentBlock::findForStoreBySlug($this->tenant->id(), $slug);

        if ($block === null) {
            http_response_code(404);
            return $this->view('errors/404', ['pageTitle' => 'Pagina no encontrada'], 'shop');
        }

        return $this->view($this->themeView('page'), [
            'block'     => $block,
            'pageTitle' => (string) ($block['title'] ?? $slug),
        ], 'shop');
    }

}
