<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Core\Idirecto\Account;
use Tienda\Models\Store;

/**
 * Registro de una tienda nueva a partir de una cuenta del mayorista.
 *
 * Regla de negocio: **en esta web solo puede tener tienda quien ya es cliente
 * del mayorista**. El tendero entra con su email y su contrasena de idirecto
 * (`Account::login()`), y aqui se crea su tienda (`mt_stores`) ya enlazada con
 * esa cuenta (`id_tienda_idirecto`) y su usuario de panel (`mt_store_users`, con
 * contrasena propia). La tarifa se consulta siempre en la ficha de idirecto; el
 * `id_margen` local solo se conserva como copia historica y no decide precios.
 *
 * La tienda nace activa y con los datos fiscales/de contacto de la cuenta, de
 * modo que pueda vender y enviar pedidos sin configurar nada; luego se edita
 * todo desde el panel.
 */
final class Registration
{
    /** Tablas propias que hacen falta para poder registrar. */
    private const REQUIRED_TABLES = ['mt_stores', 'mt_store_users', 'mt_plans'];

    /** Estados de una tienda (columna `mt_stores.status`). */
    public const STORE_PENDING = 0;
    public const STORE_ACTIVE = 1;

    /**
     * Interruptor de la activacion completa.
     *
     * Se mantiene cerrado hasta que existan contrato autorizado, PDF privado,
     * verificacion y correo aprobados. Un false aqui impide crear tiendas
     * aunque el resto de piezas ya esten desplegadas.
     */
    public static function completionEnabled(): bool
    {
        return (bool) Config::get('registration.completion_enabled', false);
    }

    /**
     * Guarda de la fase anterior.
     *
     * Mientras este activa, el controlador valida todo pero no crea tiendas.
     * Solo se desactiva de forma explicita para la prueba controlada del flujo.
     */
    public static function legacyGuardEnabled(): bool
    {
        return (bool) Config::get('registration.legacy_guard_enabled', true);
    }

    /** Tablas nuevas necesarias para el alta con evidencia y verificacion. */
    public static function onboardingSchemaReady(): bool
    {
        foreach ([
            'mt_contract_versions',
            'mt_store_registrations',
            'mt_contract_acceptances',
            'mt_registration_verifications',
        ] as $table) {
            if (!Database::tableExists($table)) {
                return false;
            }
        }

        // Migracion 015: sin estas columnas no se puede auditar la aceptacion.
        foreach (['source_template_sha256', 'ip_address', 'user_agent'] as $column) {
            $exists = Database::scalar(
                'SELECT COUNT(*)
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :table
                    AND COLUMN_NAME = :column',
                ['table' => 'mt_contract_acceptances', 'column' => $column]
            );
            if ((int) ($exists ?? 0) === 0) {
                return false;
            }
        }

        return true;
    }

