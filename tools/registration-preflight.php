<?php

declare(strict_types=1);

/**
 * Comprobacion previa del alta controlada.
 *
 *   php tools/registration-preflight.php --phase=predeploy \
 *       --base-url=https://ecom-era.com --web-user=www-data
 *
 * Solo lee configuracion, base de datos, permisos y respuestas HTTP. No hace
 * INSERT/UPDATE/DELETE, no crea tiendas y no llama a la API de correo.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use Tienda\Core\ClientIp;
use Tienda\Core\Config;
use Tienda\Core\Contract\ContractPdfGenerator;
use Tienda\Core\Contract\ContractTemplate;
use Tienda\Core\Database;
use Tienda\Core\Env;
use Tienda\Core\Logger;
use Tienda\Core\Registration;
use Tienda\Models\ContractVersion;

Logger::setEnabled(false);

$phase = (string) preflightOption($argv, 'phase', 'predeploy');
$baseUrl = rtrim((string) preflightOption($argv, 'base-url', (string) Config::get('app.url', '')), '/');
$webUser = (string) preflightOption($argv, 'web-user', '');
$skipHttp = preflightFlag($argv, 'skip-http');
$noPdfSmoke = preflightFlag($argv, 'no-pdf-smoke');
$allowOpenGuard = preflightFlag($argv, 'allow-open-guard');
$strict = preflightFlag($argv, 'strict');

if (preflightFlag($argv, 'help')
    || !in_array($phase, ['predeploy', 'go-live'], true)) {
    preflightHelp();
    exit($phase === 'predeploy' || $phase === 'go-live' ? 0 : 2);
}

$state = ['ok' => 0, 'warn' => 0, 'fail' => 0];

echo "============================================================\n";
echo " PREFLIGHT - ALTA CON CONTRATO Y VERIFICACION\n";
echo "============================================================\n";
echo ' Fase:        ' . $phase . "\n";
echo ' APP_ENV:     ' . (string) Config::get('app.env', 'local') . "\n";
echo ' Base URL:    ' . ($baseUrl !== '' ? $baseUrl : '(sin definir)') . "\n";
echo ' Modo:        solo lectura, sin datos y sin correo real' . "\n\n";

// -----------------------------------------------------------------------------
// 1. ENTORNO Y .ENV
// -----------------------------------------------------------------------------
preflightSection('Entorno y .env');
$envFile = TIENDA_BASE . '/.env';
preflightCheck(is_file($envFile) && is_readable($envFile), '.env existe y es legible', true);
if (is_file($envFile)) {
    $mode = fileperms($envFile) & 0777;
    preflightCheck(($mode & 0002) === 0, '.env no es escribible por otros', true);
    preflightCheck(($mode & 0004) === 0, '.env no es legible por otros', false);
}
preflightCheck(
    ($mode ?? 0) === 0 || (($mode ?? 0) & 0007) === 0,
    '.env no tiene permisos abiertos al resto de usuarios',
    false
);

$envName = strtolower((string) Config::get('app.env', 'local'));
if ($envName !== 'production') {
    preflightWarning('APP_ENV no es production; este preflight no certifica produccion');
} else {
    preflightOk('APP_ENV=production');
}

if ($baseUrl === '') {
    preflightFail('APP_URL/--base-url no esta definido');
} elseif ($envName === 'production' && !str_starts_with(strtolower($baseUrl), 'https://')) {
    preflightFail('en produccion la URL publica debe ser HTTPS');
} else {
    preflightOk('URL publica con esquema correcto');
}

$requiredEnv = [
    'APP_URL'                          => 'URL publica',
    'EMAIL_API_ENABLED'                => 'interruptor de API de correo',
    'API_EMAIL'                        => 'URL de API de correo',
    'EMAIL_USER'                       => 'usuario de API de correo',
    'EMAIL_PASS'                       => 'contrasena de API de correo',
    'EMAIL_FROM'                       => 'remitente',
    'EMAIL_FROM_NAME'                  => 'nombre del remitente',
    'REGISTRATION_COMPLETION_ENABLED'  => 'activacion del alta',
    'REGISTRATION_MAIL_TRANSPORT'      => 'transporte de correo',
];
foreach ($requiredEnv as $key => $label) {
    $present = Env::get($key) !== null;
    if (in_array($key, ['EMAIL_USER', 'EMAIL_PASS'], true)) {
        preflightCheck($present, $key . ' definida (' . $label . ')', true);
    } else {
        preflightCheck($present, $key . ' definida (' . $label . ')', true);
    }
}

// Estas dos tienen un valor por defecto seguro, pero conviene fijarlas a mano.
preflightCheck(
    Env::get('REGISTRATION_LEGACY_GUARD_ENABLED') !== null,
    'REGISTRATION_LEGACY_GUARD_ENABLED explicita en .env',
    false
);

// -----------------------------------------------------------------------------
// 2. CONTRATO Y PDF
// -----------------------------------------------------------------------------
preflightSection('Contrato y motor PDF');
$contract = ContractVersion::current(
    (string) Config::get('registration.contract.document_key', 'access_web')
);
preflightCheck($contract !== null, 'hay una version contractual vigente y firmada', true);
if ($contract !== null) {
    preflightOk('contrato ' . (string) $contract['version_label']
        . ' vigente desde ' . (string) $contract['effective_date']);
    preflightOk('hash de plantilla aprobada: ' . (string) $contract['template_sha256']);
}

$template = new ContractTemplate();
$contractProblems = $template->configurationProblems();
preflightCheck(
    $contractProblems === [],
    'valores legales completos (soporte, contacto, plazos y responsabilidad)',
    true
);
if ($contractProblems !== []) {
    preflightWarning('faltan: ' . implode(', ', $contractProblems));
}

if ($contract !== null && $contractProblems === []) {
    try {
        $rendered = $template->render($contract, [
            'legal_name'     => 'CLIENTE DE PRUEBA PREFLIGHT S.L.',
            'tax_id'         => 'B00000000',
            'address'        => 'Calle de Prueba 1, 50001 Zaragoza',
            'representative' => 'Persona de Prueba',
            'contact'        => 'preflight@example.test',
            'email'          => 'preflight@example.test',
        ]);
        preflightOk('plantilla HTML renderiza y no conserva marcadores');
        preflightOk('hash del HTML fuente: ' . $rendered['template_sha256']);
    } catch (Throwable $e) {
        preflightFail('la plantilla no renderiza: ' . $e->getMessage());
    }
}

preflightCheck(ContractPdfGenerator::isAvailable(), 'php-tcpdf disponible', true);
if (!$noPdfSmoke && ContractPdfGenerator::isAvailable()) {
    $tmp = tempnam(sys_get_temp_dir(), 'tienda-preflight-');
    $tmpPdf = $tmp !== false ? $tmp . '.pdf' : '';
    if ($tmp !== false) {
        @unlink($tmp);
    }
    try {
        if ($tmpPdf === '') {
            throw new RuntimeException('no se pudo crear un temporal');
        }
        (new ContractPdfGenerator())->generate(
            '<p>Preflight de contrato. Sin datos reales.</p>',
            $tmpPdf
        );
        $signature = (string) file_get_contents($tmpPdf, false, null, 0, 5);
        $size = is_file($tmpPdf) ? (int) filesize($tmpPdf) : 0;
        preflightCheck($signature === '%PDF-' && $size > 100, 'prueba de generacion PDF temporal', true);
    } catch (Throwable $e) {
        preflightFail('fallo la generacion PDF temporal: ' . $e->getMessage());
    } finally {
        if ($tmpPdf !== '') {
            @unlink($tmpPdf);
        }
    }
}

// -----------------------------------------------------------------------------
// 3. BASE DE DATOS Y MIGRACION 015
// -----------------------------------------------------------------------------
preflightSection('Base de datos y migracion 015');
try {
    Database::scalar('SELECT 1');
    preflightOk('conexion a la base de datos');
} catch (Throwable $e) {
    preflightFail('no se puede conectar a la base de datos');
}

foreach (['mt_contract_versions', 'mt_store_registrations', 'mt_contract_acceptances', 'mt_registration_verifications'] as $table) {
    preflightCheck(Database::tableExists($table), 'tabla ' . $table, true);
}

preflightCheck(Registration::onboardingSchemaReady(), 'columnas de evidencia del alta (migracion 015)', true);
if (Database::tableExists('mt_migrations')) {
    $applied = (int) (Database::scalar(
        "SELECT COUNT(*) FROM mt_migrations WHERE filename = '015_registration_acceptance_evidence.sql'"
    ) ?? 0);
    preflightCheck($applied > 0, 'migracion 015 registrada en mt_migrations', false);
}
if (Database::tableExists('mt_stores')) {
    $unique = (int) (Database::scalar(
        "SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'mt_stores'
            AND INDEX_NAME = 'uq_mt_stores_id_tienda_idirecto'"
    ) ?? 0);
    preflightCheck($unique > 0, 'indice unico cuenta->tienda (migracion 014)', false);
}
try {
    $pending = (int) (Database::scalar(
        "SELECT COUNT(*) FROM mt_store_registrations WHERE status = 'pending_verification'"
    ) ?? 0);
    $acceptances = (int) (Database::scalar('SELECT COUNT(*) FROM mt_contract_acceptances') ?? 0);
    preflightOk('altas pendientes actuales: ' . $pending . '; aceptaciones: ' . $acceptances);
} catch (Throwable $e) {
    preflightWarning('no se pudieron contar altas/aceptaciones: ' . get_class($e));
}

// -----------------------------------------------------------------------------
// 4. STORAGE PRIVADO Y PERMISOS
// -----------------------------------------------------------------------------
preflightSection('Storage privado y permisos');
$contractsDir = realpath(TIENDA_BASE . '/storage/contracts');
$templatesDir = realpath(TIENDA_BASE . '/storage/contracts/templates');
$generatedDir = realpath(TIENDA_BASE . '/storage/contracts/generated');
$outboxDir = realpath(TIENDA_BASE . '/storage/contracts/outbox');
$publicDir = realpath(TIENDA_BASE . '/public');

foreach ([
    'storage/contracts'          => $contractsDir,
    'storage/contracts/templates'=> $templatesDir,
    'storage/contracts/generated'=> $generatedDir,
    'storage/contracts/outbox'   => $outboxDir,
] as $label => $path) {
    preflightCheck($path !== false && is_dir($path), $label . ' existe', true);
    if ($path !== false && $publicDir !== false) {
        preflightCheck(
            !str_starts_with($path . DIRECTORY_SEPARATOR, $publicDir . DIRECTORY_SEPARATOR),
            $label . ' queda fuera de public/',
            true
        );
    }
    if ($path !== false && is_dir($path)) {
        $mode = fileperms($path) & 0777;
        preflightCheck(($mode & 0002) === 0, $label . ' no es escribible por otros (modo ' . sprintf('%04o', $mode) . ')', true);
        if (($mode & 0004) !== 0) {
            preflightWarning($label . ' es legible por otros (modo ' . sprintf('%04o', $mode) . '); revisar ACL/permisos');
        }
    }
}

preflightCheck(
    $templatesDir !== false && is_readable($templatesDir . '/Contrato_web_clientes_v1.html'),
    'plantilla HTML privada legible',
    true
);
preflightCheck(
    $templatesDir !== false && is_readable($templatesDir . '/Contrato_web_clientes_v1.pdf'),
    'PDF aprobado legible',
    true
);
preflightCheck($generatedDir !== false && is_writable($generatedDir), 'generated/ escribible por el proceso actual', true);
preflightCheck($outboxDir !== false && is_writable($outboxDir), 'outbox/ escribible por el proceso actual', true);

foreach ([
    TIENDA_BASE . '/storage/contracts/.htaccess',
    TIENDA_BASE . '/storage/contracts/generated/.htaccess',
    TIENDA_BASE . '/storage/contracts/outbox/.htaccess',
] as $htaccess) {
    preflightCheck(is_file($htaccess), 'existe ' . str_replace(TIENDA_BASE . '/', '', $htaccess), true);
}

if ($webUser !== '') {
    if (!function_exists('posix_getpwnam')) {
        preflightWarning('no se puede validar --web-user sin extension POSIX');
    } elseif (posix_getpwnam($webUser) === false) {
        preflightFail('el usuario web ' . $webUser . ' no existe');
    } else {
        $current = function_exists('posix_getpwuid')
            ? (string) (posix_getpwuid(posix_geteuid())['name'] ?? '')
            : '';
        if ($current !== $webUser) {
            preflightWarning(
                'ejecuta este preflight como ' . $webUser
                . ' (ahora: ' . ($current !== '' ? $current : 'desconocido')
                . ') para validar permisos y ACL reales'
            );
        } else {
            preflightOk('ejecutando como el usuario web ' . $webUser);
        }
    }
}

// -----------------------------------------------------------------------------
// 5. BLOQUEO WEB
// -----------------------------------------------------------------------------
preflightSection('Bloqueo web de storage');
$rootHtaccess = (string) @file_get_contents(TIENDA_BASE . '/.htaccess');
$nginxTemplate = (string) @file_get_contents(TIENDA_BASE . '/deploy/nginx-site.conf.tpl');
preflightCheck(str_contains($rootHtaccess, 'storage'), '.htaccess raiz bloquea storage', true);
preflightCheck(str_contains($nginxTemplate, 'storage'), 'plantilla nginx bloquea storage', true);

if ($skipHttp) {
    preflightWarning('comprobacion HTTP omitida con --skip-http');
} elseif ($baseUrl === '') {
    preflightFail('no se puede comprobar el bloqueo web sin --base-url/APP_URL');
} else {
    $protectedUrls = [
        '/.env',
        '/.env.example',
        '/storage/contracts/',
        '/storage/contracts/templates/Contrato_web_clientes_v1.html',
        '/storage/contracts/generated/',
        '/storage/contracts/outbox/',
        '/storage/logs/',
    ];
    foreach ($protectedUrls as $path) {
        $status = preflightHttpStatus($baseUrl . $path);
        if ($status === 0) {
            preflightFail('sin respuesta HTTP para ' . $path . ' (no se puede certificar el bloqueo)');
            continue;
        }
        preflightCheck(
            in_array($status, [401, 403, 404], true),
            'protegida por web: ' . $path . ' -> ' . $status,
            true
        );
    }

    $registroStatus = preflightHttpStatus($baseUrl . '/registro');
    if ($registroStatus === 200) {
        preflightOk('/registro responde 200');
    } else {
        preflightWarning('/registro devuelve ' . ($registroStatus !== 0 ? (string) $registroStatus : 'sin respuesta'));
    }
}

// -----------------------------------------------------------------------------
// 6. PROXIES DE CONFIANZA
// -----------------------------------------------------------------------------
preflightSection('Proxies de confianza para la IP');
$trustedProxies = (array) Config::get('app.trusted_proxies', []);
$invalidProxies = ClientIp::invalidRules($trustedProxies);
preflightCheck($invalidProxies === [], 'TRUSTED_PROXIES contiene solo IP/CIDR validos', true);
if ($invalidProxies !== []) {
    preflightWarning('reglas invalidas: ' . implode(', ', $invalidProxies));
}
if ($trustedProxies === []) {
    if (getenv('TRUSTED_PROXIES') === false) {
        preflightWarning('TRUSTED_PROXIES no esta definida; se asume que no hay proxy');
    } else {
        preflightWarning('TRUSTED_PROXIES vacio: solo se usara REMOTE_ADDR (correcto sin proxy)');
    }
} else {
    preflightOk('proxies declarados: ' . implode(', ', $trustedProxies));
}
preflightCheck(
    ClientIp::resolve('10.0.0.1', '203.0.113.7', '', ['10.0.0.0/8']) === '203.0.113.7',
    'resolver acepta X-Forwarded-For de un proxy declarado',
    true
);
preflightCheck(
    ClientIp::resolve('198.51.100.1', '203.0.113.7', '', ['10.0.0.0/8']) === '198.51.100.1',
    'resolver ignora X-Forwarded-For de un origen no declarado',
    true
);

// -----------------------------------------------------------------------------
// 7. CORREO (SIN ENVIAR)
// -----------------------------------------------------------------------------
preflightSection('Correo (solo configuracion)');
$transport = strtolower(trim((string) Config::get('registration.mail_transport', 'api')));
preflightCheck(
    $transport === 'api' || $envName !== 'production',
    'transporte de correo apto para produccion (api)',
    true
);
if ($transport === 'file') {
    preflightWarning('REGISTRATION_MAIL_TRANSPORT=file es solo para pruebas locales');
}
preflightCheck((bool) Config::get('email.api.enabled', false), 'EMAIL_API_ENABLED=true', true);
$apiUrl = (string) Config::get('email.api.base_url', '');
$apiScheme = strtolower((string) parse_url($apiUrl, PHP_URL_SCHEME));
if ($envName === 'production') {
    preflightCheck($apiScheme === 'https', 'API_EMAIL usa HTTPS en produccion', true);
} else {
    preflightCheck(in_array($apiScheme, ['http', 'https'], true), 'API_EMAIL tiene esquema http(s)', true);
}
preflightCheck((string) Config::get('email.api.username', '') !== '', 'EMAIL_USER definido', true);
preflightCheck((string) Config::get('email.api.password', '') !== '', 'EMAIL_PASS definido', true);
preflightCheck((string) Config::get('email.api.endpoint', '') === 'send-email-adjunto', 'endpoint send-email-adjunto', true);
$from = trim((string) Config::get('email.from', ''));
preflightCheck($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL) !== false, 'EMAIL_FROM valido', true);
preflightCheck(trim((string) Config::get('email.from_name', '')) !== '', 'EMAIL_FROM_NAME definido', true);
$replyTo = trim((string) Config::get('email.reply_to', ''));
if ($replyTo !== '') {
    preflightCheck(filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false, 'EMAIL_REPLY_TO valido', false);
}
preflightCheck(function_exists('curl_init'), 'extension curl disponible', true);
preflightOk('no se ha llamado a la API de correo');

// -----------------------------------------------------------------------------
// 8. FASE Y GUARDA
// -----------------------------------------------------------------------------
preflightSection('Fase y guarda de activacion');
$guardOpen = Registration::legacyGuardEnabled();
$completion = Registration::completionEnabled();

if ($phase === 'predeploy') {
    preflightCheck($guardOpen, 'guarda cerrada en predeploy (no se crea ninguna tienda)', true);
    if ($guardOpen) {
        preflightOk('REGISTRATION_LEGACY_GUARD_ENABLED=true');
    }
    if (!$completion) {
        preflightWarning('REGISTRATION_COMPLETION_ENABLED=false; el alta no estara lista');
    } else {
        preflightOk('REGISTRATION_COMPLETION_ENABLED=true');
    }
} else {
    preflightCheck($allowOpenGuard, 'go-live exige --allow-open-guard explicito', true);
    preflightCheck(!$guardOpen, 'guarda abierta para la prueba autorizada', true);
    preflightCheck($completion, 'activacion habilitada para go-live', true);
}

if (Registration::isOpen()) {
    preflightOk('condiciones generales de /registro abiertas');
} elseif ($phase === 'go-live') {
    preflightFail('Registration::isOpen() es false; /registro no aceptaria altas');
} else {
    preflightWarning('Registration::isOpen() es false; /registro permanece cerrado');
}

// -----------------------------------------------------------------------------
// RESULTADO
// -----------------------------------------------------------------------------
echo "\n============================================================\n";
echo ' RESULTADO: ' . $state['ok'] . ' OK, ' . $state['warn'] . ' avisos, ' . $state['fail'] . " fallos\n";
echo "============================================================\n";
echo " No se ha creado ninguna tienda, aceptacion ni codigo.\n";
echo " No se ha enviado ningun correo.\n";

if ($state['fail'] > 0 || ($strict && $state['warn'] > 0)) {
    exit(1);
}
exit(0);

// =============================================================================
// HELPERS
// =============================================================================

function preflightSection(string $title): void
{
    echo "\n== " . $title . " ==\n";
}

function preflightOk(string $message): void
{
    global $state;
    $state['ok']++;
    echo "  OK    " . $message . "\n";
}

function preflightWarning(string $message): void
{
    global $state;
    $state['warn']++;
    echo "  AVISO " . $message . "\n";
}

function preflightFail(string $message): void
{
    global $state;
    $state['fail']++;
    echo "  FALLO " . $message . "\n";
}

function preflightCheck(bool $condition, string $message, bool $fail = false): void
{
    if ($condition) {
        preflightOk($message);
    } elseif ($fail) {
        preflightFail($message);
    } else {
        preflightWarning($message);
    }
}

function preflightOption(array $argv, string $name, ?string $default = null): ?string
{
    foreach ($argv as $arg) {
        if ($arg === '--' . $name) {
            return '1';
        }
        if (str_starts_with($arg, '--' . $name . '=')) {
            return substr($arg, strlen($name) + 3);
        }
    }

    return $default;
}

function preflightFlag(array $argv, string $name): bool
{
    foreach ($argv as $arg) {
        if ($arg === '--' . $name) {
            return true;
        }
        if ($arg === '--' . $name . '=1' || $arg === '--' . $name . '=true') {
            return true;
        }
    }

    return false;
}

function preflightHttpStatus(string $url): int
{
    if (!function_exists('curl_init')) {
        return 0;
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return 0;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'IDirecto-Registration-Preflight/1.0',
        CURLOPT_HTTPHEADER     => ['Cache-Control: no-cache'],
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return (is_string($raw) && $error === '') ? $status : 0;
}

function preflightHelp(): void
{
    echo <<<TXT
Uso:
  php tools/registration-preflight.php [opciones]

Opciones:
  --phase=predeploy|go-live   Fase a comprobar (por defecto: predeploy).
  --base-url=URL              URL publica; si falta, usa APP_URL.
  --web-user=www-data         Usuario del servidor web para revisar permisos.
  --skip-http                 No hace peticiones HTTP de comprobacion.
  --no-pdf-smoke              No genera el PDF temporal de prueba.
  --allow-open-guard          Permite comprobar la fase go-live con la guarda abierta.
  --strict                    Trata los avisos como fallo.
  --help                      Muestra esta ayuda.

El script no crea tiendas, no escribe en BD y no envia correos.

TXT;
}
