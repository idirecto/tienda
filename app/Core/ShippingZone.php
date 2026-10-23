<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Core\Idirecto\Account;
use Tienda\Models\Setting;

/**
 * Zonas comerciales de venta y envio.
 *
 * Las zonas son un catalogo cerrado:
 *
 *   peninsula  -> 47 provincias peninsulares + Baleares
 *   ceuta      -> Ceuta
 *   melilla    -> Melilla
 *   canarias   -> Las Palmas + Santa Cruz de Tenerife
 *
 * La clasificacion esta confirmada por negocio. No se deduce del nombre de la
 * tienda ni del importe del pedido. La zona no interviene en el calculo de
 * impuestos: solo decide si se puede entregar y que configuracion de envio se
 * aplica.
 */
final class ShippingZone
{
    public const PENINSULA = 'peninsula';
    public const CEUTA = 'ceuta';
    public const MELLILA = 'melilla';
    public const CANARIAS = 'canarias';

    public const BLOCKED_MESSAGE_KEY = 'shipping_blocked_message';

    /** Etiquetas visibles. */
    private const LABELS = [
        self::PENINSULA => 'Península',
        self::CEUTA => 'Ceuta',
        self::MELLILA => 'Melilla',
        self::CANARIAS => 'Canarias',
    ];

    /**
     * Correspondencia confirmada provincia (id de `provincias`) -> zona.
     *
     * Baleares (9) se incluye en peninsula por decision expresa del negocio.
     */
    private const ZONE_PROVINCES = [
        self::PENINSULA => [
            1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15,
            17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 29, 30, 31,
            32, 33, 35, 36, 37, 38, 39, 40, 42, 43, 44, 45, 46, 47,
            48, 49, 50, 51, 52,
        ],
        self::CEUTA => [16],
        self::MELLILA => [34],
        self::CANARIAS => [28, 41],
    ];

    /**
     * Alias de escritura para direcciones historicas que no tienen id_provincia.
     * No cambian la clasificacion; solo permiten reconocer nombres habituales.
     */
    private const PROVINCE_ALIASES = [
        1 => ['a coruna', 'la coruna', 'coruna'],
        2 => ['alava', 'araba'],
        4 => ['alicante alacant', 'alacant'],
        6 => ['principado de asturias'],
        9 => ['baleares', 'islas baleares', 'illes balears', 'balears'],
        15 => ['castello', 'castellon de la plana'],
        20 => ['gerona'],
        23 => ['guipuzcoa', 'gipuzkoa'],
        27 => ['la rioja', 'rioja'],
        30 => ['lerida'],
        32 => ['comunidad de madrid'],
        35 => ['region de murcia'],
        36 => ['navarra', 'comunidad foral de navarra'],
        37 => ['orense', 'ourense'],
        48 => ['valencia'],
        50 => ['vizcaya', 'bizkaia'],
    ];

    private static bool $spainCountryLookedUp = false;
    private static ?int $spainCountryId = null;
    private static ?array $provinces = null;
    private static ?array $provinceNameIndex = null;
    private static ?array $zoneByProvince = null;

    /** @return array<string,string> */
    public static function labels(): array
    {
        return self::LABELS;
    }

    public static function label(string $zone): string
    {
        return self::LABELS[$zone] ?? $zone;
    }

    public static function spainCountryId(): ?int
    {
        if (!self::$spainCountryLookedUp) {
            self::$spainCountryId = Account::paisId('ES');
            self::$spainCountryLookedUp = true;
        }

        return self::$spainCountryId;
    }

