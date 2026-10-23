<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Session;
use Tienda\Core\Tenant;
use Tienda\Models\Media;
use Tienda\Models\Notice;
use Tienda\Models\Store;

/**
 * Avisos y promociones de la tienda.
 *
 * Reutiliza `mt_notices`; la migracion 011 solo anadio campos opcionales de
 * presentacion. Las escrituras van siempre acotadas al store_id de la sesion.
 */
final class NoticeController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();
        $store = Store::findWithPlan($storeId) ?? [];
        $tenant = new Tenant($store);
        $notices = Notice::forStore($storeId);
        $used = count($notices);
        $max = max(0, $tenant->maxNotices());

        return $this->view('panel/notices', [
            'pageTitle' => 'Avisos',
            'notices'   => $notices,
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
        $used = count(Notice::forStore($storeId));
        $max = max(0, $tenant->maxNotices());

        $old = $this->consumeFormOld('create');
        $errors = $this->consumeFormErrors('create');

        return $this->formView(
            $this->mergeFormData($this->defaults(), $old),
            false,
            $errors,
            $max > 0 && $used < $max,
            $used,
            $max,
            $tenant
        );
    }

    public function edit(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();
        $id = (int) ($params['id'] ?? 0);
        $notice = Notice::findForStore($id, $storeId);

        if ($notice === null) {
            Session::flash('error', 'Aviso no encontrado en esta tienda.');
            $this->redirect('panel/avisos');
        }

        $store = Store::findWithPlan($storeId) ?? [];
        $tenant = new Tenant($store);
        $used = count(Notice::forStore($storeId));
        $max = max(0, $tenant->maxNotices());

        $old = $this->consumeFormOld('edit:' . $id);
        $errors = $this->consumeFormErrors('edit:' . $id);

        return $this->formView(
            $this->mergeFormData($this->noticeToForm($notice), $old),
            true,
            $errors,
            true,
            $used,
            $max,
            $tenant
        );
    }

    public function store(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf('panel/avisos');
        $storeId = $this->storeId();
        $tenant = new Tenant(Store::findWithPlan($storeId) ?? []);

        $used = count(Notice::forStore($storeId));
        $max = max(0, $tenant->maxNotices());
        if ($max <= 0 || $used >= $max) {
            Session::flash('error', $this->limitMessage($max));
            $this->redirect('panel/avisos');
        }

        [$data, $errors] = $this->validatedData($storeId, null);
        if ($errors !== []) {
            $this->rememberForm($_POST, $errors, 'create');
            Session::flash('error', 'Revisa los datos del aviso.');
            $this->redirect('panel/avisos/nuevo');
        }

        Notice::create($data + ['store_id' => $storeId]);
        $this->clearFormState();
        Session::flash('success', 'Aviso creado.');
        $this->redirect('panel/avisos');
    }

    public function update(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf('panel/avisos');
        $storeId = $this->storeId();
        $id = (int) ($params['id'] ?? 0);
        $existing = Notice::findForStore($id, $storeId);

        if ($existing === null) {
            Session::flash('error', 'Aviso no encontrado en esta tienda.');
            $this->redirect('panel/avisos');
        }

        [$data, $errors] = $this->validatedData($storeId, $existing);
        if ($errors !== []) {
            $this->rememberForm($_POST, $errors, 'edit:' . $id);
            Session::flash('error', 'Revisa los datos del aviso.');
            $this->redirect('panel/avisos/' . $id . '/editar');
        }

        Notice::updateForStore($id, $storeId, $data);
        $this->clearFormState();
        Session::flash('success', 'Aviso actualizado.');
        $this->redirect('panel/avisos');
    }

    public function destroy(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf('panel/avisos');
        $storeId = $this->storeId();
        $id = (int) ($params['id'] ?? 0);

        if (Notice::findForStore($id, $storeId) === null) {
            Session::flash('error', 'Aviso no encontrado en esta tienda.');
            $this->redirect('panel/avisos');
        }

        Notice::deleteForStore($id, $storeId);
        // La imagen de mt_media se conserva: puede estar reutilizada.
        Session::flash('success', 'Aviso eliminado. La imagen se conserva en la biblioteca de medios.');
        $this->redirect('panel/avisos');
    }

    /** Previsualizacion con el mismo partial y CSS que la tienda. */
    public function preview(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf('panel/avisos');
        $storeId = $this->storeId();

        $existing = null;
        $existingId = (int) $this->input('notice_id', 0);
        if ($existingId > 0) {
            $existing = Notice::findForStore($existingId, $storeId);
        }

        [$data] = $this->validatedData($storeId, $existing);
        $notice = array_merge($data, [
            'id'               => $existingId,
            'store_id'         => $storeId,
            'effective_status' => 'active',
        ]);

        $store = Store::findWithPlan($storeId) ?? [];
        $tenant = new Tenant($store);

        header('Content-Type: text/html; charset=utf-8');

        return $this->view('panel/notice_preview', [
            'notice' => $notice,
            'tenant' => $tenant,
        ]);
    }

    private function formView(
        array $notice,
        bool $isEdit,
        array $errors,
        bool $canCreate,
        int $used,
        int $max,
        Tenant $tenant
    ): string {
        return $this->view('panel/notice_form', [
            'pageTitle' => $isEdit ? 'Editar aviso' : 'Nuevo aviso',
            'notice'    => $notice,
            'isEdit'    => $isEdit,
            'errors'    => $errors,
            'canCreate' => $canCreate,
            'used'      => $used,
            'max'       => $max,
            'planName'  => $tenant->planName(),
            'types'     => Notice::typeOptions(),
            'variants'  => Notice::variantOptions(),
            'textSizes' => Notice::textSizeOptions(),
            'icons'     => Notice::iconOptions(),
        ], 'panel');
    }

    /** @return array{0:array<string,mixed>,1:array<string>} */
    private function validatedData(int $storeId, ?array $existing): array
    {
        $errors = [];

        $message = trim((string) $this->input('message', ''));
        if ($message === '') {
            $errors[] = 'El mensaje del aviso es obligatorio.';
        }
        if (mb_strlen($message) > 500) {
            $errors[] = 'El mensaje no puede superar los 500 caracteres.';
            $message = mb_substr($message, 0, 500);
        }

        $type = strtolower(trim((string) $this->input('type', 'info')));
        if (!array_key_exists($type, Notice::typeOptions())) {
            $errors[] = 'El tipo de aviso no es valido.';
            $type = 'info';
        }

        $variant = strtolower(trim((string) $this->input('variant', 'soft')));
        if (!array_key_exists($variant, Notice::variantOptions())) {
            $errors[] = 'La variante visual no es valida.';
            $variant = 'soft';
        }

        $textSize = strtolower(trim((string) $this->input('text_size', 'md')));
        if (!array_key_exists($textSize, Notice::textSizeOptions())) {
            $errors[] = 'El tamano de texto no es valido.';
            $textSize = 'md';
        }

        $badge = trim((string) $this->input('badge_text', ''));
        if (mb_strlen($badge) > 40) {
            $errors[] = 'El badge no puede superar los 40 caracteres.';
            $badge = mb_substr($badge, 0, 40);
        }

        $icon = strtolower(trim((string) $this->input('icon', '')));
        if ($icon !== '' && !array_key_exists($icon, Notice::iconOptions())) {
            $errors[] = 'El icono elegido no esta disponible.';
            $icon = '';
        }

        $startsAt = $this->dateInput((string) $this->input('starts_at', ''), 'inicio', $errors);
        $endsAt = $this->dateInput((string) $this->input('ends_at', ''), 'fin', $errors);
        if ($startsAt !== null && $endsAt !== null && strtotime($endsAt) <= strtotime($startsAt)) {
            $errors[] = 'La fecha de fin debe ser posterior a la fecha de inicio.';
        }

        [$mediaId, $imageUrl, $imageErrors] = $this->resolveImage($storeId, $existing);
        array_push($errors, ...$imageErrors);

        return [[
            'type'      => $type,
            'variant'   => $variant,
            'text_size' => $textSize,
            'message'   => $message,
            'badge_text' => $badge !== '' ? $badge : null,
            'icon'      => $icon !== '' ? $icon : null,
            'media_id'  => $mediaId,
            'image_url' => $imageUrl,
            'visible'   => $this->input('visible') ? 1 : 0,
            'starts_at' => $startsAt,
            'ends_at'   => $endsAt,
        ], $errors];
    }

    /**
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
        // corresponde a un media de ESTA tienda, se recupera y se asocia.
        $mediaByUrl = Media::findByUrlForStore($imageUrl, $storeId);
        if ($mediaByUrl !== null) {
            if (!str_starts_with(strtolower((string) ($mediaByUrl['mime'] ?? '')), 'image/')) {
                return [null, null, ['El archivo seleccionado no es una imagen valida.']];
            }
            return [(int) $mediaByUrl['id'], (string) $mediaByUrl['url'], []];
        }

        // Avisos antiguos guardados solo con URL: se conserva el valor actual,
        // pero no se permite cambiarlo por una URL arbitraria.
        if ($existing !== null && $currentUrl !== '' && hash_equals($currentUrl, $imageUrl)) {
            return [$currentMediaId > 0 ? $currentMediaId : null, $currentUrl, []];
        }

        $errors[] = 'La imagen debe subirse desde el formulario y pertenecer a esta tienda.';

        return [null, null, $errors];
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
            'id'         => null,
            'type'       => 'info',
            'variant'    => 'soft',
            'text_size'  => 'md',
            'message'    => '',
            'badge_text' => '',
            'icon'       => '',
            'media_id'   => null,
            'image_url'  => null,
            'visible'    => 1,
            'starts_at'  => null,
            'ends_at'    => null,
        ];
    }

    private function noticeToForm(array $notice): array
    {
        return [
            'id'         => (int) $notice['id'],
            'type'       => (string) ($notice['type'] ?? 'info'),
            'variant'    => (string) ($notice['variant'] ?? 'soft'),
            'text_size'  => (string) ($notice['text_size'] ?? 'md'),
            'message'    => (string) ($notice['message'] ?? ''),
            'badge_text' => (string) ($notice['badge_text'] ?? ''),
            'icon'       => (string) ($notice['icon'] ?? ''),
            'media_id'   => $notice['media_id'] !== null ? (int) $notice['media_id'] : null,
            'image_url'  => $notice['image_url'] ?? null,
            'visible'    => (int) ($notice['visible'] ?? 1),
            'starts_at'  => $notice['starts_at'] ?? null,
            'ends_at'    => $notice['ends_at'] ?? null,
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
            'type', 'variant', 'text_size', 'message', 'badge_text', 'icon',
            'media_id', 'image_url', 'visible', 'starts_at', 'ends_at',
        ] as $key) {
            $safe[$key] = is_scalar($input[$key] ?? null) ? (string) $input[$key] : '';
        }

        Session::set('notice_form_old', ['_scope' => $scope, 'fields' => $safe]);
        Session::set('notice_form_errors', ['_scope' => $scope, 'items' => $errors]);
    }

    private function consumeFormOld(string $scope): array
    {
        $stored = Session::get('notice_form_old', []);
        Session::forget('notice_form_old');

        if (!is_array($stored)
            || (string) ($stored['_scope'] ?? '') !== $scope
            || !is_array($stored['fields'] ?? null)) {
            return [];
        }

        return $stored['fields'];
    }

    private function consumeFormErrors(string $scope): array
    {
        $stored = Session::get('notice_form_errors', []);
        Session::forget('notice_form_errors');

        if (!is_array($stored)
            || (string) ($stored['_scope'] ?? '') !== $scope
            || !is_array($stored['items'] ?? null)) {
            return [];
        }

        return $stored['items'];
    }

    private function clearFormState(): void
    {
        Session::forget('notice_form_old');
        Session::forget('notice_form_errors');
    }

    private function limitMessage(int $max): string
    {
        if ($max <= 0) {
            return 'Tu plan actual no permite avisos.';
        }

        return 'Has alcanzado el limite de avisos de tu plan (' . $max . '). '
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
