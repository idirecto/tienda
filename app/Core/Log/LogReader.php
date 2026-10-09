<?php

declare(strict_types=1);

namespace Tienda\Core\Log;

use Tienda\Core\Config;
use Tienda\Core\Logger;

/**
 * Lee los logs que escribe `Logger` (ficheros por canal, dia y tienda).
 *
 * No hay base de datos de por medio: se leen los ficheros
 * `storage/logs/<canal>/<AAAA-MM-DD>/<tienda>.log` y se devuelven las entradas
 * ordenadas de la mas reciente a la mas antigua.
 *
 * Para que el visor sea rapido y no se coma la memoria:
 *
 *  - se lee como mucho `log.read_max_bytes` de cada fichero (desde el final, que
 *    es donde esta lo ultimo que ha pasado);
 *  - se reunen como mucho `log.read_max_entries` entradas;
 *  - si no se pide fecha, se asume HOY (y "todo" se limita a `MAX_SCAN_DAYS`).
 */
final class LogReader
{
    /** Tope de dias que se recorren cuando se pide buscar en todo el historico. */
    public const MAX_SCAN_DAYS = 31;

    private string $base;

    public function __construct(?string $base = null)
    {
        $this->base = rtrim(
            $base ?? (string) Config::get('log.path', TIENDA_BASE . '/storage/logs'),
            '/\\'
        );
    }

    public static function make(): self
    {
        return new self();
    }

    public function base(): string
    {
        return $this->base;
    }

    // -------------------------------------------------------------------------
    // INVENTARIO
    // -------------------------------------------------------------------------

    /** Dias con algun log, del mas reciente al mas antiguo. */
    public function days(int $limit = 0): array
    {
        $days = [];
        foreach (glob($this->base . '/*/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $day = basename($dir);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
                $days[$day] = true;
            }
        }
        $days = array_keys($days);
        rsort($days);

        return $limit > 0 ? array_slice($days, 0, $limit) : $days;
    }

