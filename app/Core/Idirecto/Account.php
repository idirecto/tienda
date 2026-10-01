<?php

declare(strict_types=1);

namespace Tienda\Core\Idirecto;

use Tienda\Core\Config;
use Tienda\Core\Database;

/**
 * Cuenta con la que una tienda compra al mayorista.
 *
 * El enlace vive en `mt_stores` (`id_tienda_idirecto` -> `tiendas.id` y
 * `id_margen` -> `precios.id_margen`) y se edita en el panel > Ajustes. A partir
 * de ahi se resuelve todo lo que necesita el pedido del mayorista: tarifa,
 * sucursal, comercial, forma de pago y la direccion de facturacion.
 *
 * Los datos de la TIENDA (los que maneja esta web) ganan sobre los de la cuenta
 * del mayorista; los de la cuenta se usan como respaldo (NIF, provincia...).
 */
final class Account
{
    private static ?array $cache = null;

    /** Configuracion activa y tablas del mayorista disponibles. */
    public static function enabled(): bool
    {
        if (!(bool) Config::get('idirecto.enabled', true)) {
            return false;
        }
        foreach (['tiendas', 'pedidos', 'pedidos_det', 'pedidos_addr', 'precios'] as $table) {
            if (!Database::tableExists($table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Cuenta del mayorista de una tienda + datos derivados.
     *
     * @param array $store Fila de `mt_stores`
     */
    public static function forStore(array $store): array
    {
        // La resolucion completa se cachea por tienda: se usa varias veces por
        // peticion (aviso de configuracion, precios, envio).
        if (self::$cache !== null && (int) (self::$cache['store_id'] ?? 0) === (int) ($store['id'] ?? 0)) {
            return self::$cache;
        }

        $idTienda = (int) ($store['id_tienda_idirecto'] ?? 0);
        $tienda = $idTienda > 0 ? self::findTienda($idTienda) : null;

        $idMargen = (int) ($store['id_margen'] ?? 0);
        $origenMargen = 'tienda';
        if ($idMargen <= 0) {
            $idMargen = (int) ($tienda['id_margen'] ?? 0);
            $origenMargen = 'cuenta';
        }
        if ($idMargen <= 0) {
            $idMargen = (int) Config::get('idirecto.default_id_margen', 12);
            $origenMargen = 'defecto';
        }

        $sucursalId = (int) ($tienda['sucursal_id'] ?? 0);
        if ($sucursalId <= 0) {
            $sucursalId = (int) Config::get('idirecto.sucursal_id', 1);
        }

        $paisId = (int) ($tienda['id_pais'] ?? 0);
        if ($paisId <= 0) {
            $paisId = (int) (self::paisId((string) Config::get('idirecto.country_code', 'ES')) ?? 0);
        }

        $provinciaId = self::provinceId((string) ($store['province'] ?? ''), $paisId);
        if ($provinciaId === null) {
            $provinciaId = (int) ($tienda['id_provincia'] ?? 0) ?: null;
        }

        $store = array_merge([
            'name' => '', 'legal_name' => '', 'address' => '', 'city' => '',
            'postal_code' => '', 'phone' => '', 'email' => '',
        ], $store);

        $cuenta = [
            'store_id'       => (int) ($store['id'] ?? 0),
            'id_tienda'      => $idTienda > 0 ? $idTienda : null,
            'configurada'    => $idTienda > 0,
            'nombre'         => trim((string) $store['name']),
            'razon_social'   => self::firstFilled((string) $store['legal_name'], (string) ($tienda['nombre_sociedad'] ?? ''), (string) $store['name']),
            'nif'            => trim((string) ($tienda['nif_cif'] ?? '')),
            'direccion'      => self::firstFilled((string) $store['address'], (string) ($tienda['direccion'] ?? '')),
            'cp'             => self::firstFilled((string) $store['postal_code'], (string) ($tienda['cp'] ?? '')),
            'poblacion'      => self::firstFilled((string) $store['city'], (string) ($tienda['poblacion'] ?? '')),
            'telefono'       => self::firstFilled((string) $store['phone'], (string) ($tienda['telefono'] ?? '')),
            'email'          => self::firstFilled((string) $store['email'], (string) ($tienda['email'] ?? '')),
            'id_pais'        => $paisId > 0 ? $paisId : null,
            'id_provincia'   => $provinciaId,
            'id_margen'      => $idMargen,
            'margen_origen'  => $origenMargen,
            'sucursal_id'    => $sucursalId,
            'id_comercial'   => self::comercialId((int) ($tienda['id_comercial'] ?? 0)),
            'forma'          => self::paymentForm((int) ($tienda['forma'] ?? 0)),
            // Condiciones de pago de la cuenta (dias de vencimiento).
            'plazo'          => isset($tienda['vencimiento']) ? (int) $tienda['vencimiento'] : null,
            'dias'           => isset($tienda['dia']) ? (int) $tienda['dia'] : null,
            'cuenta'         => $tienda,
        ];

        return self::$cache = $cuenta;
    }

    /** Fila de `tiendas` (la cuenta del mayorista). */
    public static function findTienda(int $idTienda): ?array
    {
        if ($idTienda <= 0 || !Database::tableExists('tiendas')) {
            return null;
        }
        return Database::first(
            'SELECT id, nombre, nombre_sociedad, nif_cif, id_pais, id_provincia, direccion, cp,
                    poblacion, localidad, telefono, movil, email, id_margen, sucursal_id, id_comercial,
                    forma, vencimiento, dia, tipo, activo
             FROM tiendas WHERE id = :id LIMIT 1',
            ['id' => $idTienda]
        );
    }

    /**
     * Hash con el que el mayorista guarda las contrasenas de sus tiendas.
     *
     * OJO: NO es `password_hash()`, asi que `password_verify()` no vale para
     * `tiendas.password`. idirecto hace exactamente esto en su login
     * (`index_controller::login()`), y es lo que hay que replicar para
     * comprobar una cuenta.
     */
    public static function signature(string $password): string
    {
        return hash('sha256', md5(sha1($password)));
    }

    /**
     * Comprueba las credenciales de una cuenta del mayorista y la devuelve.
     *
     * Mismo criterio que el login de idirecto: `activo = 2` y la contrasena
     * firmada; ademas exigimos que la cuenta no este cerrada ni borrada. La
     * contrasena NO se guarda en ningun sitio: solo se comprueba aqui.
     *
     * @return array|null Fila de `tiendas` o null si no vale
     */
    public static function login(string $email, string $password): ?array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '' || $password === '' || !Database::tableExists('tiendas')) {
            return null;
        }

        return Database::first(
            'SELECT id, nombre, nombre_sociedad, nif_cif, email, id_pais, id_provincia, direccion,
                    cp, poblacion, localidad, telefono, movil, id_margen, sucursal_id, id_comercial,
                    forma, vencimiento, dia, activo, cerrada, deleted
             FROM tiendas
             WHERE LOWER(email) = :email
               AND password = :password
               AND activo = 2
               AND COALESCE(cerrada, 0) = 0
               AND COALESCE(deleted, 0) = 0
             LIMIT 1',
            ['email' => $email, 'password' => self::signature($password)]
        );
    }

    /** Id del pais a partir de su codigo ISO-2 (ES by defecto). */
    public static function paisId(string $iso2): ?int
    {
        $iso2 = strtoupper(trim($iso2));
        if ($iso2 === '' || !Database::tableExists('paises')) {
            return null;
        }
        $id = Database::scalar(
            'SELECT id FROM paises WHERE UPPER(codigo) = :codigo ORDER BY id ASC LIMIT 1',
            ['codigo' => $iso2]
        );
        return $id === null ? null : (int) $id;
    }

    /**
     * Id de provincia a partir de su nombre (Espana). `null` si no se encuentra:
     * `pedidos_addr.provincia` admite nulos, asi que un nombre raro no rompe.
     */
    public static function provinceId(string $nombre, ?int $idPais = null): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '' || !Database::tableExists('provincias')) {
            return null;
        }

        $params = ['nombre' => $nombre];
        $sql = 'SELECT id FROM provincias WHERE UPPER(provincia) = UPPER(:nombre)';
        if ($idPais !== null && $idPais > 0) {
            $sql .= ' AND id_pais = :pais';
            $params['pais'] = $idPais;
        }
        $id = Database::scalar($sql . ' ORDER BY id ASC LIMIT 1', $params);
        if ($id !== null) {
            return (int) $id;
        }

        // Segundo intento por parecido ("Zaragoza " vs "ZARAGOZA", "Alicante/Alacant"...).
        $params = ['nombre' => '%' . $nombre . '%'];
        $sql = 'SELECT id FROM provincias WHERE provincia LIKE :nombre';
        if ($idPais !== null && $idPais > 0) {
            $sql .= ' AND id_pais = :pais';
            $params['pais'] = $idPais;
        }
        $id = Database::scalar($sql . ' ORDER BY id ASC LIMIT 1', $params);

        return $id === null ? null : (int) $id;
    }

