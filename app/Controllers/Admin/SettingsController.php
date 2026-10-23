<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Idirecto\Account;
use Tienda\Core\Session;
use Tienda\Core\ShippingZone;
use Tienda\Models\Catalog;
use Tienda\Models\Menu;
use Tienda\Models\Store;
use Tienda\Models\StoreUser;

/**
 * Ajustes de la tienda: datos fiscales/de contacto, cuenta del mayorista y
 * usuarios del panel.
 */
final class SettingsController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $store = Store::findWithPlan($storeId) ?? [];

        // Nivel de cliente del mayorista: se muestra junto a la cuenta, con el
        // motivo cuando la cuenta no lo tiene asignado.
        $nivel = Menu::storeLevelStatus($storeId);

        $shippingZones = ShippingZone::storeConfig($storeId);
        $shippingOld = Session::get('shipping_zone_old', []);
        if (is_array($shippingOld)) {
            foreach ($shippingZones as $zoneCode => $zoneConfig) {
                if (!isset($shippingOld[$zoneCode]) || !is_array($shippingOld[$zoneCode])) {
                    continue;
                }
                foreach (['cost', 'free_from', 'delivery_days_min', 'delivery_days_max', 'conditions'] as $field) {
                    if (array_key_exists($field, $shippingOld[$zoneCode])) {
                        $shippingZones[$zoneCode][$field] = $shippingOld[$zoneCode][$field];
                    }
                }
                if (array_key_exists('enabled', $shippingOld[$zoneCode])) {
                    $shippingZones[$zoneCode]['enabled'] = !empty($shippingOld[$zoneCode]['enabled']) ? 1 : 0;
                }
            }
        }
        $shippingMessage = Session::get('shipping_zone_message_old');
        if (!is_string($shippingMessage)) {
            $shippingMessage = ShippingZone::configuredBlockedMessage($storeId);
        }
        Session::forget('shipping_zone_old');
        Session::forget('shipping_zone_message_old');

        return $this->view('panel/settings', [
            'pageTitle' => 'Ajustes',
            'store'     => $store,
            'users'     => StoreUser::forStore($storeId),
            'account'   => Account::forStore($store),
            'idirectoReady' => Account::enabled(),
            'nivel'     => $nivel,
            'nivelAviso' => $nivel['level'] === null ? Menu::levelWarning($nivel) : '',
            'shippingZones' => $shippingZones,
            'shippingBlockedMessage' => $shippingMessage,
        ], 'panel');
    }

    public function save(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        // Alta de usuario del panel
        if ((string) $this->input('form', '') === 'user') {
            return $this->createUser($storeId);
        }

        // Venta y cobro: beneficio, envio y formas de pago.
        if ((string) $this->input('form', '') === 'shop') {
            return $this->saveShop($storeId);
        }

        if ((string) $this->input('form', '') === 'shipping_zones') {
            return $this->saveShippingZones($storeId);
        }

        // Cuenta del mayorista: si se informa, tiene que existir de verdad.
        $idTienda = (int) $this->input('id_tienda_idirecto', 0);
        if ($idTienda > 0 && Account::findTienda($idTienda) === null) {
            Session::flash('error', 'La cuenta #' . $idTienda . ' no existe en el mayorista.');
            $this->redirect('panel/ajustes');
        }

        Store::updateById($storeId, [
            'name'        => (string) $this->input('name', 'Mi tienda'),
            'legal_name'  => (string) $this->input('legal_name', ''),
            'email'       => (string) $this->input('email', ''),
            'phone'       => (string) $this->input('phone', ''),
            'postal_code' => (string) $this->input('postal_code', ''),
            'city'        => (string) $this->input('city', ''),
            'province'    => (string) $this->input('province', ''),
            'tax_rate'    => (float) str_replace(',', '.', (string) $this->input('tax_rate', '21')),
            'id_tienda_idirecto' => $idTienda > 0 ? $idTienda : null,
            'id_margen'   => max(0, (int) $this->input('id_margen', 0)) ?: null,
        ]);

        Session::flash('success', 'Datos de la tienda actualizados.');
        $this->redirect('panel/ajustes');
    }

    /**
     * Guarda el beneficio, los gastos de envio y las formas de pago.
     *
     * De aqui sale lo que paga el cliente en la web: precio = tarifa de la tienda
     * + este beneficio (+ IVA), y el envio segun la tarifa y el minimo gratis.
     */
    private function saveShop(int $storeId): string
    {
        $markup = (float) str_replace(',', '.', (string) $this->input('markup', '15'));
        $flat = (float) str_replace(',', '.', (string) $this->input('shipping_flat', '0'));
        $freeRaw = trim((string) $this->input('free_shipping_from', ''));
        $free = $freeRaw === '' ? null : (float) str_replace(',', '.', $freeRaw);

        Store::updateById($storeId, [
            'markup'             => max(0, min(200, $markup)),
            'shipping_flat'      => max(0, $flat),
            'free_shipping_from' => $free !== null && $free > 0 ? $free : null,
            'pay_transfer'       => (string) $this->input('pay_transfer', '') === '1' ? 1 : 0,
            'pay_cod'            => (string) $this->input('pay_cod', '') === '1' ? 1 : 0,
            'pay_pickup'         => (string) $this->input('pay_pickup', '') === '1' ? 1 : 0,
            'bank_details'       => trim((string) $this->input('bank_details', '')) ?: null,
        ]);

        // Los precios del catalogo van cacheados por tienda (tarifa + beneficio):
        // al cambiar el beneficio hay que olvidar la cache de precios.
        Catalog::forgetPriceCache();

        Session::flash('success', 'Ajustes de venta y cobro guardados.');
        $this->redirect('panel/ajustes');
    }

    /** Configura las cuatro zonas fijas y el mensaje de rechazo. */
    private function saveShippingZones(int $storeId): string
    {
        $input = $this->input('zones', []);
        if (!is_array($input)) {
            $input = [];
        }

        $errors = [];
        $configs = [];
        $active = 0;

        foreach (ShippingZone::labels() as $zoneCode => $label) {
            $row = isset($input[$zoneCode]) && is_array($input[$zoneCode]) ? $input[$zoneCode] : [];
            $enabled = !empty($row['enabled']) ? 1 : 0;
            $active += $enabled;

            $cost = $this->moneyOrNull($row['cost'] ?? '', $label, 'coste', $errors);
            $free = $this->moneyOrNull($row['free_from'] ?? '', $label, 'envio gratis', $errors);
            $min = $this->daysOrNull($row['delivery_days_min'] ?? '', $label, 'plazo minimo', $errors);
            $max = $this->daysOrNull($row['delivery_days_max'] ?? '', $label, 'plazo maximo', $errors);
            if ($min !== null && $max !== null && $max < $min) {
                $errors[] = $label . ': el plazo maximo no puede ser menor que el minimo.';
            }

            $conditions = trim((string) ($row['conditions'] ?? ''));
            if (mb_strlen($conditions) > 1000) {
                $errors[] = $label . ': las condiciones no pueden superar los 1000 caracteres.';
                $conditions = mb_substr($conditions, 0, 1000);
            }

            $configs[$zoneCode] = [
                'enabled' => $enabled,
                'cost' => $cost,
                'free_from' => $free,
                'delivery_days_min' => $min,
                'delivery_days_max' => $max,
                'conditions' => $conditions !== '' ? $conditions : null,
            ];
        }

        if ($active === 0) {
            $errors[] = 'Activa al menos una zona de venta.';
        }

        $message = trim((string) $this->input('shipping_blocked_message', ''));
        if ($message === '') {
            $errors[] = 'Escribe el mensaje que verá el cliente cuando su zona no esté disponible.';
        } elseif (mb_strlen($message) > 500) {
            $errors[] = 'El mensaje de zona no disponible no puede superar los 500 caracteres.';
            $message = mb_substr($message, 0, 500);
        }

        if ($errors !== []) {
            Session::set('shipping_zone_old', $input);
            Session::set('shipping_zone_message_old', $message);
            Session::flash('error', implode(' ', $errors));
            $this->redirect('panel/ajustes');
        }

        ShippingZone::saveStoreConfig($storeId, $configs, $message);
        Session::flash('success', 'Zonas de venta y configuración de envío guardadas.');
        $this->redirect('panel/ajustes');
    }

    private function moneyOrNull(mixed $value, string $zone, string $field, array &$errors): ?float
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $raw)) {
            $errors[] = $zone . ': el ' . $field . ' debe ser un importe positivo con hasta dos decimales.';
            return null;
        }

        return round((float) $raw, 2);
    }

    private function daysOrNull(mixed $value, string $zone, string $field, array &$errors): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^\d+$/', $raw) || (int) $raw > 65535) {
            $errors[] = $zone . ': el ' . $field . ' debe ser un numero entero de días entre 0 y 65535.';
            return null;
        }

        return (int) $raw;
    }

    private function createUser(int $storeId): string
    {
        $email = strtolower(trim((string) $this->input('email', '')));
        $name = trim((string) $this->input('name', ''));
        $password = (string) $this->input('password', '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            Session::flash('error', 'Revisa los datos: nombre, email valido y contrasena de 8+ caracteres.');
            $this->redirect('panel/ajustes');
        }

        if (StoreUser::findBy('email', $email) !== null) {
            Session::flash('error', 'Ya existe un usuario con ese email.');
            $this->redirect('panel/ajustes');
        }

        StoreUser::create([
            'store_id'      => $storeId,
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => 'editor',
            'active'        => 1,
        ]);

        Session::flash('success', 'Usuario creado correctamente.');
        $this->redirect('panel/ajustes');
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
