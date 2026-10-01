<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Pedido que la tienda recibe de uno de sus clientes.
 *
 * Estados (columna `status`):
 *
 *   0 borrador   -> todavia no cuenta como pedido real
 *   1 activo     -> pendiente de preparar
 *   2 preparado  -> listo para enviar
 *   3 enviado    -> (al menos) enviado al cliente
 *   4 facturado  -> ya tiene factura
 *   5 cancelado
 *   6 borrado    -> papelera; se puede restaurar
 *
 * El listado del panel agrupa estos estados en pestanas (Activos, Facturados,
 * Borrados...) y ademas permite filtrar por un estado concreto.
 */
final class Order extends Model
{
    protected static string $table = 'mt_orders';

    public const STATUS_DRAFT     = 0;
    public const STATUS_ACTIVE    = 1;
    public const STATUS_PREPARING = 2;
    public const STATUS_SENT      = 3;
    public const STATUS_INVOICED  = 4;
    public const STATUS_CANCELLED = 5;
    public const STATUS_DELETED   = 6;

    /** Estados que se pueden enviar al mayorista (no la papelera ni el borrador). */
    public const SENDABLE_STATUSES = [self::STATUS_ACTIVE, self::STATUS_PREPARING];

    /** Etiqueta de cada estado. */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT     => 'Borrador',
            self::STATUS_ACTIVE    => 'Activo',
            self::STATUS_PREPARING => 'Preparado',
            self::STATUS_SENT      => 'Enviado',
            self::STATUS_INVOICED  => 'Facturado',
            self::STATUS_CANCELLED => 'Cancelado',
            self::STATUS_DELETED   => 'Borrado',
        ];
    }

    /**
     * Pestanas del listado. `null` = sin filtrar (todos los estados).
     *
     * @return array<string, array{label:string, statuses:array<int,int>|null}>
     */
    public static function statusGroups(): array
    {
        return [
            'todos'      => ['label' => 'Todos',       'statuses' => null],
            'activos'    => ['label' => 'Activos',     'statuses' => [self::STATUS_ACTIVE, self::STATUS_PREPARING, self::STATUS_SENT]],
            'borradores' => ['label' => 'Borradores',  'statuses' => [self::STATUS_DRAFT]],
            'facturados' => ['label' => 'Facturados',  'statuses' => [self::STATUS_INVOICED]],
            'cancelados' => ['label' => 'Cancelados',  'statuses' => [self::STATUS_CANCELLED]],
            'borrados'   => ['label' => 'Borrados',    'statuses' => [self::STATUS_DELETED]],
        ];
    }

    public static function statusLabel(int $status): string
    {
        return self::statuses()[$status] ?? 'Estado ' . $status;
    }

    /** Clase del `pill` del panel segun el estado. */
    public static function statusPill(int $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT     => 'info',
            self::STATUS_ACTIVE    => 'warning',
            self::STATUS_PREPARING => 'info',
            self::STATUS_SENT      => 'promo',
            self::STATUS_INVOICED  => 'ok',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_DELETED   => 'neutral',
            default                => 'neutral',
        };
    }

    /**
     * Estados de una pestana del listado.
     *
     * @return array<int,int>|null  null = todos
     */
    public static function statusesForGroup(string $group): ?array
    {
        if ($group !== '' && preg_match('/^\d+$/', $group) === 1) {
            $status = (int) $group;
            return array_key_exists($status, self::statuses()) ? [$status] : null;
        }

        return self::statusGroups()[$group]['statuses'] ?? null;
    }

    /** Marca un estado valido o el estado activo por defecto. */
    public static function normalizeStatus(mixed $status): int
    {
        $status = (int) $status;
        return array_key_exists($status, self::statuses()) ? $status : self::STATUS_ACTIVE;
    }

    // =====================================================================
    // LISTADO
    // =====================================================================

    /**
     * Pedidos de una tienda con filtro de estado y busqueda libre.
     *
     * @param array{group?:string, q?:string} $filters
     * @return array{items:array,total:int,page:int,per_page:int,pages:int}
     */
    public static function paginateForStore(int $storeId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));

        [$where, $params] = self::buildFilters($storeId, $filters);

        $total = (int) Database::scalar("SELECT COUNT(*) FROM mt_orders o $where", $params);

        $items = Database::select(
            "SELECT o.*,
                    (SELECT COUNT(*) FROM mt_order_items i WHERE i.order_id = o.id) AS items_total,
                    (SELECT COUNT(*) FROM mt_order_items i WHERE i.order_id = o.id AND i.sent_at IS NOT NULL) AS items_sent
             FROM mt_orders o
             $where
             ORDER BY o.id DESC
             LIMIT $perPage OFFSET " . (($page - 1) * $perPage),
            $params
        );

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    /** Numero de pedidos por estado (para las pestanas y el panel). */
    public static function countsForStore(int $storeId): array
    {
        $counts = array_fill_keys(array_keys(self::statuses()), 0);
        $rows = Database::select(
            'SELECT status, COUNT(*) AS n FROM mt_orders WHERE store_id = :store_id GROUP BY status',
            ['store_id' => $storeId]
        );
        foreach ($rows as $row) {
            $status = (int) $row['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['n'];
            } else {
                $counts[$status] = (int) $row['n'];
            }
        }
        return $counts;
    }

    /** Cuantos pedidos hay en los estados indicados (null = todos). */
    public static function countGroup(?array $statuses, array $counts): int
    {
        if ($statuses === null) {
            return array_sum($counts);
        }
        $total = 0;
        foreach ($statuses as $status) {
            $total += (int) ($counts[$status] ?? 0);
        }
        return $total;
    }

    /** Pedidos activos pendientes de atender (contador del dashboard). */
    public static function countActiveForStore(int $storeId): int
    {
        $statuses = self::statusGroups()['activos']['statuses'] ?? [];
        if ($statuses === []) {
            return 0;
        }
        $placeholders = [];
        $params = ['store_id' => $storeId];
        foreach ($statuses as $i => $status) {
            $placeholders[] = ':s' . $i;
            $params['s' . $i] = $status;
        }
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM mt_orders WHERE store_id = :store_id AND status IN (' . implode(',', $placeholders) . ')',
            $params
        );
    }

    /** @return array{0:string,1:array} */
    private static function buildFilters(int $storeId, array $filters): array
    {
        $where = ['o.store_id = :store_id'];
        $params = ['store_id' => $storeId];

        $statuses = self::statusesForGroup((string) ($filters['group'] ?? ''));
        if ($statuses !== null) {
            $placeholders = [];
            foreach ($statuses as $i => $status) {
                $placeholders[] = ':st' . $i;
                $params['st' . $i] = $status;
            }
            $where[] = 'o.status IN (' . implode(',', $placeholders) . ')';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            // Con ATTR_EMULATE_PREPARES = false un parametro no puede repetirse.
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $where[] = '(o.code LIKE :q1 OR o.customer_name LIKE :q2 OR o.customer_email LIKE :q3)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    // =====================================================================
    // ACCESO
    // =====================================================================

    public static function findForStore(int $id, int $storeId, bool $withItems = false): ?array
    {
        $order = Database::first(
            'SELECT * FROM mt_orders WHERE id = :id AND store_id = :store_id LIMIT 1',
            ['id' => $id, 'store_id' => $storeId]
        );

        if ($order === null) {
            return null;
        }

        if ($withItems) {
            $order['items'] = OrderItem::forOrder($id);
        }

        return $order;
    }

    // =====================================================================
    // ESCRITURA
    // =====================================================================

    /**
     * Crea el pedido con sus lineas y calcula los totales.
     *
     * @param array<string,mixed>              $data  Datos del pedido (sin store_id)
     * @param array<int,array<string,mixed>>   $lines Lineas normalizadas
     */
    public static function createWithItems(int $storeId, array $data, array $lines): int
    {
        return (int) Database::transaction(function () use ($storeId, $data, $lines): int {
            $orderId = Database::insert('mt_orders', [
                'store_id'       => $storeId,
                'code'           => null,
                'status'         => self::normalizeStatus($data['status'] ?? self::STATUS_ACTIVE),
                'customer_name'  => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'customer_tax_id' => $data['customer_tax_id'] ?? null,
                'ship_name'      => $data['ship_name'] ?? null,
                'ship_tax_id'    => $data['ship_tax_id'] ?? null,
                'ship_address'   => $data['ship_address'] ?? null,
                'ship_city'      => $data['ship_city'] ?? null,
                'ship_province'  => $data['ship_province'] ?? null,
                'ship_postal_code' => $data['ship_postal_code'] ?? null,
                'ship_country'   => $data['ship_country'] ?? 'Espana',
                'notes'          => $data['notes'] ?? null,
                'shipping'       => (float) ($data['shipping'] ?? 0),
            ]);

            // Numero visible: se deriva del id, que ya es unico en la tabla.
            $code = 'P' . date('y') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
            Database::update('mt_orders', $orderId, ['code' => $code]);

            $lineNo = 0;
            foreach ($lines as $line) {
                OrderItem::add($orderId, $storeId, ++$lineNo, $line);
            }

            self::recalculateTotals($orderId);

            return $orderId;
        });
    }

    /** Recalcula subtotal, IVA y total a partir de las lineas. */
    public static function recalculateTotals(int $orderId): void
    {
        $row = Database::first(
            'SELECT COALESCE(SUM(qty * price_customer), 0) AS subtotal,
                    COALESCE(SUM(qty * price_customer * tax_rate / 100), 0) AS tax_total,
                    (SELECT shipping FROM mt_orders WHERE id = :id2) AS shipping
             FROM mt_order_items WHERE order_id = :id',
            ['id' => $orderId, 'id2' => $orderId]
        ) ?? ['subtotal' => 0, 'tax_total' => 0, 'shipping' => 0];

        $subtotal = round((float) $row['subtotal'], 2);
        $tax      = round((float) $row['tax_total'], 2);
        $shipping = round((float) $row['shipping'], 2);

        Database::update('mt_orders', $orderId, [
            'subtotal'  => $subtotal,
            'tax_total' => $tax,
            'total'     => round($subtotal + $tax + $shipping, 2),
        ]);
    }

    /** Cambia el estado del pedido. */
    public static function setStatus(int $id, int $status): void
    {
        self::updateById($id, ['status' => self::normalizeStatus($status)]);
    }

    /** Marca el pedido como enviado a idirecto (referencia y fecha). */
    public static function markSentToIdirecto(int $id, int $idTienda, string $reference): void
    {
        self::updateById($id, [
            'id_tienda_idirecto' => $idTienda,
            'idirecto_ref'       => $reference,
            'sent_at'            => date('Y-m-d H:i:s'),
        ]);
    }
}
