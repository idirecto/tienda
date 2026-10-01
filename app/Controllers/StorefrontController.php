<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use Tienda\Core\Config;
use Tienda\Core\Controller;
use Tienda\Core\Str;
use Tienda\Models\Banner;
use Tienda\Models\Catalog;
use Tienda\Models\ContentBlock;
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
        $perPage = (int) Config::get('catalog.per_page', 12);

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
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
        $category = isset($_GET['cat']) ? (int) $_GET['cat'] : null;
        $subcategory = isset($_GET['subcat']) ? (int) $_GET['subcat'] : null;

        // Filtros avanzados (se sanean contra config/catalog.php) y orden.
        $selection = Catalog::selectionFromQuery($_GET);

        // El listado esta "acotado" cuando hay algo que reduce el numero de
        // candidatos. Solo entonces se ofrece ordenar por precio (ver
        // Catalog::sorts): sobre el catalogo entero esa orden es cara.
        $narrowed = $category !== null
            || ($q !== null && $q !== '')
            || $selection['terms'] !== []
            || $selection['price_min'] !== null
            || $selection['price_max'] !== null;

        $sort = Catalog::sortKey(isset($_GET['orden']) ? (string) $_GET['orden'] : null, $narrowed);

        $menu = Catalog::menuTree();

        // La subcategoria manda: fija tambien su categoria para que filtros,
        // titulo y migas de pan queden coherentes entre si.
        $subInfo = $subcategory !== null ? Catalog::subcategory($subcategory) : null;
        if ($subInfo === null) {
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
            $sort
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

        return $this->view($this->themeView('catalog'), [
            'result'        => $result,
            'menu'          => $menu,
            'q'             => $q ?? '',
            'category'      => $category,
            'categoryName'  => $categoryName,
            'subcategory'   => $subInfo,
            'subcategories' => $subcategories,
            'title'         => $title,
            'facets'        => Catalog::facets($category, $subcategory, $selection),
            'selection'     => $selection,
            'activeFilters' => $selection['flat'],
            'sorts'         => Catalog::sorts($narrowed),
            'sort'          => $sort,
            'ownProducts'   => OwnProduct::publishedForStore($this->tenant->id()),
            'catalogReady'  => Catalog::isAvailable(),
            'pageTitle'     => $title . ' - ' . $this->tenant->name(),
        ], 'shop');
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

    /** Plantilla activa con fallback al tema base. */
    private function themeView(string $name): string
    {
        $theme = preg_replace('/[^a-z0-9_\-]/i', '', $this->tenant->theme()) ?: 'idirecto';
        if (!is_file(TIENDA_BASE . "/app/Views/themes/{$theme}/{$name}.php")) {
            $theme = 'idirecto';
        }
        return "themes/{$theme}/{$name}";
    }
}