    /** Nombre de la forma de pago (`formas_pago`) del id guardado en la cuenta. */
    public static function paymentForm(int $formaId): ?string
    {
        if ($formaId <= 0 || !Database::tableExists('formas_pago')) {
            return null;
        }
        $forma = Database::scalar('SELECT forma FROM formas_pago WHERE id = :id LIMIT 1', ['id' => $formaId]);
        return $forma === null ? null : (string) $forma;
    }

    /** Nombre de la provincia a partir de su id (el inverso de `provinceId`). */
    public static function provinceName(int $idProvincia): string
    {
        if ($idProvincia <= 0 || !Database::tableExists('provincias')) {
            return '';
        }
        $nombre = Database::scalar('SELECT provincia FROM provincias WHERE id = :id LIMIT 1', ['id' => $idProvincia]);
        return $nombre === null ? '' : (string) $nombre;
    }

    /** Nombre del pais a partir de su id (`paises`). */
    public static function countryName(int $idPais): string
    {
        if ($idPais <= 0 || !Database::tableExists('paises')) {
            return '';
        }
        $pais = Database::scalar('SELECT pais FROM paises WHERE id = :id LIMIT 1', ['id' => $idPais]);
        return $pais === null ? '' : (string) $pais;
    }

    /** Comercial valido (la FK de `pedidos` lo exige) o el de configuracion. */
    private static function comercialId(int $id): ?int
    {
        $candidatos = [$id];
        $defecto = (int) Config::get('idirecto.comercial_id', 10);
        if ($defecto > 0) {
            $candidatos[] = $defecto;
        }

        foreach ($candidatos as $candidato) {
            if ($candidato <= 0 || !Database::tableExists('comerciales')) {
                continue;
            }
            $existe = Database::scalar('SELECT id FROM comerciales WHERE id = :id LIMIT 1', ['id' => $candidato]);
            if ($existe !== null) {
                return (int) $existe;
            }
        }

        return null;
    }

    private static function firstFilled(string ...$values): string
    {
        foreach ($values as $value) {
            $value = trim($value);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }
}
