<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Session;
use Tienda\Core\Tenant;
use Tienda\Models\Banner;
use Tienda\Models\Media;
use Tienda\Models\Store;

/**
 * Banners de la tienda.
 *
 * Toda escritura usa el store_id de la sesion y comprueba la propiedad del
 * registro. Los ficheros subidos NO se borran al eliminar o sustituir un banner:
 * el media puede seguir referenciado por el menu u otros contenidos.
 */
final class BannerController extends Controller
{
    private const MAX_OVERLAY = 70;
    private const DEFAULT_OVERLAY = 35;

    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();
        $store = Store::findWithPlan($storeId) ?? [];
        $tenant = new Tenant($store);

        $banners = Banner::forStore($storeId);
        $used = count($banners);
        $max = max(0, $tenant->maxBanners());

        return $this->view('panel/banners', [
            'pageTitle' => 'Banners',
            'banners'   => $banners,
            'used'      => $used,
            'max'       => $max,
            'canCreate' => $max > 0 && $used < $max,
            'planName'  => $tenant->planName(),
        ], 'panel');
    }

    public function create(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();
        $store = Store::findWithPlan($storeId) ?? [];
        $tenant = new Tenant($store);
        $used = count(Banner::forStore($storeId));
        $max = max(0, $tenant->maxBanners());

        $old = $this->consumeFormOld('create');
        $errors = $this->consumeFormErrors('create');

        return $this->formView(
            $this->mergeFormData($this->defaults(), $old),
            false,
            $errors,
            $max > 0 && $used < $max,
            $used,
            $max,
            $tenant,
            ''
        );
    }

    public function edit(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();
        $id = (int) ($params['id'] ?? 0);
        $banner = Banner::findForStore($id, $storeId);

        if ($banner === null) {
            Session::flash('error', 'Banner no encontrado en esta tienda.');
            $this->redirect('panel/banners');
        }

        $store = Store::findWithPlan($storeId) ?? [];
        $tenant = new Tenant($store);
        $used = count(Banner::forStore($storeId));
        $max = max(0, $tenant->maxBanners());

        $old = $this->consumeFormOld('edit:' . $id);
        $errors = $this->consumeFormErrors('edit:' . $id);

        return $this->formView(
            $this->mergeFormData($this->bannerToForm($banner), $old),
            true,
            $errors,
            true,
            $used,
            $max,
            $tenant,
            (string) ($banner['position'] ?? '')
        );
    }

    public function store(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf('panel/banners');
        $storeId = $this->storeId();
        $tenant = new Tenant(Store::findWithPlan($storeId) ?? []);

        $used = count(Banner::forStore($storeId));
        $max = max(0, $tenant->maxBanners());
        if ($max <= 0 || $used >= $max) {
            Session::flash('error', $this->limitMessage($max));
            $this->redirect('panel/banners');
        }

        [$data, $errors] = $this->validatedData($storeId, null);
        if ($errors !== []) {
            $this->rememberForm($_POST, $errors, 'create');
            Session::flash('error', 'Revisa los datos del banner.');
            $this->redirect('panel/banners/nuevo');
        }

        Banner::create($data + ['store_id' => $storeId]);
        $this->clearFormState();
        Session::flash('success', 'Banner creado.');
        $this->redirect('panel/banners');
    }

    public function update(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf('panel/banners');
        $storeId = $this->storeId();
        $id = (int) ($params['id'] ?? 0);
        $existing = Banner::findForStore($id, $storeId);

        if ($existing === null) {
            Session::flash('error', 'Banner no encontrado en esta tienda.');
            $this->redirect('panel/banners');
        }

        [$data, $errors] = $this->validatedData($storeId, $existing);
        if ($errors !== []) {
            $this->rememberForm($_POST, $errors, 'edit:' . $id);
            Session::flash('error', 'Revisa los datos del banner.');
            $this->redirect('panel/banners/' . $id . '/editar');
        }

        Banner::updateForStore($id, $storeId, $data);
        $this->clearFormState();
        Session::flash('success', 'Banner actualizado.');
        $this->redirect('panel/banners');
    }

    public function destroy(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf('panel/banners');
        $storeId = $this->storeId();
        $id = (int) ($params['id'] ?? 0);

        if (Banner::findForStore($id, $storeId) === null) {
            Session::flash('error', 'Banner no encontrado en esta tienda.');
            $this->redirect('panel/banners');
        }

        Banner::deleteForStore($id, $storeId);
        // No se borra el fichero subido ni su fila de mt_media.
        Session::flash('success', 'Banner eliminado. La imagen se conserva en la biblioteca de medios.');
        $this->redirect('panel/banners');
    }

    /**
     * Previsualizacion sin guardar. Recibe exactamente los campos del formulario
     * y devuelve el mismo HTML del hero que usa la tienda.
     */
    public function preview(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf('panel/banners');
        $storeId = $this->storeId();

        $existing = null;
        $existingId = (int) $this->input('banner_id', 0);
        if ($existingId > 0) {
            $existing = Banner::findForStore($existingId, $storeId);
        }

        [$data] = $this->validatedData($storeId, $existing);
        $banner = array_merge($data, [
            'id'               => $existingId,
            'store_id'         => $storeId,
            'effective_status' => 'active',
        ]);

        $store = Store::findWithPlan($storeId) ?? [];
        $tenant = new Tenant($store);

        header('Content-Type: text/html; charset=utf-8');

        return $this->view('panel/banner_preview', [
            'banner' => $banner,
            'tenant' => $tenant,
        ]);
    }

    private function formView(
        array $banner,
        bool $isEdit,
        array $errors,
        bool $canCreate,
        int $used,
        int $max,
        Tenant $tenant,
        string $originalPosition
    ): string {
        return $this->view('panel/banner_form', [
            'pageTitle' => $isEdit ? 'Editar banner' : 'Nuevo banner',
            'banner'    => $banner,
            'isEdit'    => $isEdit,
            'errors'    => $errors,
            'canCreate' => $canCreate,
            'used'      => $used,
            'max'       => $max,
            'planName'  => $tenant->planName(),
            'statuses'  => Banner::statusOptions(),
            'positions' => Banner::positionOptions(),
            'overlayDefault' => self::DEFAULT_OVERLAY,
            'overlayMax' => self::MAX_OVERLAY,
            'legacyPosition' => $originalPosition !== ''
                && !Banner::isRenderedPosition($originalPosition),
        ], 'panel');
    }

    /**
     * Valida y normaliza todos los campos.
     *
     * @return array{0:array<string,mixed>,1:array<string>}
     */
    private function validatedData(int $storeId, ?array $existing): array
    {
        $errors = [];

        $name = trim((string) $this->input('name', ''));
        $title = trim((string) $this->input('title', ''));
        $subtitle = trim((string) $this->input('subtitle', ''));
        $altText = trim((string) $this->input('alt_text', ''));
        $linkRaw = trim((string) $this->input('link', ''));

        if (mb_strlen($name) > 120) {
            $errors[] = 'El nombre interno no puede superar los 120 caracteres.';
            $name = mb_substr($name, 0, 120);
        }
        if (mb_strlen($title) > 150) {
            $errors[] = 'El titulo no puede superar los 150 caracteres.';
            $title = mb_substr($title, 0, 150);
        }
        if (mb_strlen($subtitle) > 255) {
            $errors[] = 'El subtitulo no puede superar los 255 caracteres.';
            $subtitle = mb_substr($subtitle, 0, 255);
        }
        if (mb_strlen($altText) > 255) {
            $errors[] = 'El texto alternativo no puede superar los 255 caracteres.';
            $altText = mb_substr($altText, 0, 255);
        }
        if (mb_strlen($linkRaw) > 500) {
            $errors[] = 'El enlace no puede superar los 500 caracteres.';
            $linkRaw = mb_substr($linkRaw, 0, 500);
        }

        $link = $this->validLink($linkRaw, $errors);

        $position = strtolower(trim((string) $this->input('position', 'hero')));
        if (!array_key_exists($position, Banner::positionOptions())) {
            $errors[] = 'La posicion elegida no se muestra en esta version de la tienda.';
            $position = 'hero';
        }
        $legacyPosition = $existing !== null
            && !Banner::isRenderedPosition((string) ($existing['position'] ?? ''));
        if ($legacyPosition
            && $position === 'hero'
            && (string) $this->input('confirm_move_position', '') !== '1') {
            $errors[] = 'Confirma que quieres mover este banner antiguo a Portada antes de guardarlo.';
        }

        $sortRaw = trim((string) $this->input('sort', '0'));
        $sort = filter_var($sortRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => -32768, 'max_range' => 32767]]);
        if ($sort === false) {
            $errors[] = 'El orden debe ser un numero entero entre -32768 y 32767.';
            $sort = (int) ($existing['sort'] ?? 0);
        }

        $statusRaw = strtolower(trim((string) $this->input('status', '')));
        if ($statusRaw === '') {
            // Compatibilidad con el formulario anterior: solo enviaba `active`.
            $statusRaw = $this->input('active') ? 'active' : 'inactive';
        }
        if (!array_key_exists($statusRaw, Banner::statusOptions())) {
            $errors[] = 'El estado elegido no es valido.';
            $statusRaw = 'inactive';
        }

        $startsAt = $this->dateInput((string) $this->input('starts_at', ''), 'inicio', $errors);
        $endsAt = $this->dateInput((string) $this->input('ends_at', ''), 'fin', $errors);
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
            $errors[] = 'La fecha de fin debe ser posterior a la fecha de inicio.';
        }
        if ($statusRaw === 'scheduled' && $startsAt === null) {
            $errors[] = 'Un banner programado necesita una fecha de inicio.';
        }

        $overlayRaw = trim((string) $this->input('overlay_opacity', (string) self::DEFAULT_OVERLAY));
        $overlay = filter_var($overlayRaw, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => self::MAX_OVERLAY],
        ]);
        if ($overlay === false) {
            $errors[] = 'El velo de la imagen debe estar entre 0 y ' . self::MAX_OVERLAY . '.';
            $overlay = self::DEFAULT_OVERLAY;
        }

        [$mediaId, $imageUrl, $imageErrors] = $this->resolveImage($storeId, $existing);
        array_push($errors, ...$imageErrors);

        return [[
            'name'             => $name !== '' ? $name : null,
            'title'            => $title !== '' ? $title : null,
            'subtitle'         => $subtitle !== '' ? $subtitle : null,
            'alt_text'         => $altText !== '' ? $altText : null,
            'link'             => $link,
            'media_id'         => $mediaId,
            'image_url'        => $imageUrl,
            'position'         => $position,
            'sort'             => (int) $sort,
            'active'           => in_array($statusRaw, ['active', 'scheduled'], true) ? 1 : 0,
            'status'           => $statusRaw,
            'starts_at'        => $startsAt,
            'ends_at'          => $endsAt,
            'overlay_opacity'  => (int) $overlay,
        ], $errors];
    }

    /**
     * Resuelve la imagen desde mt_media, siempre acotada a la tienda.
     * Una URL libre solo se conserva si es exactamente la que ya tenia el banner.
     *
     * @return array{0:?int,1:?string,2:array<string>}
     */
    private function resolveImage(int $storeId, ?array $existing): array
    {
        $errors = [];
        $mediaId = max(0, (int) $this->input('media_id', 0));
        $imageUrl = trim((string) $this->input('image_url', ''));
        $currentUrl = trim((string) ($existing['image_url'] ?? ''));
        $currentMediaId = (int) ($existing['media_id'] ?? 0);

        if ($mediaId > 0) {
            $media = Media::findForStore($mediaId, $storeId);
            if ($media === null) {
                return [null, null, ['La imagen seleccionada no pertenece a esta tienda.']];
            }
            if (!str_starts_with(strtolower((string) ($media['mime'] ?? '')), 'image/')) {
                return [null, null, ['El archivo seleccionado no es una imagen valida.']];
            }

            return [$mediaId, (string) $media['url'], []];
        }

        if ($imageUrl === '') {
            return [null, null, []];
        }

        // Compatibilidad con formularios antiguos que solo enviaban la URL. Si
        // la URL corresponde a un media de ESTA tienda, se recupera y asocia.
        $mediaByUrl = Media::findByUrlForStore($imageUrl, $storeId);
        if ($mediaByUrl !== null) {
            if (!str_starts_with(strtolower((string) ($mediaByUrl['mime'] ?? '')), 'image/')) {
                return [null, null, ['El archivo seleccionado no es una imagen valida.']];
            }
            return [(int) $mediaByUrl['id'], (string) $mediaByUrl['url'], []];
        }

        // Banners/avisos antiguos guardados solo con URL: se conserva su valor
        // actual, pero no se permite cambiarlo por una URL arbitraria.
        if ($existing !== null && $currentUrl !== '' && hash_equals($currentUrl, $imageUrl)) {
            return [$currentMediaId > 0 ? $currentMediaId : null, $currentUrl, []];
        }

        $errors[] = 'La imagen debe subirse desde el formulario y pertenecer a esta tienda.';

        return [null, null, $errors];
    }

    /** Valida enlaces internos y HTTP(S); rechaza esquemas peligrosos. */
    private function validLink(string $link, array &$errors): ?string
    {
        if ($link === '') {
            return null;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $link) === 1 || str_contains($link, '\\')) {
            $errors[] = 'El enlace contiene caracteres no permitidos.';
            return null;
        }

        if (str_starts_with($link, '/')) {
            if (str_starts_with($link, '//')) {
                $errors[] = 'El enlace debe ser una ruta interna o una URL http/https.';
                return null;
            }
            return $link;
        }

        $parts = parse_url($link);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)
            || empty($parts['host'])
            || filter_var($link, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'El enlace debe empezar por / o ser una URL valida http/https.';
            return null;
        }

        return $link;
    }

    private function dateInput(string $raw, string $label, array &$errors): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            $errors[] = 'La fecha de ' . $label . ' no es valida.';
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function defaults(): array
    {
        return [
            'id'              => null,
            'name'            => '',
            'title'           => '',
            'subtitle'        => '',
            'alt_text'        => '',
            'link'            => '',
            'media_id'        => null,
            'image_url'       => null,
            'position'        => 'hero',
            'sort'            => 0,
            'status'          => 'active',
            'starts_at'       => null,
            'ends_at'         => null,
            'overlay_opacity' => self::DEFAULT_OVERLAY,
        ];
    }

    private function bannerToForm(array $banner): array
    {
        $stored = strtolower(trim((string) ($banner['status'] ?? '')));
        if (!array_key_exists($stored, Banner::statusOptions())) {
            $stored = (int) ($banner['active'] ?? 0) === 1 ? 'active' : 'inactive';
        }

        return [
            'id'              => (int) $banner['id'],
            'name'            => (string) ($banner['name'] ?? ''),
            'title'           => (string) ($banner['title'] ?? ''),
            'subtitle'        => (string) ($banner['subtitle'] ?? ''),
            'alt_text'        => (string) ($banner['alt_text'] ?? ''),
            'link'            => (string) ($banner['link'] ?? ''),
            'media_id'        => $banner['media_id'] !== null ? (int) $banner['media_id'] : null,
            'image_url'       => $banner['image_url'] ?? null,
            'position'        => (string) ($banner['position'] ?? 'hero'),
            'sort'            => (int) ($banner['sort'] ?? 0),
            'status'          => $stored,
            'starts_at'       => $banner['starts_at'] ?? null,
            'ends_at'         => $banner['ends_at'] ?? null,
            'overlay_opacity' => Banner::overlayOpacity($banner),
        ];
    }

    private function mergeFormData(array $base, array $old): array
    {
        if ($old === []) {
            return $base;
        }

        foreach ($base as $key => $value) {
            if (array_key_exists($key, $old)) {
                $base[$key] = is_scalar($old[$key]) || $old[$key] === null ? $old[$key] : $value;
            }
        }

        return $base;
    }

    private function rememberForm(array $input, array $errors, string $scope): void
    {
        $safe = [];
        foreach ([
            'name', 'title', 'subtitle', 'alt_text', 'link', 'media_id',
            'image_url', 'position', 'sort', 'status', 'starts_at', 'ends_at',
            'overlay_opacity',
        ] as $key) {
            $safe[$key] = is_scalar($input[$key] ?? null) ? (string) $input[$key] : '';
        }

        Session::set('banner_form_old', ['_scope' => $scope, 'fields' => $safe]);
        Session::set('banner_form_errors', ['_scope' => $scope, 'items' => $errors]);
    }

    private function consumeFormOld(string $scope): array
    {
        $stored = Session::get('banner_form_old', []);
        Session::forget('banner_form_old');

        if (!is_array($stored)
            || (string) ($stored['_scope'] ?? '') !== $scope
            || !is_array($stored['fields'] ?? null)) {
            return [];
        }

        return $stored['fields'];
    }

    private function consumeFormErrors(string $scope): array
    {
        $stored = Session::get('banner_form_errors', []);
        Session::forget('banner_form_errors');

        if (!is_array($stored)
            || (string) ($stored['_scope'] ?? '') !== $scope
            || !is_array($stored['items'] ?? null)) {
            return [];
        }

        return $stored['items'];
    }

    private function clearFormState(): void
    {
        Session::forget('banner_form_old');
        Session::forget('banner_form_errors');
    }

    private function limitMessage(int $max): string
    {
        if ($max <= 0) {
            return 'Tu plan actual no permite banners.';
        }

        return 'Has alcanzado el limite de banners de tu plan (' . $max . '). '
            . 'Puedes editar o eliminar uno existente, pero no crear otro con este plan.';
    }

    private function storeId(): int
    {
        $id = Auth::storeId();
        if ($id === null || $id <= 0) {
            $this->redirect('panel/logout');
        }

        return $id;
    }
}
