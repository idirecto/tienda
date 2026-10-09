<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Logger;
use Tienda\Core\Log\LogReader;
use Tienda\Core\Session;
use Tienda\Models\Store;

/**
 * Visor de logs del panel: `/panel/logs`.
 *
 * Cada tienda ve **solo sus propios logs** (el `store_id` sale siempre de la
 * sesion, nunca del formulario). El rol `platform` puede ver todas las tiendas y
 * filtrar por una concreta.
 *
 * No hay tabla de logs: se leen los ficheros que escribe `Logger`
 * (`storage/logs/<canal>/<dia>/<tienda>.log`) mediante `LogReader`.
 */
final class LogController extends Controller
{
    private const PER_PAGE = 50;

    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();
        $isPlatform = Auth::isPlatform();

        // La limpieza de dias antiguos se aprovecha al abrir el visor (como
        // mucho una vez al dia: lo controla la marca `log.gc_interval`).
        Logger::gcIfDue();

        // ---- Fecha: hoy | una fecha | todos (ultimos LogReader::MAX_SCAN_DAYS dias)
        $fecha = (string) $this->input('fecha', date('Y-m-d'));
        $day = null;
        $from = null;
        if ($fecha === 'todos') {
            $from = '1970-01-01';
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1) {
            $day = $fecha;
        } else {
            $day = date('Y-m-d');
        }

        // ---- Canal y nivel
        $channel = (string) $this->input('canal', '');
        $channel = preg_replace('/[^a-z0-9_-]+/', '-', strtolower($channel)) ?? '';
        $channel = $channel === 'todos' ? '' : trim($channel, '-');

        $level = strtolower((string) $this->input('nivel', ''));
        if (!isset(Logger::LEVELS[$level])) {
            $level = '';
        }

        $q = mb_substr(trim((string) $this->input('q', '')), 0, 120);
        $page = max(1, (int) $this->input('pagina', 1));

        // ---- AISLAMIENTO: sin plataforma, la tienda SIEMPRE es la de la sesion.
        $scope = $storeId;
        $tienda = (string) $storeId;
        if ($isPlatform) {
            $tienda = (string) $this->input('tienda', 'todas');
            if ($tienda === 'todas') {
                $scope = null;
            } elseif (ctype_digit($tienda)) {
                $scope = (int) $tienda;
            } else {
                $tienda = 'todas';
                $scope = null;
            }
        }

        $reader = LogReader::make();
        $filters = [
            'day'      => $day,
            'from'     => $from,
            'channel'  => $channel,
            'level'    => $level,
            'store_id' => $scope,
            'q'        => $q,
        ];

        $result = $reader->search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $counts = $reader->countByLevel($filters);

        return $this->view('panel/logs', [
            'pageTitle'     => 'Logs',
            'entries'       => $result['entries'],
            'total'         => $result['total'],
            'counts'        => $counts,
            'page'          => $page,
            'perPage'       => self::PER_PAGE,
            'days'          => $reader->days(60),
            'usedChannels'  => $reader->channels(),
            'levelLabels'   => (array) config('log.levels', []),
            'channelLabels' => (array) config('log.channels', []),
            'filters'       => ['fecha' => $day ?? 'todos', 'canal' => $channel, 'nivel' => $level, 'q' => $q],
            'isPlatform'    => $isPlatform,
            'tienda'        => $tienda,
            'stores'        => $isPlatform ? Store::allWithPlan() : [],
            'retention'     => (int) config('log.retention_days', 30),
            'maxScanDays'   => LogReader::MAX_SCAN_DAYS,
            'ubicacion'     => $this->ubicacion($scope, $day, $channel, $isPlatform),
        ], 'panel');
    }

    /** Limpieza manual de los logs viejos: solo la plataforma. */
    public function purge(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();

        if (!Auth::isPlatform()) {
            Session::flash('error', 'Solo la plataforma puede limpiar los logs.');
            $this->redirect('panel/logs');
        }

        $deleted = Logger::gc(true);
        Logger::notice('sistema', 'Limpieza manual de logs', [
            'borrados' => $deleted,
            'por'      => (string) (Auth::user()['email'] ?? ''),
        ]);

        Session::flash('success', 'Limpieza hecha: ' . $deleted . ' fichero(s) antiguo(s) borrado(s).');
        $this->redirect('panel/logs');
    }

    /** Id de tienda del usuario autenticado (la sesion manda sobre el host). */
    private function storeId(): int
    {
        $id = Auth::storeId();
        if ($id === null || $id <= 0) {
            $this->redirect('panel/logout');
        }

        return $id;
    }

    /** Ruta (informativa) de los ficheros que se estan viendo, para SSH. */
    private function ubicacion(?int $scope, ?string $day, string $channel, bool $isPlatform): string
    {
        $path = 'storage/logs/' . ($channel !== '' ? $channel : '*');
        $path .= '/' . ($day !== null ? $day : '*');
        if ($scope !== null) {
            $path .= '/' . $scope . '_<slug>.log';
        } else {
            $path .= $isPlatform ? '/*.log' : '/_plataforma.log';
        }

        return $path;
    }
}
