<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Session;
use Tienda\Core\View;
use Tienda\Models\Menu;
use Tienda\Models\MenuAdmin;

/**
 * Panel de plataforma del menu (solo rol `platform`).
 *
 * Aqui se administra el arbol COMPARTIDO (nodos con `store_id` NULL) y se
 * decide que ve cada tienda (punto 10) y cada nivel de cliente (punto 11).
 *
 * Aunque este usuario puede tocar todas las tiendas, el aislamiento se mantiene
 * en el modelo: cada operacion pasa por `MenuAdmin` con el alcance explícito y
 * la tienda destino se valida contra `mt_stores`.
 */
final class PlatformMenuController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requirePlatform();

        $storeId = (int) ($this->input('store', 0));
        $stores = MenuAdmin::storeOptions();
        if ($storeId <= 0 || !$this->storeExists($storeId, $stores)) {
            $storeId = (int) ($stores[0]['id'] ?? 0);
        }

        $storeLevel = $storeId > 0 ? Menu::storeLevelStatus($storeId) : null;

        return $this->view('panel/menu_plataforma', [
            'pageTitle'   => 'Menu de la plataforma',
            'stores'      => $stores,
            'storeId'     => $storeId,
            'storeName'   => $this->storeName($storeId, $stores),
            'levels'      => Menu::customerLevels(),
            'storeLevel'  => $storeLevel['level'] ?? null,
            'storeLevelStatus'  => $storeLevel,
            'storeLevelWarning' => $storeLevel !== null && $storeLevel['level'] === null
                ? Menu::levelWarning($storeLevel) : '',
            'visibility'  => Menu::visibilityModes(),
            'styles'      => Menu::styles(),
            'style'       => $this->tenant->menuStyle(),
            'stats'       => Menu::stats(0, true),
            'storeStats'  => $storeId > 0 ? Menu::stats($storeId) : null,
            'tree'        => MenuAdmin::tree(0, true),
            'draft'       => $storeId > 0 ? Menu::hasDraftChanges($storeId) : false,
            'warnings'    => $storeId > 0 ? Menu::review($storeId) : [],
            'pendientes'  => Menu::pending(0, 60, true),
            'destinos'    => MenuAdmin::destinations(),
            'icons'       => Menu::iconOptions(),
            'badges'      => Menu::badgePresets(),
            'banners'     => $storeId > 0 ? MenuAdmin::bannerOptions($storeId) : [],
            'scope'       => 'platform',
            'actionBase'  => $this->url('panel/plataforma/menu'),
            'isPlatform'  => true,
        ], 'panel');
    }

    /** Crea un nodo: compartido (owner 0) o de una tienda concreta. */
    public function nodeCreate(array $params = []): string
    {
        $this->requirePlatform();
        $this->requireCsrf();

        $input = $this->nodeInput();
        $input['owner'] = (int) $this->input('owner', 0);

        $result = MenuAdmin::create(0, true, $input);
        if ($result['ok'] && $result['id'] !== null) {
            $this->saveVisibilityList((int) $result['id']);
        }
        $this->flashResult($result);

        return $this->back();
    }

    /** Edita un nodo del arbol global. */
    public function nodeUpdate(array $params = []): string
    {
        $this->requirePlatform();
        $this->requireCsrf();

        $id = (int) ($params['id'] ?? 0);
        $input = $this->nodeInput();
        $input['owner'] = (int) $this->input('owner', 0);

        $result = MenuAdmin::update($id, 0, true, $input);
        if ($result['ok']) {
            $this->saveVisibilityList($id);
        }
        $this->flashResult($result);

        return $this->back();
    }

    /**
     * Guarda la lista de tiendas del nodo (la que reparte el menu, punto 10).
     * Si el formulario no traia visibilidad, no se toca nada.
     */
    private function saveVisibilityList(int $itemId): void
    {
        $mode = (string) $this->input('visibility', '');
        $stores = array_map('intval', (array) $this->input('visibility_stores', []));
        if ($mode === '' && $stores === []) {
            return;
        }

        $result = MenuAdmin::saveVisibility($itemId, $mode !== '' ? $mode : 'todas', $stores, 0, true);
        if (!$result['ok']) {
            Session::flash('error', $result['message']);
        }
    }

    public function nodeDelete(array $params = []): string
    {
        $this->requirePlatform();
        $this->requireCsrf();

        $result = MenuAdmin::delete((int) ($params['id'] ?? 0), 0, true);
        $this->flashResult($result);

        return $this->back();
    }

    public function nodeToggle(array $params = []): string
    {
        $this->requirePlatform();
        $this->requireCsrf();

        $result = MenuAdmin::toggle((int) ($params['id'] ?? 0), 0, true);
        $this->flashResult($result);

        return $this->back();
    }

    /** Orden del drag & drop (JSON). */
    public function nodeOrder(array $params = []): string
    {
        $this->requirePlatform();
        $this->requireCsrf();

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

        return $this->json(MenuAdmin::reorder($order, $parent, 0, true));
    }

    /** Que tiendas ven este nodo (punto 10). */
    public function visibility(array $params = []): string
    {
        $this->requirePlatform();
        $this->requireCsrf();

        $id = (int) ($params['id'] ?? 0);
        $mode = (string) $this->input('visibility', 'todas');
        $stores = array_map('intval', (array) $this->input('visibility_stores', []));

        $result = MenuAdmin::saveVisibility($id, $mode, $stores, 0, true);
        $this->flashResult($result);

        return $this->back();
    }

    /** Publica el menu de UNA tienda con el arbol global actual. */
    public function publishStore(array $params = []): string
    {
        $this->requirePlatform();
        $this->requireCsrf();

        $storeId = (int) ($params['store'] ?? 0);
        if ($storeId <= 0 || !$this->storeExists($storeId, MenuAdmin::storeOptions())) {
            Session::flash('error', 'Esa tienda no existe.');
            return $this->back();
        }

        $result = Menu::publish($storeId, $this->userId());
        if ($result['ok']) {
            Session::flash('success', 'Menu publicado para esa tienda (version ' . $result['version'] . ').');
            foreach ($result['warnings'] as $warning) {
                Session::flash('warning', $warning);
            }
        }

        return $this->back();
    }

    /** Pasa al modelo compartido: todo el arbol de esa tienda deja de ser suyo. */
    public function shareStore(array $params = []): string
    {
        $this->requirePlatform();
        $this->requireCsrf();

        $storeId = (int) ($params['store'] ?? 0);
        $result = MenuAdmin::shareStoreTree($storeId, true);
        $this->flashResult($result);

        return $this->back();
    }

    /** Publica el menu de TODAS las tiendas de una vez. */
    public function publishAll(array $params = []): string
    {
        $this->requirePlatform();
        $this->requireCsrf();

        $ok = 0;
        $warnings = [];
        foreach (MenuAdmin::storeOptions() as $store) {
            $result = Menu::publish((int) $store['id'], $this->userId());
            if ($result['ok']) {
                $ok++;
                $warnings = array_merge($warnings, $result['warnings']);
            }
        }

        Session::flash('success', 'Menu publicado en ' . $ok . ' tienda(s).');
        foreach (array_slice(array_unique($warnings), 0, 3) as $warning) {
            Session::flash('warning', $warning);
        }

        return $this->back();
    }

    /** Vista previa del menu global aplicado a la tienda elegida. */
    public function preview(array $params = []): string
    {
        $this->requirePlatform();

        $storeId = (int) ($params['store'] ?? 0);
        $stores = MenuAdmin::storeOptions();
        if ($storeId <= 0 || !$this->storeExists($storeId, $stores)) {
            $storeId = (int) ($stores[0]['id'] ?? 0);
        }

        return $this->view('panel/menu_preview', [
            'pageTitle'  => 'Vista previa del menu',
            'styles'     => Menu::styles(),
            'style'      => $this->tenant->menuStyle(),
            'frameUrl'   => $this->url('panel/plataforma/menu/' . $storeId . '/previa/marco'),
            'backUrl'    => $this->url('panel/plataforma/menu?store=' . $storeId),
        ], 'panel');
    }

    /** Marco de la vista previa (borrador del arbol global para esa tienda). */
    public function previewFrame(array $params = []): string
    {
        $this->requirePlatform();

        $storeId = (int) ($params['store'] ?? 0);
        if ($storeId <= 0 || !$this->storeExists($storeId, MenuAdmin::storeOptions())) {
            $storeId = (int) (MenuAdmin::storeOptions()[0]['id'] ?? 0);
        }

        $style = strtolower((string) $this->input('estilo', ''));
        if (!isset(Menu::styles()[$style])) {
            $style = $this->tenant->menuStyle();
        }

        return View::render($this->themeView('menu_preview_frame'), [
            'tenant'    => $this->tenant,
            'menuStyle' => $style,
            'menuTree'  => $storeId > 0 ? Menu::draftTree($storeId) : ['categories' => [], 'groups' => 0, 'links' => 0],
            'menuQuick' => Menu::quickLinks(),
            'styles'    => Menu::styles(),
        ]);
    }

    // =====================================================================
    // APOYO
    // =====================================================================

    private function requirePlatform(): void
    {
        $this->requireAuth();
        if (!Auth::isPlatform()) {
            http_response_code(403);
            Session::flash('error', 'Solo el administrador de la plataforma puede entrar aqui.');
            $this->redirect('panel');
        }
    }

    private function nodeInput(): array
    {
        $type = strtolower(trim((string) $this->input('target_type', 'subcategoria')));
        $targetId = $this->input('target_id', 0);
        $targetExtra = $this->input('target_extra', 0);

        // Los filtros del catalogo usan dos ids (filtro y subfiltro).
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
            'visibility'         => $this->input('visibility', 'todas'),
        ];
    }

    private function flashResult(array $result): void
    {
        Session::flash($result['ok'] ? 'success' : 'error', (string) $result['message']);
        foreach ((array) ($result['errors'] ?? []) as $error) {
            Session::flash('error', (string) $error);
        }
    }

    private function storeExists(int $storeId, array $stores): bool
    {
        foreach ($stores as $store) {
            if ((int) $store['id'] === $storeId) {
                return true;
            }
        }

        return false;
    }

    private function storeName(int $storeId, array $stores): string
    {
        foreach ($stores as $store) {
            if ((int) $store['id'] === $storeId) {
                return (string) $store['label'];
            }
        }

        return '';
    }

    private function userId(): ?int
    {
        $user = Auth::user();

        return $user === null ? null : (int) $user['id'];
    }

    /** Vuelve a la pantalla de la que se vino (mantiene la tienda elegida). */
    private function back(): never
    {
        $storeId = (int) $this->input('store', 0);

        $this->redirect('panel/plataforma/menu' . ($storeId > 0 ? '?store=' . $storeId : ''));
    }
}
