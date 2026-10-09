<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use Tienda\Core\Cart;
use Tienda\Core\Controller;
use Tienda\Core\CustomerAuth;
use Tienda\Core\Logger;
use Tienda\Core\Session;
use Tienda\Models\Customer;
use Tienda\Models\CustomerAddress;
use Tienda\Models\Order;

/**
 * Cuenta del CLIENTE de la tienda: registro, entrada, sus pedidos y su libreta de
 * direcciones.
 *
 * Es independiente del panel del tendero: otro usuario, otra contrasena y otra
 * sesion (`CustomerAuth`). El cliente solo puede tocar sus propios datos.
 */
final class CustomerController extends Controller
{
    private const ATTEMPTS = 5;

    // =====================================================================
    // ENTRAR / SALIR
    // =====================================================================

    public function showLogin(array $params = []): string
    {
        if (CustomerAuth::check($this->tenant->id())) {
            $this->redirect('cuenta');
        }

        return $this->view($this->themeView('account/login'), [
            'pageTitle' => 'Entrar en tu cuenta',
            'email'     => (string) $this->input('email', ''),
            'returnTo'  => $this->safeReturn((string) $this->input('volver', '')),
        ], 'shop');
    }

    public function login(array $params = []): string
    {
        $this->requireCsrf('cuenta/login');

        $storeId = $this->tenant->id();
        $email = mb_strtolower(trim((string) $this->input('email', '')));
        $password = (string) $this->input('password', '');
        $return = $this->safeReturn((string) $this->input('volver', ''));

        if (CustomerAuth::blocked($storeId, self::ATTEMPTS)) {
            Session::flash('error', 'Demasiados intentos fallidos. Espera unos minutos y vuelve a probar.');
            $this->redirect('cuenta/login');
        }

        if ($email === '' || $password === '' || !CustomerAuth::attempt($storeId, $email, $password)) {
            CustomerAuth::noteAttempt($storeId);
            Logger::warning('acceso', 'Entrada de cliente fallida', [
                'store_id' => $storeId,
                'email'    => mb_substr($email, 0, 180),
            ]);
            Session::flash('error', 'El email o la contrasena no son correctos.');
            $this->redirect('cuenta/login');
        }

        CustomerAuth::clearAttempts($storeId);
        $cliente = CustomerAuth::customer($storeId);
        Logger::info('acceso', 'Entrada de cliente correcta', [
            'store_id'    => $storeId,
            'cliente_id'  => (int) ($cliente['id'] ?? 0),
            'email'       => mb_substr($email, 0, 180),
        ]);
        Session::flash('success', 'Bienvenido de nuevo.');

        $this->redirect($return !== '' ? $return : 'cuenta');
    }

    public function logout(array $params = []): string
    {
        $this->requireCsrf('cuenta');
        CustomerAuth::logout($this->tenant->id());
        Session::flash('success', 'Has cerrado la sesion.');
        $this->redirect('');
    }

    // =====================================================================
    // REGISTRO
    // =====================================================================

    public function showRegister(array $params = []): string
    {
        if (CustomerAuth::check($this->tenant->id())) {
            $this->redirect('cuenta');
        }

        return $this->view($this->themeView('account/register'), [
            'pageTitle' => 'Crear tu cuenta',
            'email'     => (string) $this->input('email', ''),
            'name'      => (string) $this->input('name', ''),
            'returnTo'  => $this->safeReturn((string) $this->input('volver', '')),
        ], 'shop');
    }