    /** Se puede registrar: puente activo, registro abierto y tablas presentes. */
    public static function isOpen(): bool
    {
        if (!(bool) Config::get('idirecto.enabled', true) || !(bool) Config::get('idirecto.register', true)) {
            return false;
        }
        if (!Database::tableExists('tiendas')) {
            return false;
        }
        foreach (self::REQUIRED_TABLES as $table) {
            if (!Database::tableExists($table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Datos con los que nace la tienda, a partir de la cuenta del mayorista.
     *
     * Es una funcion pura (no escribe nada) para poder comprobarla en las
     * verificaciones: los datos de la cuenta se copian tal cual y el resto son
     * valores por defecto que el tendero puede cambiar en el panel.
     *
     * @param array       $tienda Fila de `tiendas`
     * @param string|null $slug   Slug pedido por el tendero (null = derivado)
     */
    public static function dataFromAccount(array $tienda, ?string $slug = null): array
    {
        $nombre = trim((string) ($tienda['nombre'] ?? ''));
        $razonSocial = trim((string) ($tienda['nombre_sociedad'] ?? ''));
        $comercial = $nombre !== '' ? $nombre : $razonSocial;

        $idTienda = (int) ($tienda['id'] ?? 0);
        $idMargen = (int) ($tienda['id_margen'] ?? 0);

        // La direccion pedida se valida antes de tocar la base de datos: si es
        // una etiqueta reservada (www, panel, mail...) la tienda nacería con un
        // subdominio que no resuelve. Vacio = se genera del nombre comercial.
        $slugPedido = $slug !== null && trim($slug) !== '' ? Subdomain::normalize($slug) : '';
        if ($slugPedido !== '') {
            $slugError = Subdomain::formatError($slugPedido);
            if ($slugError !== null) {
                throw new ValidationException('Direccion de tienda no valida: ' . $slugError);
            }
        }
        $slugFinal = self::uniqueSlug($slugPedido !== '' ? $slugPedido : $comercial);

        return [
            'slug'        => $slugFinal,
            'name'        => mb_substr($comercial !== '' ? $comercial : 'Mi tienda', 0, 120),
            'legal_name'  => $razonSocial !== '' ? mb_substr($razonSocial, 0, 150) : null,
            'email'       => mb_substr(mb_strtolower(trim((string) ($tienda['email'] ?? ''))), 0, 150) ?: null,
            'phone'       => mb_substr(trim((string) ($tienda['telefono'] ?? '')) ?: trim((string) ($tienda['movil'] ?? '')), 0, 40) ?: null,
            'id_plan'     => self::defaultPlanId(),
            'status'      => self::STORE_ACTIVE,
            'theme'       => 'idirecto',
            'address'     => mb_substr(trim((string) ($tienda['direccion'] ?? '')), 0, 200) ?: null,
            'city'        => mb_substr(trim((string) ($tienda['poblacion'] ?? '')), 0, 100) ?: null,
            'province'    => mb_substr(Account::provinceName((int) ($tienda['id_provincia'] ?? 0)), 0, 100),
            'postal_code' => mb_substr(trim((string) ($tienda['cp'] ?? '')), 0, 20) ?: null,
            'country'     => mb_substr(Account::countryName((int) ($tienda['id_pais'] ?? 0)) ?: 'Espana', 0, 60),
            'currency'    => 'EUR',
            // El IVA de partida es el general; se ajusta en el panel.
            'tax_rate'    => 21.00,
            'show_prices' => 1,
            'allow_orders' => 1,
            'meta_title'  => mb_substr($comercial !== '' ? $comercial : 'Mi tienda', 0, 200),
            // Enlace con la cuenta del mayorista: lo que permite enviar pedidos.
            'id_tienda_idirecto' => $idTienda > 0 ? $idTienda : null,
            // Copia historica; la tarifa efectiva se lee de `tiendas.id_margen`.
            'id_margen'   => $idMargen > 0 ? $idMargen : null,
        ];
    }

    /**
     * Crea la tienda y su usuario de panel.
     *
     * @param array  $tienda   Fila de `tiendas` (cuenta del mayorista)
     * @param string $email    Email del usuario del panel (el de la cuenta)
     * @param string $password Contrasena NUEVA del panel (no la de idirecto)
     * @param string|null $slug Slug pedido por el tendero
     * @return array{store_id:int,user_id:int,slug:string}
     * @throws ValidationException si el email ya tiene usuario o hay datos invalidos
     */
    public static function register(array $tienda, string $email, string $password, ?string $slug = null): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('El email de la cuenta no es valido.');
        }
        if (strlen($password) < 8) {
            throw new ValidationException('La contrasena del panel debe tener al menos 8 caracteres.');
        }
        if (self::existingStoreFor((int) ($tienda['id'] ?? 0)) !== null) {
            throw new ValidationException('Ya hay una tienda registrada con esa cuenta del mayorista.');
        }
        if (Database::scalar('SELECT id FROM mt_store_users WHERE email = :email LIMIT 1', ['email' => $email]) !== null) {
            throw new ValidationException('Ya existe un usuario con ese email. Entra en el panel o usa otro email.');
        }

        $data = self::dataFromAccount($tienda, $slug);

        $crear = static fn (): array => self::persistStoreAndUser($data, $email, $password, true);

        // Transaccion propia; si ya hay una abierta (pruebas con rollback) se une.
        if (Database::pdo()->inTransaction()) {
            return $crear();
        }

        return Database::transaction($crear);
    }

    /**
     * Crea la tienda y el usuario, pero los deja pendientes de verificar.
     *
     * La tienda nace con `status = 0` y el usuario con `active = 0`; solo la
     * verificacion del correo los activa. Comparte validaciones y datos con
     * `register()` para que el alta pendiente no diverja de la definitiva.
     *
     * @return array{store_id:int,user_id:int,slug:string}
     */
    public static function registerPending(array $tienda, string $email, string $password, ?string $slug = null): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('El email de la cuenta no es valido.');
        }
        if (strlen($password) < 8) {
            throw new ValidationException('La contrasena del panel debe tener al menos 8 caracteres.');
        }
        if (self::existingStoreFor((int) ($tienda['id'] ?? 0)) !== null) {
            throw new ValidationException('Ya hay una tienda registrada con esa cuenta del mayorista.');
        }
        if (Database::scalar('SELECT id FROM mt_store_users WHERE email = :email LIMIT 1', ['email' => $email]) !== null) {
            throw new ValidationException('Ya existe un usuario con ese email. Entra en el panel o usa otro email.');
        }

        $data = self::dataFromAccount($tienda, $slug);
        $data['status'] = self::STORE_PENDING;

        $crear = static fn (): array => self::persistStoreAndUser($data, $email, $password, false);

        if (Database::pdo()->inTransaction()) {
            return $crear();
        }

        return Database::transaction($crear);
    }

