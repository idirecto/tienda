<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Idirecto\Account;
use Tienda\Core\Session;
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

        return $this->view('panel/settings', [
            'pageTitle' => 'Ajustes',
            'store'     => $store,
            'users'     => StoreUser::forStore($storeId),
            'account'   => Account::forStore($store),
            'idirectoReady' => Account::enabled(),
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