    /**
     * Alta del cliente.
     *
     * Si ya compro como invitado con ese email, se "reclama" su cuenta: se le
     * pone contrasena y conserva los pedidos que ya tenia.
     */
    public function register(array $params = []): string
    {
        $this->requireCsrf('cuenta/registro');

        $storeId = $this->tenant->id();
        $email = mb_strtolower(trim((string) $this->input('email', '')));
        $name = trim((string) $this->input('name', ''));
        $phone = trim((string) $this->input('phone', ''));
        $password = (string) $this->input('password', '');
        $repeat = (string) $this->input('password_confirm', '');
        $return = $this->safeReturn((string) $this->input('volver', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Escribe un email valido.');
            $this->redirect('cuenta/registro');
        }
        if ($name === '') {
            Session::flash('error', 'Escribe tu nombre.');
            $this->redirect('cuenta/registro');
        }
        if (strlen($password) < 8) {
            Session::flash('error', 'La contrasena debe tener al menos 8 caracteres.');
            $this->redirect('cuenta/registro');
        }
        if ($password !== $repeat) {
            Session::flash('error', 'Las dos contrasenas no coinciden.');
            $this->redirect('cuenta/registro');
        }

        $existing = Customer::findByEmail($storeId, $email);
        if ($existing !== null && empty($existing['password_hash'])) {
            // Invitado que ahora se registra: se queda con su historial.
            Customer::updateById((int) $existing['id'], [
                'name'          => mb_substr($name, 0, 150),
                'phone'         => $phone !== '' ? mb_substr($phone, 0, 40) : null,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'is_guest'      => 0,
                'active'        => 1,
            ]);
            $customer = Customer::find((int) $existing['id']);
        } elseif ($existing !== null) {
            Session::flash('error', 'Ya existe una cuenta con ese email. Entra con tu contrasena.');
            $this->redirect('cuenta/login');
        } else {
            $id = Customer::createForStore($storeId, [
                'email'         => $email,
                'name'          => $name,
                'phone'         => $phone,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'is_guest'      => false,
            ]);
            $customer = Customer::find($id);
        }

        if ($customer !== null) {
            CustomerAuth::login($storeId, $customer);
        }
        Session::flash('success', 'Tu cuenta esta creada.');

        $this->redirect($return !== '' ? $return : 'cuenta');
    }

    // =====================================================================
    // MI CUENTA
    // =====================================================================

    public function index(array $params = []): string
    {
        $customer = $this->requireCustomer();
        $storeId = $this->tenant->id();

        return $this->view($this->themeView('account/index'), [
            'pageTitle' => 'Mi cuenta',
            'customer'  => $customer,
            'orders'    => array_slice(Customer::orders((int) $customer['id'], $storeId), 0, 5),
            'addresses' => CustomerAddress::forCustomer((int) $customer['id'], $storeId),
            'totalOrders' => count(Customer::orders((int) $customer['id'], $storeId)),
            'cartUnits' => Cart::count($storeId),
        ], 'shop');
    }

    public function orders(array $params = []): string
    {
        $customer = $this->requireCustomer();

        return $this->view($this->themeView('account/orders'), [
            'pageTitle' => 'Mis pedidos',
            'customer'  => $customer,
            'orders'    => Customer::orders((int) $customer['id'], $this->tenant->id()),
        ], 'shop');
    }

    public function order(array $params = []): string
    {
        $customer = $this->requireCustomer();
        $storeId = $this->tenant->id();
        $order = Order::findForCustomer($storeId, (int) $customer['id'], (string) ($params['code'] ?? ''), true);

        if ($order === null) {
            Session::flash('error', 'No hemos encontrado ese pedido.');
            $this->redirect('cuenta/pedidos');
        }

        return $this->view($this->themeView('account/order'), [
            'pageTitle' => 'Pedido ' . $order['code'],
            'customer'  => $customer,
            'order'     => $order,
            'store'     => $this->tenant->toArray(),
        ], 'shop');
    }

    // =====================================================================
    // DIRECCIONES
    // =====================================================================

    public function addresses(array $params = []): string
    {
        $customer = $this->requireCustomer();
        $storeId = $this->tenant->id();

        return $this->view($this->themeView('account/addresses'), [
            'pageTitle' => 'Mis direcciones',
            'customer'  => $customer,
            'addresses' => CustomerAddress::forCustomer((int) $customer['id'], $storeId),
        ], 'shop');
    }

    /** Formulario de direccion: nueva o editar una existente. */
    public function addressForm(array $params = []): string
    {
        $customer = $this->requireCustomer();
        $storeId = $this->tenant->id();

        $address = null;
        $id = (int) ($params['id'] ?? 0);
        if ($id > 0) {
            $address = CustomerAddress::findForCustomer($id, (int) $customer['id'], $storeId);
            if ($address === null) {
                Session::flash('error', 'Esa direccion no existe.');
                $this->redirect('cuenta/direcciones');
            }
        }

        return $this->view($this->themeView('account/address_form'), [
            'pageTitle' => $address === null ? 'Nueva direccion' : 'Editar direccion',
            'customer'  => $customer,
            'address'   => $address,
            'isFirst'   => CustomerAddress::countForCustomer((int) $customer['id'], $storeId) === 0,
        ], 'shop');
    }

    /** Guarda (crea o actualiza) una direccion del cliente. */
    public function addressSave(array $params = []): string
    {
        $this->requireCsrf('cuenta/direcciones');
        $customer = $this->requireCustomer();
        $storeId = $this->tenant->id();

        $id = (int) $this->input('id', 0);
        if ($id > 0 && CustomerAddress::findForCustomer($id, (int) $customer['id'], $storeId) === null) {
            Session::flash('error', 'Esa direccion no existe.');
            $this->redirect('cuenta/direcciones');
        }

        $data = [
            'label'        => (string) $this->input('label', ''),
            'name'         => (string) $this->input('name', ''),
            'tax_id'       => (string) $this->input('tax_id', ''),
            'address'      => (string) $this->input('address', ''),
            'detail'       => (string) $this->input('detail', ''),
            'postal_code'  => (string) $this->input('postal_code', ''),
            'city'         => (string) $this->input('city', ''),
            'province'     => (string) $this->input('province', ''),
            'country'      => (string) $this->input('country', 'Espana'),
            'phone'        => (string) $this->input('phone', ''),
            'mobile'       => (string) $this->input('mobile', ''),
            'id_pais'      => (int) $this->input('id_pais', 0),
            'id_provincia' => (int) $this->input('id_provincia', 0),
            'id_poblacion' => (int) $this->input('id_poblacion', 0),
            'is_default_ship' => (string) $this->input('is_default_ship', '') === '1',
            'is_default_bill' => (string) $this->input('is_default_bill', '') === '1',
        ];

        if (trim($data['name']) === '' || trim($data['address']) === '') {
            Session::flash('error', 'El nombre y la direccion son obligatorios.');
            $this->redirect('cuenta/direcciones/' . ($id > 0 ? $id : 'nueva'));
        }

        CustomerAddress::save((int) $customer['id'], $storeId, $data, $id);
        Session::flash('success', $id > 0 ? 'Direccion actualizada.' : 'Direccion guardada.');

        $this->redirect('cuenta/direcciones');
    }

    public function addressDelete(array $params = []): string
    {
        $this->requireCsrf('cuenta/direcciones');
        $customer = $this->requireCustomer();

        $id = (int) ($params['id'] ?? 0);
        if (CustomerAddress::remove($id, (int) $customer['id'], $this->tenant->id())) {
            Session::flash('success', 'Direccion borrada.');
        } else {
            Session::flash('error', 'Esa direccion no existe.');
        }

        $this->redirect('cuenta/direcciones');
    }

    // =====================================================================
    // PERFIL
    // =====================================================================

    public function profile(array $params = []): string
    {
        $customer = $this->requireCustomer();

        return $this->view($this->themeView('account/profile'), [
            'pageTitle' => 'Mis datos',
            'customer'  => $customer,
        ], 'shop');
    }

    public function profileSave(array $params = []): string
    {
        $this->requireCsrf('cuenta/perfil');
        $customer = $this->requireCustomer();

        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            Session::flash('error', 'El nombre no puede estar vacio.');
            $this->redirect('cuenta/perfil');
        }

        $updates = [
            'name'   => mb_substr($name, 0, 150),
            'tax_id' => mb_substr(trim((string) $this->input('tax_id', '')), 0, 30) ?: null,
            'phone'  => mb_substr(trim((string) $this->input('phone', '')), 0, 40) ?: null,
            'mobile' => mb_substr(trim((string) $this->input('mobile', '')), 0, 40) ?: null,
        ];

        // Cambio de contrasena (opcional).
        $password = (string) $this->input('password', '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                Session::flash('error', 'La contrasena debe tener al menos 8 caracteres.');
                $this->redirect('cuenta/perfil');
            }
            if ($password !== (string) $this->input('password_confirm', '')) {
                Session::flash('error', 'Las dos contrasenas no coinciden.');
                $this->redirect('cuenta/perfil');
            }
            $updates['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            $updates['is_guest'] = 0;
        }

        Customer::updateById((int) $customer['id'], $updates);
        Session::flash('success', 'Tus datos se han guardado.');

        $this->redirect('cuenta');
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    /** Cliente con la sesion abierta o al formulario de entrada. */
    private function requireCustomer(): array
    {
        $customer = CustomerAuth::customer($this->tenant->id());

        if ($customer === null || (int) $customer['active'] !== 1) {
            CustomerAuth::logout($this->tenant->id());
            Session::flash('error', 'Entra en tu cuenta para ver esta pagina.');
            $this->redirect('cuenta/login');
        }

        return $customer;
    }

    /** Solo se aceptan rutas de esta tienda (evita redirecciones abiertas). */
    private function safeReturn(string $return): string
    {
        $return = trim($return);
        if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//')) {
            return '';
        }

        return ltrim($return, '/');
    }
}