    /**
     * Inserta tienda, zonas y usuario con el estado indicado.
     *
     * @return array{store_id:int,user_id:int,slug:string}
     */
    private static function persistStoreAndUser(array $data, string $email, string $password, bool $active): array
    {
        $storeId = (int) Database::insert('mt_stores', $data);

        // Toda tienda nueva arranca con las cuatro zonas disponibles y la
        // tarifa general como respaldo; el tendero puede ajustarlas luego.
        ShippingZone::seedDefaults($storeId);

        $userId = (int) Database::insert('mt_store_users', [
            'store_id'      => $storeId,
            'name'          => $data['name'],
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => 'owner',
            'active'        => $active ? 1 : 0,
        ]);

        return ['store_id' => $storeId, 'user_id' => $userId, 'slug' => (string) $data['slug']];
    }

    /** Tienda ya registrada para esa cuenta del mayorista (si la hay). */
    public static function existingStoreFor(int $idTienda): ?array
    {
        if ($idTienda <= 0) {
            return null;
        }
        return Database::first(
            'SELECT id, slug, name, status FROM mt_stores WHERE id_tienda_idirecto = :id_tienda LIMIT 1',
            ['id_tienda' => $idTienda]
        );
    }

    /** Slug libre a partir de un texto (anade -2, -3... si esta cogido). */
    public static function uniqueSlug(string $base): string
    {
        // suggest() evita etiquetas reservadas (www, panel, mail...) y deja una
        // etiqueta valida incluso cuando el texto de entrada viene vacio.
        $base = Subdomain::suggest($base);

        $slug = $base;
        $i = 2;
        while (self::slugTaken($slug)) {
            $suffix = '-' . $i;
            $slug = rtrim(substr($base, 0, Subdomain::MAX_LENGTH - strlen($suffix)), '-') . $suffix;
            if (++$i > 200) {
                $slug = $base . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        return $slug;
    }

    /** Plan con el que nace la tienda (config; si no existe, el mas basico). */
    public static function defaultPlanId(): ?int
    {
        $code = (string) Config::get('idirecto.register_plan', 'basico');
        $id = Database::scalar('SELECT id FROM mt_plans WHERE code = :code LIMIT 1', ['code' => $code]);
        if ($id !== null) {
            return (int) $id;
        }

        $id = Database::scalar('SELECT id FROM mt_plans WHERE active = 1 ORDER BY sort ASC, id ASC LIMIT 1');
        return $id === null ? null : (int) $id;
    }

    private static function slugTaken(string $slug): bool
    {
        return Store::findBySlugWithPlan($slug) !== null;
    }
}