    /** Canales presentes en el log. */
    public function channels(): array
    {
        $channels = [];
        foreach (glob($this->base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if ($name !== '' && $name[0] !== '.') {
                $channels[$name] = true;
            }
        }
        $channels = array_keys($channels);
        sort($channels);

        return $channels;
    }

    /**
     * Tiendas con log (en un dia concreto o en todo el historico).
     *
     * @return list<array{store_id:?int,store:string,files:int}>
     */
    public function stores(?string $day = null): array
    {
        $filterDay = ($day !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) ? $day : '*';
        $found = [];

        foreach (glob($this->base . '/*/' . $filterDay . '/*.log') ?: [] as $file) {
            [$storeId, $slug] = self::parseStore(basename($file, '.log'));
            $key = $storeId ?? 0;
            if (!isset($found[$key])) {
                $found[$key] = ['store_id' => $storeId, 'store' => $slug, 'files' => 0];
            }
            $found[$key]['files']++;
        }

        return array_values($found);
    }

    // -------------------------------------------------------------------------
    // CONSULTA
    // -------------------------------------------------------------------------

    /**
     * Entradas que cumplen los filtros, de la mas reciente a la mas antigua.
     *
     * Filtros admitidos: `day`, `from`, `to` (AAAA-MM-DD), `channel`,
     * `store_id`, `level` (nivel MINIMO) y `q` (texto libre).
     *
     * @return array{entries:list<array<string,mixed>>,total:int}
     */
    public function search(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $all = $this->collect($this->normalize($filters));

        return [
            'entries' => array_slice($all, max(0, $offset), max(1, $limit)),
            'total'   => count($all),
        ];
    }

    /** Cuantas entradas hay de cada nivel con esos filtros. */
    public function countByLevel(array $filters = []): array
    {
        $counts = array_fill_keys(array_keys(Logger::LEVELS), 0);

        foreach ($this->collect($this->normalize($filters)) as $entry) {
            $level = strtolower((string) ($entry['level'] ?? ''));
            if (isset($counts[$level])) {
                $counts[$level]++;
            }
        }

        return $counts;
    }

    /**
     * Resumen para el panel: incidencias (aviso, error y critico) de una tienda.
     *
     * @return array{warning:int,error:int,critical:int,total:int}
     */
    public function health(?int $storeId = null, ?string $day = null): array
    {
        $counts = $this->countByLevel([
            'store_id' => $storeId,
            'day'      => $day ?? date('Y-m-d'),
            'level'    => 'warning',
        ]);

        $warning = (int) ($counts['warning'] ?? 0);
        $error = (int) ($counts['error'] ?? 0);
        $critical = (int) ($counts['critical'] ?? 0);

        return [
            'warning'  => $warning,
            'error'    => $error,
            'critical' => $critical,
            'total'    => $warning + $error + $critical,
        ];
    }

    // -------------------------------------------------------------------------
    // INTERNOS
    // -------------------------------------------------------------------------

    private function normalize(array $filters): array
    {
        $day = self::cleanDay($filters['day'] ?? null);
        $from = self::cleanDay($filters['from'] ?? null);
        $to = self::cleanDay($filters['to'] ?? null);

        // Sin fecha no se recorre todo el historico: se asume hoy.
        if ($day === null && $from === null && $to === null) {
            $day = date('Y-m-d');
        }

        $channel = strtolower(trim((string) ($filters['channel'] ?? '')));
        if ($channel === 'todos') {
            $channel = '';
        }
        $channel = preg_replace('/[^a-z0-9_-]+/', '-', $channel) ?? '';
        $channel = trim($channel, '-');

        $level = strtolower(trim((string) ($filters['level'] ?? '')));
        if (!isset(Logger::LEVELS[$level])) {
            $level = '';
        }

        $storeId = null;
        if (isset($filters['store_id']) && $filters['store_id'] !== null && $filters['store_id'] !== '') {
            $storeId = (int) $filters['store_id'];
            if ($storeId <= 0) {
                $storeId = null;
            }
        }

        $q = mb_strtolower(mb_substr(trim((string) ($filters['q'] ?? '')), 0, 120));

        return [
            'day'      => $day,
            'from'     => $from,
            'to'       => $to,
            'channel'  => $channel,
            'level'    => $level,
            'store_id' => $storeId,
            'q'        => $q,
        ];
    }

    private function dayList(array $f): array
    {
        if ($f['day'] !== null) {
            return [$f['day']];
        }

        $days = $this->days();
        if ($f['from'] !== null || $f['to'] !== null) {
            $days = array_values(array_filter($days, static function (string $day) use ($f): bool {
                return ($f['from'] === null || $day >= $f['from'])
                    && ($f['to'] === null || $day <= $f['to']);
            }));
        }

        return array_slice($days, 0, self::MAX_SCAN_DAYS);
    }

    /** @return list<array<string,mixed>> */
    private function collect(array $f): array
    {
        $maxEntries = max(100, (int) Config::get('log.read_max_entries', 20000));
        $maxBytes = max(1024, (int) Config::get('log.read_max_bytes', 5242880));
        $min = $f['level'] !== '' ? Logger::LEVELS[$f['level']] : 0;

        $channels = $f['channel'] !== '' ? [$f['channel']] : $this->channels();
        $entries = [];

        foreach ($this->dayList($f) as $day) {
            foreach ($channels as $channel) {
                $files = glob($this->base . '/' . $channel . '/' . $day . '/*.log') ?: [];
                sort($files);

                foreach ($files as $file) {
                    [$storeId, $slug] = self::parseStore(basename($file, '.log'));
                    if ($f['store_id'] !== null && $storeId !== $f['store_id']) {
                        continue;
                    }

                    foreach ($this->readFile($file, $maxBytes) as $line) {
                        $entry = json_decode($line, true);
                        if (!is_array($entry)) {
                            continue;
                        }

                        $entryLevel = strtolower((string) ($entry['level'] ?? 'info'));
                        if ($min > 0 && (Logger::LEVELS[$entryLevel] ?? 0) < $min) {
                            continue;
                        }
                        if ($f['q'] !== '' && !self::matches($entry, $f['q'])) {
                            continue;
                        }

                        $entry['_day'] = $day;
                        $entry['_channel'] = $channel;
                        $entry['_store_id'] = $storeId;
                        $entry['_store'] = $slug;
                        $entry['_file'] = ltrim(str_replace($this->base, '', $file), '/\\');
                        $entries[] = $entry;

                        if (count($entries) >= $maxEntries) {
                            break 4;
                        }
                    }
                }
            }
        }

        usort(
            $entries,
            static fn (array $a, array $b): int => strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? ''))
        );

        return $entries;
    }

    /** @return list<string> */
    private function readFile(string $path, int $maxBytes): array
    {
        $size = @filesize($path);
        if ($size === false || $size <= 0) {
            return [];
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            if ($size > $maxBytes) {
                fseek($handle, $size - $maxBytes);
                fgets($handle); // descarta la primera linea, que puede venir cortada
            }

            $lines = [];
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }

            return $lines;
        } finally {
            fclose($handle);
        }
    }

    private static function matches(array $entry, string $needle): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) ($entry['message'] ?? ''),
            (string) ($entry['_store'] ?? ''),
            (string) ($entry['http']['url'] ?? ''),
            json_encode($entry['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '',
        ], static fn (string $v): bool => $v !== '')));

        return str_contains($haystack, $needle);
    }

    /**
     * Separa el nombre del fichero (`<id>_<slug>.log`) en tienda y slug.
     *
     * @return array{0:?int,1:string}
     */
    private static function parseStore(string $name): array
    {
        if ($name === '') {
            return [null, 'desconocida'];
        }
        if ($name[0] === '_') {
            return [null, 'plataforma'];
        }
        if (preg_match('/^(\d+)_?(.*)$/', $name, $m) === 1) {
            return [(int) $m[1], $m[2] !== '' ? $m[2] : 'tienda'];
        }

        return [null, $name];
    }

    private static function cleanDay(mixed $day): ?string
    {
        if (!is_string($day)) {
            return null;
        }
        $day = trim($day);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 ? $day : null;
    }
}
