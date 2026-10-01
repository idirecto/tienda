<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Linea de un pedido de la tienda.
 *
 * `source` distingue de donde sale el producto:
 *   - catalog -> `productos` del mayorista: se puede enviar a idirecto
 *   - own     -> `mt_own_products` de la tienda: NO se puede enviar
 *
 * El envio al mayorista es por linea (`sent_at` + `idirecto_pedido_id`), asi que
 * un pedido con 4 lineas puede mandarse a idirecto en dos envios con 2 y 2.
 */
final class OrderItem extends Model
{
    protected static string $table = 'mt_order_items';

    public const SOURCE_CATALOG = 'catalog';
    public const SOURCE_OWN     = 'own';

    /** @return array<int,array> */
    public static function forOrder(int $orderId): array
    {
        return Database::select(
            'SELECT * FROM mt_order_items WHERE order_id = :order_id ORDER BY line_no ASC, id ASC',
            ['order_id' => $orderId]
        );
    }

    public static function findForOrder(int $id, int $orderId): ?array
    {
        return Database::first(
            'SELECT * FROM mt_order_items WHERE id = :id AND order_id = :order_id LIMIT 1',
            ['id' => $id, 'order_id' => $orderId]
        );
    }

    /** Lineas de un pedido que todavia se pueden enviar al mayorista. */
    public static function pendingSendable(int $orderId): array
    {
        return Database::select(
            "SELECT * FROM mt_order_items
             WHERE order_id = :order_id AND source = :source
               AND sent_at IS NULL AND product_id IS NOT NULL
             ORDER BY line_no ASC, id ASC",
            ['order_id' => $orderId, 'source' => self::SOURCE_CATALOG]
        );
    }

    /** Se puede enviar al mayorista: producto del catalogo y sin enviar aun. */
    public static function isSendable(array $item): bool
    {
        return (string) ($item['source'] ?? '') === self::SOURCE_CATALOG
            && (int) ($item['product_id'] ?? 0) > 0
            && empty($item['sent_at']);
    }

    public static function nextLineNo(int $orderId): int
    {
        $max = (int) Database::scalar(
            'SELECT COALESCE(MAX(line_no), 0) FROM mt_order_items WHERE order_id = :order_id',
            ['order_id' => $orderId]
        );
        return $max + 1;
    }

    /** Anade una linea ya normalizada. */
    public static function add(int $orderId, int $storeId, int $lineNo, array $line): int
    {
        return (int) Database::insert('mt_order_items', [
            'order_id'       => $orderId,
            'store_id'       => $storeId,
            'line_no'        => $lineNo,
            'source'         => $line['source'],
            'product_id'     => $line['product_id'],
            'sku'            => $line['sku'],
            'name'           => $line['name'],
            'qty'            => $line['qty'],
            'price_customer' => $line['price_customer'],
            'tax_rate'       => $line['tax_rate'],
            'price_idirecto' => $line['price_idirecto'] ?? null,
            'cost_idirecto'  => $line['cost_idirecto'] ?? null,
        ]);
    }

    public static function deleteLine(int $id, int $orderId): int
    {
        return Database::execute(
            'DELETE FROM mt_order_items WHERE id = :id AND order_id = :order_id',
            ['id' => $id, 'order_id' => $orderId]
        );
    }

    /**
     * Guarda el resultado del envio: que lineas fueron y a que pedido.
     *
     * @param array<int,int> $itemIds
     */
    public static function markSent(array $itemIds, int $pedidoIdirecto, string $fecha): int
    {
        if ($itemIds === []) {
            return 0;
        }

        $placeholders = [];
        $params = ['pedido' => $pedidoIdirecto, 'fecha' => $fecha];
        foreach (array_values($itemIds) as $i => $id) {
            $placeholders[] = ':i' . $i;
            $params['i' . $i] = (int) $id;
        }

        return Database::execute(
            'UPDATE mt_order_items SET sent_at = :fecha, idirecto_pedido_id = :pedido
             WHERE id IN (' . implode(',', $placeholders) . ')',
            $params
        );
    }

    /** Resumen de lineas de un pedido (totales, enviadas y pendientes). */
    public static function summary(array $items): array
    {
        $summary = [
            'lines'            => count($items),
            'lines_sendable'   => 0,
            'lines_sent'       => 0,
            'lines_pending'    => 0,
            'lines_own'        => 0,
            'amount_sendable'  => 0.0,
            'amount_sent'      => 0.0,
        ];

        foreach ($items as $item) {
            $subtotal = (float) $item['qty'] * (float) $item['price_customer'];
            $isCatalog = (string) $item['source'] === self::SOURCE_CATALOG;

            if (!$isCatalog) {
                $summary['lines_own']++;
                continue;
            }

            if (!empty($item['sent_at'])) {
                $summary['lines_sent']++;
                $summary['amount_sent'] += $subtotal;
                continue;
            }

            if ((int) $item['product_id'] > 0) {
                $summary['lines_sendable']++;
                $summary['amount_sendable'] += $subtotal;
            }
        }

        $summary['lines_pending'] = $summary['lines_sendable'];

        return $summary;
    }

    /**
     * Normaliza las lineas recibidas del formulario.
     *
     * @param array<int,array<string,mixed>> $raw
     * @return array{lines:array<int,array>, errors:array<int,string>}
     */
    public static function normalizeLines(array $raw): array
    {
        $lines = [];
        $errors = [];
        $lineNo = 0;

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $productId = (int) ($row['product_id'] ?? 0);

            // Fila vacia (el editor anade una al final): se ignora sin error.
            if ($name === '' && $productId <= 0) {
                continue;
            }

            $lineNo++;
            $source = (string) ($row['source'] ?? self::SOURCE_CATALOG);
            if (!in_array($source, [self::SOURCE_CATALOG, self::SOURCE_OWN], true)) {
                $source = self::SOURCE_CATALOG;
            }

            $qty = (int) ($row['qty'] ?? 1);
            if ($qty < 1) {
                $errors[] = 'Linea ' . $lineNo . ': la cantidad debe ser 1 o mas.';
                $qty = 1;
            }
            $qty = min($qty, 9999);

            if ($name === '') {
                $errors[] = 'Linea ' . $lineNo . ': falta el nombre del producto.';
                continue;
            }

            $price = (float) str_replace(',', '.', (string) ($row['price_customer'] ?? '0'));
            if ($price < 0) {
                $errors[] = 'Linea ' . $lineNo . ': el precio no puede ser negativo.';
                $price = 0.0;
            }

            $tax = (float) str_replace(',', '.', (string) ($row['tax_rate'] ?? '21'));
            if ($tax < 0 || $tax > 100) {
                $tax = 21.0;
            }

            $lines[] = [
                'source'         => $source,
                'product_id'     => $productId > 0 ? $productId : null,
                'sku'            => mb_substr(trim((string) ($row['sku'] ?? '')), 0, 60) ?: null,
                'name'           => mb_substr($name, 0, 200),
                'qty'            => $qty,
                'price_customer' => round($price, 2),
                'tax_rate'       => round($tax, 2),
                // Se rellenan al consultar la tarifa del mayorista.
                'price_idirecto' => null,
                'cost_idirecto'  => null,
            ];
        }

        return ['lines' => $lines, 'errors' => $errors];
    }
}