    /**
     * Provincias espanolas canónicas, en el orden de la base central.
     *
     * @return array<int,string>
     */
    public static function provinces(): array
    {
        if (self::$provinces !== null) {
            return self::$provinces;
        }

        $countryId = self::spainCountryId();
        if ($countryId === null || !Database::tableExists('provincias')) {
            return self::$provinces = [];
        }

        $rows = Database::select(
            'SELECT id, provincia FROM provincias WHERE id_pais = :country ORDER BY provincia ASC',
            ['country' => $countryId]
        );

        $out = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['provincia'] ?? ''));
            if ($id > 0 && $name !== '' && self::zoneForProvinceId($id) !== null) {
                $out[$id] = $name;
            }
        }

        return self::$provinces = $out;
    }

    public static function provinceName(int $provinceId): ?string
    {
        return self::provinces()[$provinceId] ?? null;
    }

    public static function zoneForProvinceId(int $provinceId): ?string
    {
        if (self::$zoneByProvince === null) {
            $map = [];
            foreach (self::ZONE_PROVINCES as $zone => $provinceIds) {
                foreach ($provinceIds as $id) {
                    $map[(int) $id] = $zone;
                }
            }
            self::$zoneByProvince = $map;
        }

        return self::$zoneByProvince[$provinceId] ?? null;
    }

    /**
     * Resuelve la provincia de una direccion:
     *   1. id_provincia valido de Espana;
     *   2. nombre canonico o alias habitual;
     *   3. codigo postal contra la tabla `poblaciones`.
     */
    public static function resolveProvince(array $address): ?int
    {
        $id = (int) ($address['id_provincia'] ?? 0);
        if ($id > 0 && self::provinceName($id) !== null) {
            return $id;
        }

        $name = self::normalizeName((string) ($address['province'] ?? ''));
        if ($name !== '') {
            $index = self::provinceNameIndex();
            if (isset($index[$name])) {
                return (int) $index[$name];
            }
        }

        $postalCode = preg_replace('/\D+/', '', (string) ($address['postal_code'] ?? '')) ?? '';
        if (strlen($postalCode) >= 5) {
            $postalCode = substr($postalCode, 0, 5);
        }
        $countryId = self::spainCountryId();
        if (strlen($postalCode) === 5 && $countryId !== null && Database::tableExists('poblaciones')) {
            // Primero el CP exacto y, si la tabla no tiene esa poblacion, por
            // prefijo provincial (los dos primeros digitos identifican la
            // provincia en Espana). Asi no dependemos de que exista la
            // localidad concreta en `poblaciones`.
            foreach (['cp' => $postalCode, 'prefix' => substr($postalCode, 0, 2) . '%'] as $mode => $value) {
                // Si hay alguna fila mal clasificada en `poblaciones`, manda la
                // provincia con mas localidades para ese CP/prefijo.
                $sql = $mode === 'cp'
                    ? 'SELECT id_provincia, COUNT(*) AS total FROM poblaciones
                       WHERE id_pais = :country AND cp = :value
                       GROUP BY id_provincia ORDER BY total DESC, id_provincia ASC LIMIT 1'
                    : 'SELECT id_provincia, COUNT(*) AS total FROM poblaciones
                       WHERE id_pais = :country AND cp LIKE :value
                       GROUP BY id_provincia ORDER BY total DESC, id_provincia ASC LIMIT 1';
                $row = Database::first($sql, ['country' => $countryId, 'value' => $value]);
                if ($row !== null) {
                    $provinceId = (int) ($row['id_provincia'] ?? 0);
                    if (self::provinceName($provinceId) !== null) {
                        return $provinceId;
                    }
                }
            }
        }

        return null;
    }

    /** Solo se admiten direcciones de Espana. */
    public static function countryIsSpain(array $address): bool
    {
        $countryId = (int) ($address['id_pais'] ?? 0);
        if ($countryId > 0) {
            $spainId = self::spainCountryId();
            if ($spainId === null || $countryId !== $spainId) {
                return false;
            }
        }

        $country = self::normalizeName((string) ($address['country'] ?? ''));
        // El checkout antiguo dejaba 'Espana' por defecto; un valor vacio se
        // interpreta igual que el formulario historico. Si hay pais textual y
        // contradice al id, se rechaza la direccion.
        return $country === '' || in_array($country, ['espana', 'es', 'spain'], true);
    }

    public static function zoneForAddress(array $address): ?string
    {
        if (!self::countryIsSpain($address)) {
            return null;
        }

        $provinceId = self::resolveProvince($address);
        $provinceZone = $provinceId !== null ? self::zoneForProvinceId($provinceId) : null;
        $postalZone = self::zoneForPostalCode((string) ($address['postal_code'] ?? ''));

        // Si provincia y CP apuntan a zonas distintas, la direccion es
        // incoherente: no se deja pasar solo porque una de las dos parezca
        // valida.
        if ($provinceZone !== null && $postalZone !== null && $provinceZone !== $postalZone) {
            return null;
        }

        return $provinceZone ?? $postalZone;
    }

    /**
     * Respaldo territorial por los dos primeros digitos del CP espanol.
     * No sustituye a la provincia: solo resuelve direcciones antiguas cuyo
     * texto de provincia no se puede identificar.
     */
    public static function zoneForPostalCode(string $postalCode): ?string
    {
        $digits = preg_replace('/\D+/', '', $postalCode) ?? '';
        if (strlen($digits) !== 5) {
            return null;
        }

        $prefix = (int) substr($digits, 0, 2);
        if ($prefix === 35 || $prefix === 38) {
            return self::CANARIAS;
        }
        if ($prefix === 51) {
            return self::CEUTA;
        }
        if ($prefix === 52) {
            return self::MELLILA;
        }
        if ($prefix >= 1 && $prefix <= 50) {
            return self::PENINSULA;
        }

        return null;
    }

    /**
     * Completa provincia/pais con los valores canonicos de la base central.
     * No fuerza una zona: una direccion puede ser valida y no estar activa.
     */
    public static function canonicalizeAddress(array $address): array
    {
        if (!self::countryIsSpain($address)) {
            return $address;
        }

        $provinceId = self::resolveProvince($address);
        if ($provinceId !== null) {
            $address['id_provincia'] = $provinceId;
            $name = self::provinceName($provinceId);
            if ($name !== null) {
                $address['province'] = $name;
            }
        }

        $spainId = self::spainCountryId();
        if ($spainId !== null) {
            $address['id_pais'] = $spainId;
            $address['country'] = 'Espana';
        }

        return $address;
    }

    public static function deliveryLabel(?int $min, ?int $max): string
    {
        if ($min === null && $max === null) {
            return '';
        }
        if ($min !== null && $max !== null) {
            return $min === $max ? $min . ' días' : $min . '-' . $max . ' días';
        }
        if ($min !== null) {
            return 'Desde ' . $min . ' días';
        }

        return 'Hasta ' . $max . ' días';
    }

    /** Configuracion de las cuatro zonas, con valores por defecto compatibles. */
    public static function storeConfig(int $storeId): array
    {
        $config = [];
        foreach (self::LABELS as $zone => $label) {
            $config[$zone] = [
                'zone_code'         => $zone,
                'label'             => $label,
                'enabled'           => 1,
                'cost'              => null,
                'free_from'         => null,
                'delivery_days_min' => null,
                'delivery_days_max' => null,
                'conditions'        => '',
            ];
        }

        if (!Database::tableExists('mt_store_shipping_zones')) {
            return $config;
        }

        $rows = Database::select(
            'SELECT * FROM mt_store_shipping_zones WHERE store_id = :store_id',
            ['store_id' => $storeId]
        );

        foreach ($rows as $row) {
            $zone = (string) ($row['zone_code'] ?? '');
            if (!isset($config[$zone])) {
                continue;
            }

            $config[$zone]['enabled'] = (int) ($row['enabled'] ?? 0) === 1 ? 1 : 0;
            $config[$zone]['cost'] = $row['cost'] === null || $row['cost'] === ''
                ? null : round((float) $row['cost'], 2);
            $config[$zone]['free_from'] = $row['free_from'] === null || $row['free_from'] === ''
                ? null : round((float) $row['free_from'], 2);
            $config[$zone]['delivery_days_min'] = $row['delivery_days_min'] === null || $row['delivery_days_min'] === ''
                ? null : max(0, (int) $row['delivery_days_min']);
            $config[$zone]['delivery_days_max'] = $row['delivery_days_max'] === null || $row['delivery_days_max'] === ''
                ? null : max(0, (int) $row['delivery_days_max']);
            $config[$zone]['conditions'] = (string) ($row['conditions'] ?? '');
        }

        return $config;
    }

    public static function enabledConfig(int $storeId, string $zone): ?array
    {
        $config = self::storeConfig($storeId)[$zone] ?? null;

        return $config !== null && (int) $config['enabled'] === 1 ? $config : null;
    }

    public static function configuredBlockedMessage(int $storeId): string
    {
        return trim((string) Setting::get($storeId, self::BLOCKED_MESSAGE_KEY, ''));
    }

    public static function blockedMessage(int $storeId): string
    {
        $message = self::configuredBlockedMessage($storeId);

        return $message !== ''
            ? $message
            : 'La dirección de envío no está disponible para esta tienda.';
    }

    /**
     * Guarda la configuracion confirmada. El controlador valida los importes y
     * plazos antes de llamar aqui.
     *
     * @param array<string,array<string,mixed>> $zones
     */
    public static function saveStoreConfig(int $storeId, array $zones, ?string $message): void
    {
        Database::transaction(static function () use ($storeId, $zones, $message): void {
            foreach (self::LABELS as $zoneCode => $label) {
                $zone = $zones[$zoneCode] ?? [];
                $params = [
                    'store_id'         => $storeId,
                    'zone_code'        => $zoneCode,
                    'enabled'          => !empty($zone['enabled']) ? 1 : 0,
                    'cost'             => $zone['cost'] ?? null,
                    'free_from'        => $zone['free_from'] ?? null,
                    'delivery_min'     => $zone['delivery_days_min'] ?? null,
                    'delivery_max'     => $zone['delivery_days_max'] ?? null,
                    'conditions'       => $zone['conditions'] ?? null,
                ];

                Database::execute(
                    'INSERT INTO mt_store_shipping_zones
                        (store_id, zone_code, enabled, cost, free_from, delivery_days_min, delivery_days_max, conditions)
                     VALUES
                        (:store_id, :zone_code, :enabled, :cost, :free_from, :delivery_min, :delivery_max, :conditions)
                     ON DUPLICATE KEY UPDATE
                        enabled = VALUES(enabled),
                        cost = VALUES(cost),
                        free_from = VALUES(free_from),
                        delivery_days_min = VALUES(delivery_days_min),
                        delivery_days_max = VALUES(delivery_days_max),
                        conditions = VALUES(conditions)',
                    $params
                );
            }

            Setting::set($storeId, self::BLOCKED_MESSAGE_KEY, $message !== '' ? $message : null);
        });
    }

    /** Crea las cuatro filas iniciales sin tocar filas ya existentes. */
    public static function seedDefaults(int $storeId): void
    {
        if ($storeId <= 0 || !Database::tableExists('mt_store_shipping_zones')) {
            return;
        }

        foreach (array_keys(self::LABELS) as $zoneCode) {
            Database::execute(
                'INSERT IGNORE INTO mt_store_shipping_zones (store_id, zone_code, enabled)
                 VALUES (:store_id, :zone_code, 1)',
                ['store_id' => $storeId, 'zone_code' => $zoneCode]
            );
        }
    }

    /** @return array<string,int> */
    private static function provinceNameIndex(): array
    {
        if (self::$provinceNameIndex !== null) {
            return self::$provinceNameIndex;
        }

        $index = [];
        foreach (self::provinces() as $id => $name) {
            $normalized = self::normalizeName($name);
            if ($normalized !== '') {
                $index[$normalized] = (int) $id;
            }
        }

        foreach (self::PROVINCE_ALIASES as $id => $aliases) {
            foreach ($aliases as $alias) {
                $normalized = self::normalizeName($alias);
                if ($normalized !== '' && !isset($index[$normalized])) {
                    $index[$normalized] = (int) $id;
                }
            }
        }

        return self::$provinceNameIndex = $index;
    }

    private static function normalizeName(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($converted !== false && $converted !== '') {
            $value = $converted;
        }

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '');
    }
}
