<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Database;
use Tienda\Core\Session;
use Tienda\Core\View;
use Tienda\Models\Menu;
use Tienda\Models\MenuAdmin;

/**
 * Menu de navegacion de la tienda (borrador + publicacion).
 *
 * Aqui la tienda elige el estilo (Menu Compacto o Menu Catalogo), edita SUS
 * nodos, ordena, pone iconos y badges, y publica. Los nodos de la plataforma se
 * ven pero no se tocan: para eso esta el panel de plataforma.
 *
 * El `store_id` sale SIEMPRE de la sesion (`Auth::storeId()`), nunca del
 * formulario: es lo que garantiza que una tienda no pueda tocar otra.
 */
final class MenuController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        // Nivel de cliente: el estado incluye el motivo cuando no se puede
        // resolver, para que el aviso diga que falta y donde se arregla.
        $nivel = Menu::storeLevelStatus($storeId);

        return $this->view('panel/menu', [
            'pageTitle'   => 'Menu',
            'styles'      => Menu::styles(),
            'style'       => $this->tenant->menuStyle(),
            'scopes'      => Menu::scopes(),
            'menuScope'   => Menu::scopeForStore($storeId),
            'choice'      => Menu::choiceList($storeId),
            'stats'       => Menu::stats($storeId),
            'tree'        => MenuAdmin::tree($storeId),
            'pending'     => Menu::pending($storeId, 60),
            'warnings'    => Menu::review($storeId),
            'draft'       => Menu::hasDraftChanges($storeId),
            'version'     => Menu::publishedVersion($storeId),
            'publishedAt' => Menu::publishedAt($storeId),
            'destinos'    => MenuAdmin::destinations($storeId),
            'icons'       => Menu::iconOptions(),
            'badges'      => Menu::badgePresets(),
            'banners'     => MenuAdmin::bannerOptions($storeId),
            'niveles'     => Menu::customerLevels(),
            'nivelTienda' => $nivel['level'],
            'nivelEstado' => $nivel,
            'nivelAviso'  => $nivel['level'] === null ? Menu::levelWarning($nivel) : '',
            'scope'       => 'store',
            'actionBase'  => $this->url('panel/menu'),
            'isPlatform'  => false,
        ], 'panel');
    }

    /** Guarda el estilo de menu elegido. */
    public function save(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $style = strtolower(trim((string) $this->input('menu_style', '')));
        if (!isset(Menu::styles()[$style])) {
            Session::flash('error', 'Elige uno de los dos estilos de menu disponibles.');
            $this->redirect('panel/menu');
        }

        Database::update('mt_stores', $storeId, ['menu_style' => $style]);
        Menu::invalidate($storeId);

        $label = (string) (Menu::styles()[$style] ?? $style);
        Session::flash('success', 'Menu actualizado: ' . $label . '.');
        $this->redirect('panel/menu');
    }

    /**
     * Guarda el alcance del menu: «completo» o «elegido».
     *
     * Se guarda en `mt_stores.menu_scope` (el `store_id` sale de la sesion).
     */
    public function saveScope(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $scope = strtolower(trim((string) $this->input('menu_scope', '')));
        if (!isset(Menu::scopes()[$scope])) {
            Session::flash('error', 'Elige uno de los dos modos de menu.');
            $this->redirect('panel/menu');
        }

        Database::update('mt_stores', $storeId, ['menu_scope' => $scope]);
        Menu::invalidate($storeId);

        Session::flash(
            'success',
            $scope === 'elegido'
                ? 'Ahora tu web muestra solo las categorias que marques.'
                : 'Ahora tu web muestra el menu completo (menos lo que ocultes).'
        );
        $this->redirect('panel/menu');
    }

    /**
     * Muestra u oculta un nodo en la web de ESTA tienda, y le puede poner su
     * propio nombre, sin tocar el nodo (que puede ser compartido).
     */
    public function nodeOverride(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $id = (int) ($params['id'] ?? 0);
        $state = (string) $this->input('state', 'visible');
        $label = (string) $this->input('label', '');

        $result = MenuAdmin::setOverride($id, $storeId, $state, $label);
        $this->flashResult($result, 'Guardado.');

        $this->redirect('panel/menu');
    }

    /** Quita la anulacion de un nodo (vuelve a como esta en el arbol). */
    public function nodeOverrideClear(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $result = MenuAdmin::clearOverride((int) ($params['id'] ?? 0), $storeId);
        $this->flashResult($result, 'Vuelto al estado del arbol.');

        $this->redirect('panel/menu');
    }

    /** Crea un nodo (categoria, grupo o destino). */
    public function nodeCreate(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $result = MenuAdmin::create($storeId, false, $this->nodeInput());
        $this->flashResult($result, 'Nodo creado.');

        return $this->redirect('panel/menu');
    }

    /** Edita un nodo de la tienda. */
    public function nodeUpdate(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();
        $id = (int) ($params['id'] ?? 0);

        $result = MenuAdmin::update($id, $storeId, false, $this->nodeInput());
        $this->flashResult($result, 'Nodo guardado.');

        return $this->redirect('panel/menu');
    }

    /** Borra un nodo y su rama. */
    public function nodeDelete(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $result = MenuAdmin::delete((int) ($params['id'] ?? 0), $storeId, false);
        $this->flashResult($result, 'Nodo eliminado.');

        return $this->redirect('panel/menu');
    }

    /** Activa o desactiva un nodo. */
    public function nodeToggle(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $result = MenuAdmin::toggle((int) ($params['id'] ?? 0), $storeId, false);
        $this->flashResult($result, 'Nodo actualizado.');

        return $this->redirect('panel/menu');
    }

    /**
     * Guarda el orden del drag & drop.
     *
     * Espera JSON: { parent: 12|null, order: [3, 7, 9] }. Como el arrastrar y
     * soltar no puede depender de un formulario, se responde JSON.
     */
    public function nodeOrder(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $payload = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $parent = $payload['parent'] ?? null;
        $parent = ($parent === null || $parent === '' || (int) $parent === 0) ? null : (int) $parent;
        $order = array_map('intval', (array) ($payload['order'] ?? []));

        if ($order === []) {
            return $this->json(['ok' => false, 'message' => 'No ha llegado ningun nodo que ordenar.']);
        }

        $result = MenuAdmin::reorder($order, $parent, $storeId, false);
        if ($result['ok']) {
            Session::flash('success', $result['message']);
        }

        return $this->json($result);
    }

    /**
     * Publica: el visitante pasa a ver el borrador actual y se invalida la cache
     * (puntos 14 y 15).
     */
    public function publish(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $result = Menu::publish($storeId, $this->userId());
        if ($result['ok']) {
            Session::flash('success', 'Menu publicado (version ' . $result['version'] . '). Ya se ve en la tienda.');
            foreach ($result['warnings'] as $warning) {
                Session::flash('warning', $warning);
            }
        } else {
            Session::flash('error', 'No se ha podido publicar el menu.');
        }

        return $this->redirect('panel/menu');
    }

    /** Vista previa: escritorio (1280) y movil (390) con el borrador actual. */
    public function preview(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        return $this->view('panel/menu_preview', [
            'pageTitle'  => 'Vista previa del menu',
            'styles'     => Menu::styles(),
            'style'      => $this->tenant->menuStyle(),
            'frameUrl'   => $this->url('panel/menu/previa/marco'),
            'backUrl'    => $this->url('panel/menu'),
        ], 'panel');
    }

    /** Marco de la vista previa: solo el menu, con el ancho que le da el iframe. */
    public function previewFrame(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $style = strtolower((string) $this->input('estilo', ''));
        if (!isset(Menu::styles()[$style])) {
            $style = $this->tenant->menuStyle();
        }

        return View::render($this->themeView('menu_preview_frame'), [
            'tenant'    => $this->tenant,
            'menuStyle' => $style,
            'menuTree'  => Menu::draftTree($storeId),
            'menuQuick' => Menu::quickLinks(),
            'styles'    => Menu::styles(),
        ]);
    }

    /**
     * Vuelve a copiar el arbol de referencia (solo nombres y orden) desde el
     * menu de PuntoByZE. Es una accion destructiva para el arbol propio.
     */
    public function seed(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();

        $storeId = $this->storeId();
        $replace = (bool) $this->input('replace');

        $result = Menu::seedFromReference($storeId, $replace);

        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
        $this->redirect('panel/menu');
    }

    // =====================================================================
    // APOYO
    // =====================================================================

    /** Datos del formulario de un nodo (nada de store_id: sale de la sesion). */
    private function nodeInput(): array
    {
        $type = strtolower(trim((string) $this->input('target_type', 'subcategoria')));
        $targetId = $this->input('target_id', 0);
        $targetExtra = $this->input('target_extra', 0);

        // Los filtros del catalogo usan dos ids (filtro y subfiltro) que no
        // caben en el selector de destinos: el formulario los manda aparte.
        if ($type === 'filtro') {
            $targetId = $this->input('filter_id', 0);
            $targetExtra = $this->input('filter_extra', 0);
        }

        return [
            'parent_id'          => $this->input('parent_id'),
            'label'              => $this->input('label', ''),
            'slug'               => $this->input('slug', ''),
            'icon'               => $this->input('icon', ''),
            'target_type'        => $this->input('target_type', 'subcategoria'),
            'target_id'          => $targetId,
            'target_extra'       => $targetExtra,
            'target_key'         => $this->input('target_key', ''),
            'url'                => $this->input('url', ''),
            'badge'              => $this->input('badge', ''),
            'badge_color'        => $this->input('badge_color', ''),
            'banner_id'          => $this->input('banner_id', 0),
            'banner_url'         => $this->input('banner_url', ''),
            'min_customer_level' => $this->input('min_customer_level', ''),
            'hide_empty'         => $this->input('hide_empty') ? 1 : 0,
            'active'             => $this->input('active') ? 1 : 0,
        ];
    }

    private function flashResult(array $result, string $okMessage): void
    {
        if ($result['ok']) {
            Session::flash('success', $result['message'] !== '' ? $result['message'] : $okMessage);
        } else {
            Session::flash('error', $result['message']);
        }
        foreach ((array) ($result['errors'] ?? []) as $error) {
            Session::flash('error', (string) $error);
        }
    }

    private function userId(): ?int
    {
        $user = Auth::user();

        return $user === null ? null : (int) $user['id'];
    }

    private function storeId(): int
    {
        $id = Auth::storeId();
        if ($id === null || $id <= 0) {
            $this->redirect('panel/logout');
        }

        return (int) $id;
    }
}
