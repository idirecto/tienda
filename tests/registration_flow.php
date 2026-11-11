<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/Support/FakeEmailApiClient.php';

use Tienda\Core\Config;
use Tienda\Core\ClientIp;
use Tienda\Core\Contract\ContractConfigurationException;
use Tienda\Core\Contract\ContractTemplate;
use Tienda\Core\Contract\ContractTemplateException;
use Tienda\Core\Database;
use Tienda\Core\Logger;
use Tienda\Core\Onboarding\RegistrationException;
use Tienda\Core\Onboarding\RegistrationFlow;
use Tienda\Core\Onboarding\VerificationService;
use Tienda\Models\ContractAcceptance;
use Tienda\Models\ContractVersion;
use Tienda\Models\RegistrationVerification;
use Tienda\Models\StoreRegistration;
use Tienda\Tests\Support\FakeEmailApiClient;

if (strtolower((string) config('app.env', 'local')) === 'production') {
    fwrite(STDERR, "No se ejecutan pruebas de escritura en produccion.\n");
    exit(2);
}

$dbName = (string) config('database.name', '');
if (preg_match('/(^|_)(test|prueba)(_|$)/i', $dbName) !== 1) {
    fwrite(STDERR, "La base de datos '{$dbName}' no parece de pruebas (debe contener test/prueba).\n");
    exit(2);
}

$ok = 0;
$fail = 0;
$artifacts = [];

$check = static function (bool $condition, string $label) use (&$ok, &$fail): void {
    if ($condition) {
        $ok++;
        echo "  OK    {$label}\n";
    } else {
        $fail++;
        echo "  FALLO {$label}\n";
    }
};

$logsTmp = rtrim(sys_get_temp_dir(), '/\\') . '/tienda-onboarding-logs-' . getmypid();
$rmdir = static function (string $dir) use (&$rmdir): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (glob($dir . '/*') ?: [] as $item) {
        is_dir($item) ? $rmdir($item) : @unlink($item);
    }
    @rmdir($dir);
};
$rmdir($logsTmp);
Logger::setPath($logsTmp);

$values = [
    'support_email'         => 'soporte@example.test',
    'idirecto_contact'      => 'Soporte IDIRECTO, soporte@example.test',
    'liability_limit'       => '600,00 EUR',
    'preaviso_days'         => 30,
    'export_days'           => 30,
    'negotiation_days'      => 30,
    'confidentiality_years' => 3,
];

$applyConfig = static function (array $override = []) use ($values): void {
    Config::set('registration.contract.values', array_merge($values, $override));
    Config::set('email.from', 'no-responder@example.test');
    Config::set('email.from_name', 'Tienda Pruebas');
    Config::set('email.reply_to', 'soporte@example.test');
};

// -----------------------------------------------------------------------------
// Preparacion de la BD de pruebas
// -----------------------------------------------------------------------------
$pdfTemplate = TIENDA_BASE . '/storage/contracts/templates/Contrato_web_clientes_v1.pdf';
$htmlTemplate = TIENDA_BASE . '/storage/contracts/templates/Contrato_web_clientes_v1.html';
if (!is_file($pdfTemplate) || !is_file($htmlTemplate)) {
    fwrite(STDERR, "Faltan las plantillas contractuales privadas.\n");
    exit(2);
}

try {
    $planId = Database::scalar("SELECT id FROM mt_plans WHERE code = 'basico' LIMIT 1");
    if ($planId === null) {
        $planId = Database::insert('mt_plans', [
            'code' => 'basico',
            'name' => 'Basico pruebas',
            'active' => 1,
            'sort' => 0,
        ]);
    }

    if (ContractVersion::current('access_web') === null) {
        Database::insert('mt_contract_versions', [
            'document_key'    => 'access_web',
            'version_label'   => '1.0-TEST',
            'effective_date'  => date('Y-m-d'),
            'template_path'   => 'Contrato_web_clientes_v1.pdf',
            'template_sha256' => hash_file('sha256', $pdfTemplate),
            'page_count'      => 7,
            'approved_by'     => 'Pruebas automatizadas',
            'approved_at'     => date('Y-m-d H:i:s'),
            'active'          => 1,
        ]);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'No se pudo preparar la BD de pruebas: ' . $e->getMessage() . "\n");
    exit(2);
}

$contract = ContractVersion::current('access_web');
if ($contract === null) {
    fwrite(STDERR, "No hay contrato vigente en la BD de pruebas.\n");
    exit(2);
}

$request = [
    'ip'         => '203.0.113.7',
    'user_agent' => 'Mozilla/5.0 (Pruebas de alta)',
    'base_url'   => 'https://example.test/registro/verificar/',
];

/** @return array<string,mixed> */
$account = static function (string $email, string $name = 'Empresa Prueba'): array {
    return [
        'id'             => random_int(50000, 65000),
        'nombre'         => $name,
        'nombre_sociedad'=> $name . ' S.L.',
        'nif_cif'        => 'B12345678',
        'direccion'      => 'Calle de Prueba 1',
        'cp'             => '50001',
        'poblacion'      => 'Zaragoza',
        'id_provincia'   => 0,
        'email'          => $email,
        'telefono'       => '600000000',
    ];
};

$representative = [
    'first_name' => 'Ana',
    'last_name'  => 'Perez Garcia',
    'role'       => 'Administradora',
    'phone'      => '600000000',
];

$fakeCode = static function (FakeEmailApiClient $fake, int $index = -1): string {
    $call = $index < 0 ? ($fake->calls[count($fake->calls) + $index] ?? null) : ($fake->calls[$index] ?? null);
    $html = (string) ($call['message']['html'] ?? '');
    if (preg_match('/letter-spacing:3px;font-weight:bold">([0-9 ]+)<\/p>/', $html, $m) !== 1) {
        return '';
    }

    return str_replace(' ', '', $m[1]);
};

$flow = static function (FakeEmailApiClient $fake, array $verificationConfig = []) use ($values): RegistrationFlow {
    $config = array_merge([
        'digits'                  => 8,
        'ttl_seconds'             => 900,
        'max_attempts'            => 5,
        'resend_cooldown_seconds' => 60,
        'max_resends_per_window'  => 5,
        'resend_window_seconds'   => 3600,
    ], $verificationConfig);

    return new RegistrationFlow($fake, new VerificationService($config));
};

$scenario = static function (string $name, callable $fn) use (&$fail, &$ok): void {
    echo "\n== {$name} ==\n";
    $pdo = Database::pdo();
    $pdo->beginTransaction();
    try {
        $fn();
    } catch (Throwable $e) {
        $fail++;
        echo '  FALLO excepcion no controlada: ' . get_class($e) . ' — ' . $e->getMessage() . "\n";
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
};

$makeRegistration = static function (
    RegistrationFlow $flow,
    array $account,
    string $email,
    string $name,
    array $representative,
    array $request
): array {
    return $flow->register(
        $account,
        $email,
        'clave-panel-1234',
        null,
        $representative,
        $request
    );
};

$applyConfig();

// -----------------------------------------------------------------------------
// 1. Configuracion incompleta
// -----------------------------------------------------------------------------
$scenario('Configuracion incompleta', static function () use ($applyConfig, $flow, $fakeCode, $account, $representative, $request, $check): void {
    $applyConfig(['support_email' => '']);
    $fake = new FakeEmailApiClient(true, true);
    $flow = $flow($fake);
    $email = 'config-' . bin2hex(random_bytes(4)) . '@example.test';
    $acc = $account($email, 'Config Incompleta');

    $reason = null;
    try {
        $flow->register($acc, $email, 'clave-panel-1234', null, $representative, $request);
    } catch (RegistrationException $e) {
        $reason = $e->reason();
    }

    $check($reason === RegistrationFlow::CONFIG_INCOMPLETE, 'el alta se bloquea si falta un valor legal');
    $check((int) Database::scalar('SELECT COUNT(*) FROM mt_store_registrations WHERE email = :e', ['e' => $email]) === 0, 'no se crea alta con configuracion incompleta');
    $check($fake->calls === [], 'no se envia ningun correo con configuracion incompleta');
    $applyConfig();
});

// -----------------------------------------------------------------------------
// 2. Marcador sin resolver y escape de datos
// -----------------------------------------------------------------------------
$scenario('Plantilla y escape', static function () use ($contract, $values, $check): void {
    $temp = tempnam(sys_get_temp_dir(), 'contrato-marcador-') . '.html';
    file_put_contents($temp, '<p>{{CLIENTE_RAZON_SOCIAL}}</p><p>{{MARCADOR_SIN_RESOLVER}}</p>');
    $template = new ContractTemplate($values, $temp);
    $reason = null;
    try {
        $template->render($contract, [
            'legal_name' => 'Cliente Prueba S.L.',
            'tax_id' => 'B12345678',
            'address' => 'Calle 1',
            'representative' => 'Ana Perez',
            'contact' => 'ana@example.test',
            'email' => 'ana@example.test',
        ]);
    } catch (ContractTemplateException) {
        $reason = 'marker';
    }
    @unlink($temp);
    $check($reason === 'marker', 'aborta si queda un marcador sin resolver');

    $template2 = new ContractTemplate($values);
    $rendered = $template2->render($contract, [
        'legal_name' => 'Cliente [X] {Y} & <b>',
        'tax_id' => 'B12345678',
        'address' => 'Calle 1',
        'representative' => 'Ana Perez',
        'contact' => 'ana@example.test',
        'email' => 'ana@example.test',
    ]);
    $check(str_contains($rendered['html'], '&#91;X&#93;') && str_contains($rendered['html'], '&#123;Y&#125;'), 'escapa corchetes y llaves del usuario');
    $check(str_contains($rendered['html'], '&amp; &lt;b&gt;') && !str_contains($rendered['html'], '<b>'), 'escapa HTML del usuario');
    $check(preg_match('/\{\{[^{}]+\}\}|\[[^\[\]]+\]/u', $rendered['html']) === 0, 'el HTML renderizado no conserva marcadores');

    $missing = null;
    try {
        (new ContractTemplate([]))->assertConfigurationReady();
    } catch (ContractConfigurationException) {
        $missing = true;
    }
    $check($missing === true, 'ContractTemplate bloquea configuracion incompleta');
});

// -----------------------------------------------------------------------------
// 2 bis. IP real detras de proxy de confianza
// -----------------------------------------------------------------------------
echo "\n== IP detras de proxy ==\n";
$check(
    ClientIp::resolve('10.0.0.1', '203.0.113.7', '', ['10.0.0.0/8']) === '203.0.113.7',
    'un proxy de confianza aporta la IP real del cliente'
);
$check(
    ClientIp::resolve('198.51.100.1', '203.0.113.7', '', ['10.0.0.0/8']) === '198.51.100.1',
    'sin proxy de confianza se ignora X-Forwarded-For'
);
$check(
    ClientIp::resolve('10.0.0.1', '203.0.113.7, 10.0.0.2', '', ['10.0.0.0/8']) === '203.0.113.7',
    'se recorre la cadena y se descartan los proxies conocidos'
);
$check(
    ClientIp::resolve('10.0.0.1', 'no-es-ip', '', ['10.0.0.0/8']) === '10.0.0.1',
    'una cabecera invalida cae a REMOTE_ADDR'
);
$check(
    ClientIp::resolve('10.0.0.1', '', '198.51.100.8', ['10.0.0.1']) === '198.51.100.8',
    'X-Real-IP se usa como respaldo del proxy de confianza'
);
$check(ClientIp::invalidRules(['10.0.0.0/33', 'no-es-ip']) === ['10.0.0.0/33', 'no-es-ip'], 'se detectan proxies de confianza invalidos');
$check(ClientIp::invalidRules(['10.0.0.1', '10.0.0.0/24', '2001:db8::/32']) === [], 'se aceptan IPs y CIDR validos');

$previousProxies = (array) Config::get('app.trusted_proxies', []);
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';
Config::set('app.trusted_proxies', ['10.0.0.1']);
$check(ClientIp::fromGlobals() === '203.0.113.7', 'fromGlobals usa X-Forwarded-For del proxy configurado');
Config::set('app.trusted_proxies', []);
$check(ClientIp::fromGlobals() === '10.0.0.1', 'fromGlobals ignora X-Forwarded-For sin proxy configurado');
Config::set('app.trusted_proxies', $previousProxies);
unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);

// -----------------------------------------------------------------------------
// 3. Alta correcta, auditoria, PDF, codigo y doble activacion
// -----------------------------------------------------------------------------
$scenario('Alta, verificacion y doble activacion', static function () use ($flow, $makeRegistration, $fakeCode, $account, $representative, $request, $check, $htmlTemplate, $contract, &$artifacts): void {
    $fake = new FakeEmailApiClient(true, true);
    $flow = $flow($fake);
    $email = 'alta-' . bin2hex(random_bytes(4)) . '@example.test';
    $acc = $account($email, 'Alta Correcta');
    $result = $makeRegistration($flow, $acc, $email, 'Alta Correcta', $representative, $request);
    $artifacts[] = TIENDA_BASE . '/storage/contracts/' . $result['pdf_path'];
    $code = $fakeCode($fake, 0);

    $store = Database::first('SELECT * FROM mt_stores WHERE id = :id', ['id' => $result['store_id']]);
    $user = Database::first('SELECT * FROM mt_store_users WHERE id = :id', ['id' => $result['user_id']]);
    $registration = StoreRegistration::findByUuid($result['uuid']);
    $acceptance = ContractAcceptance::latestForRegistration((int) $registration['id']);
    $verification = RegistrationVerification::latestForRegistration((int) $registration['id']);
    $pdfPath = TIENDA_BASE . '/storage/contracts/' . $acceptance['pdf_path'];

    $check((int) $store['status'] === 0, 'la tienda nace pendiente');
    $check((int) $user['active'] === 0, 'el usuario nace inactivo');
    $check($registration['status'] === StoreRegistration::STATUS_PENDING, 'el alta queda pendiente de verificacion');
    $check($registration['email_sent_at'] !== null, 'se registra el envio del correo');
    $check($code !== '' && preg_match('/^\d{8}$/', $code) === 1, 'el correo contiene un codigo de 8 digitos');
    $check($verification['code_hash'] !== $code && password_verify($code, (string) $verification['code_hash']), 'solo se guarda el hash del codigo');
    $check((int) $verification['attempts'] === 0 && (int) $verification['max_attempts'] === 5, 'el codigo nace con limite de intentos');
    $check($acceptance !== null, 'se registra la aceptacion electronica');
    $check((int) $acceptance['contract_version_id'] === (int) $contract['id'], 'la aceptacion guarda la version contractual');
    $check((string) $acceptance['pdf_path'] === $result['pdf_path'], 'la aceptacion guarda la ruta privada del PDF');
    $check((string) $acceptance['pdf_sha256'] === hash_file('sha256', $pdfPath), 'el hash del PDF coincide con el fichero');
    $check((string) $acceptance['template_sha256'] === strtolower((string) $contract['template_sha256']), 'la aceptacion liga la plantilla aprobada de la version');
    $check((string) $acceptance['source_template_sha256'] === hash_file('sha256', $htmlTemplate), 'el hash del HTML fuente coincide con el fichero usado');
    $check((string) $acceptance['acceptance_text_sha256'] === hash('sha256', ContractTemplate::ACCEPTANCE_TEXT), 'se audita el texto de aceptacion');
    $check((string) $acceptance['ip_address'] === $request['ip'], 'se guarda la IP de la aceptacion');
    $check((string) $acceptance['user_agent'] === $request['user_agent'], 'se guarda el user-agent de la aceptacion');
    $check($acceptance['accepted_at_utc'] !== null && $acceptance['timezone'] === 'Europe/Madrid', 'se guarda fecha UTC y zona horaria');
    $check((string) $acceptance['company_legal_name'] === $acc['nombre_sociedad'], 'el contrato usa la razon social de la cuenta');
    $check(str_starts_with((string) $acceptance['pdf_path'], 'generated/'), 'el PDF queda en storage/contracts/generated');

    $check(is_file($pdfPath) && is_readable($pdfPath), 'el PDF existe en la ruta privada');
    $text = (string) shell_exec('pdftotext ' . escapeshellarg($pdfPath) . ' - 2>/dev/null');
    $check(str_contains($text, $acc['nombre_sociedad']), 'el PDF contiene la razon social del cliente');
    $check(str_contains($text, 'soporte@example.test') && str_contains($text, '600,00 EUR'), 'el PDF contiene los valores de configuracion');
    $check(preg_match('/\{\{[^{}]+\}\}|\[[^\[\]]+\]/u', $text) === 0, 'el PDF no contiene marcadores sin resolver');
    $check(!str_contains($text, 'Powered by TCPDF'), 'el PDF no incluye publicidad de la libreria');

    $check(count($fake->calls) === 1, 'el cliente falso recibe exactamente un correo');
    $check(($fake->calls[0]['message']['host'] ?? '') === 'https://example.test', 'el correo usa el origen del sitio como host');
    $check(($fake->calls[0]['pdf_sha256'] ?? '') === hash_file('sha256', $pdfPath), 'el correo adjunta el mismo PDF auditado');
    $sentHtml = str_replace(' ', '', (string) ($fake->calls[0]['message']['html'] ?? ''));
    $check(str_contains($sentHtml, $code), 'el correo incluye el codigo de verificacion');

    $first = $flow->verify($result['uuid'], $code);
    $check($first['outcome'] === 'verified', 'el codigo correcto activa el alta');

    $store = Database::first('SELECT * FROM mt_stores WHERE id = :id', ['id' => $result['store_id']]);
    $user = Database::first('SELECT * FROM mt_store_users WHERE id = :id', ['id' => $result['user_id']]);
    $registration = StoreRegistration::findByUuid($result['uuid']);
    $verification = RegistrationVerification::latestForRegistration((int) $registration['id']);
    $check((int) $store['status'] === 1, 'la tienda queda activa al verificar');
    $check((int) $user['active'] === 1, 'el usuario queda activo al verificar');
    $check($registration['status'] === StoreRegistration::STATUS_VERIFIED, 'el alta queda verificada');
    $check($verification['consumed_at'] !== null, 'el codigo queda consumido');

    $second = $flow->verify($result['uuid'], $code);
    $check($second['outcome'] === 'already_verified', 'la segunda verificacion es idempotente');
    $check((int) Database::scalar('SELECT COUNT(*) FROM mt_contract_acceptances WHERE registration_id = :r', ['r' => $registration['id']]) === 1, 'no se duplica la aceptacion');
    $check((int) Database::scalar('SELECT COUNT(*) FROM mt_registration_verifications WHERE registration_id = :r', ['r' => $registration['id']]) === 1, 'no se duplica el codigo');
    $check((int) Database::scalar('SELECT status FROM mt_stores WHERE id = :id', ['id' => $result['store_id']]) === 1, 'la tienda sigue activa tras la segunda llamada');
});

// -----------------------------------------------------------------------------
// 4. Codigo incorrecto y limite de intentos
// -----------------------------------------------------------------------------
$scenario('Codigo incorrecto', static function () use ($flow, $makeRegistration, $fakeCode, $account, $representative, $request, $check, &$artifacts): void {
    $fake = new FakeEmailApiClient(true, true);
    $flow = $flow($fake);
    $email = 'wrong-' . bin2hex(random_bytes(4)) . '@example.test';
    $result = $makeRegistration($flow, $account($email, 'Codigo Incorrecto'), $email, 'Codigo Incorrecto', $representative, $request);
    $artifacts[] = TIENDA_BASE . '/storage/contracts/' . $result['pdf_path'];
    $code = $fakeCode($fake, 0);
    $wrong = $code === '00000000' ? '11111111' : '00000000';

    $first = $flow->verify($result['uuid'], $wrong);
    $verification = RegistrationVerification::latestForRegistration((int) StoreRegistration::findByUuid($result['uuid'])['id']);
    $check($first['outcome'] === RegistrationFlow::INVALID_CODE, 'un codigo incorrecto no activa');
    $check((int) $verification['attempts'] === 1, 'el intento fallido se cuenta');
    $check((int) Database::scalar('SELECT status FROM mt_stores WHERE id = :id', ['id' => $result['store_id']]) === 0, 'la tienda sigue pendiente');

    for ($i = 0; $i < 4; $i++) {
        $flow->verify($result['uuid'], $wrong);
    }
    $locked = $flow->verify($result['uuid'], $code);
    $check($locked['outcome'] === RegistrationFlow::LOCKED, 'se bloquea al agotar los intentos');
    $check((int) Database::scalar('SELECT status FROM mt_stores WHERE id = :id', ['id' => $result['store_id']]) === 0, 'un codigo bloqueado no activa la tienda');
});

// -----------------------------------------------------------------------------
// 5. Codigo caducado
// -----------------------------------------------------------------------------
$scenario('Codigo caducado', static function () use ($flow, $makeRegistration, $fakeCode, $account, $representative, $request, $check, &$artifacts): void {
    $fake = new FakeEmailApiClient(true, true);
    $flow = $flow($fake);
    $email = 'expired-' . bin2hex(random_bytes(4)) . '@example.test';
    $result = $makeRegistration($flow, $account($email, 'Codigo Caducado'), $email, 'Codigo Caducado', $representative, $request);
    $artifacts[] = TIENDA_BASE . '/storage/contracts/' . $result['pdf_path'];
    $code = $fakeCode($fake, 0);
    Database::execute(
        'UPDATE mt_registration_verifications SET expires_at = :expires WHERE registration_id = :r',
        ['expires' => gmdate('Y-m-d H:i:s', time() - 60), 'r' => (int) StoreRegistration::findByUuid($result['uuid'])['id']]
    );

    $resultVerify = $flow->verify($result['uuid'], $code);
    $check($resultVerify['outcome'] === RegistrationFlow::EXPIRED, 'un codigo caducado no se acepta');
    $check((int) Database::scalar('SELECT status FROM mt_stores WHERE id = :id', ['id' => $result['store_id']]) === 0, 'la tienda no se activa con codigo caducado');
});

// -----------------------------------------------------------------------------
// 6. Reenvio: espera, sustitucion y maximo por ventana
// -----------------------------------------------------------------------------
$scenario('Reenvio y limites', static function () use ($flow, $makeRegistration, $fakeCode, $account, $representative, $request, $check, &$artifacts): void {
    $fake = new FakeEmailApiClient(true, true);
    $slow = $flow($fake, ['resend_cooldown_seconds' => 60]);
    $email = 'resend-' . bin2hex(random_bytes(4)) . '@example.test';
    $result = $makeRegistration($slow, $account($email, 'Reenvio'), $email, 'Reenvio', $representative, $request);
    $artifacts[] = TIENDA_BASE . '/storage/contracts/' . $result['pdf_path'];
    $firstCode = $fakeCode($fake, 0);

    $cooldown = null;
    try {
        $slow->resend($result['uuid'], $request);
    } catch (RegistrationException $e) {
        $cooldown = $e->reason();
    }
    $check($cooldown === RegistrationFlow::RESEND_COOLDOWN, 'se respeta la espera entre reenvios');
    $check(count($fake->calls) === 1, 'el reenvio en espera no envia correo');

    $fast = $flow($fake, ['resend_cooldown_seconds' => 0, 'max_resends_per_window' => 2]);
    $resend = $fast->resend($result['uuid'], $request);
    $check(($resend['outcome'] ?? '') === 'resent', 'el reenvio permitido envia un codigo nuevo');
    $check(count($fake->calls) === 2, 'el cliente falso registra el reenvio');
    $secondCode = $fakeCode($fake, 1);
    $check($secondCode !== '' && $secondCode !== $firstCode, 'el codigo reenviado es distinto');

    $registrationId = (int) StoreRegistration::findByUuid($result['uuid'])['id'];
    $active = RegistrationVerification::activeForRegistration($registrationId);
    $all = Database::select('SELECT * FROM mt_registration_verifications WHERE registration_id = :r ORDER BY id', ['r' => $registrationId]);
    $check(count($all) === 2, 'se conservan el codigo antiguo y el nuevo');
    $check($all[0]['superseded_at'] !== null, 'el codigo anterior queda anulado');
    $check($active !== null && (int) $active['id'] === (int) $all[1]['id'], 'solo el codigo nuevo esta activo');

    $limit = null;
    try {
        $fast->resend($result['uuid'], $request);
    } catch (RegistrationException $e) {
        $limit = $e->reason();
    }
    $check($limit === RegistrationFlow::RESEND_LIMIT, 'se aplica el maximo de reenvios por ventana');
    $check(count($fake->calls) === 2, 'el limite no envia un tercer correo');

    $old = $fast->verify($result['uuid'], $firstCode);
    $check($old['outcome'] === RegistrationFlow::INVALID_CODE, 'el codigo anterior ya no sirve');
    $verified = $fast->verify($result['uuid'], $secondCode);
    $check($verified['outcome'] === 'verified', 'el codigo nuevo activa el alta');
});

// -----------------------------------------------------------------------------
// 7. Rollback: fallo de correo
// -----------------------------------------------------------------------------
$scenario('Rollback por fallo de correo', static function () use ($flow, $account, $representative, $request, $check): void {
    $fake = new FakeEmailApiClient(true, false);
    $flow = $flow($fake);
    $email = 'rollback-' . bin2hex(random_bytes(4)) . '@example.test';
    $acc = $account($email, 'Rollback');

    $reason = null;
    try {
        $flow->register($acc, $email, 'clave-panel-1234', null, $representative, $request);
    } catch (RegistrationException $e) {
        $reason = $e->reason();
    }

    $check($reason === RegistrationFlow::MAIL_FAILED, 'el fallo de correo se comunica de forma controlada');
    $check((int) Database::scalar('SELECT COUNT(*) FROM mt_stores WHERE id_tienda_idirecto = :a', ['a' => (int) $acc['id']]) === 0, 'rollback: no queda tienda');
    $check((int) Database::scalar('SELECT COUNT(*) FROM mt_store_users WHERE email = :e', ['e' => $email]) === 0, 'rollback: no queda usuario');
    $check((int) Database::scalar('SELECT COUNT(*) FROM mt_store_registrations WHERE email = :e', ['e' => $email]) === 0, 'rollback: no queda alta');
    $check((int) Database::scalar('SELECT COUNT(*) FROM mt_contract_acceptances WHERE representative_email = :e', ['e' => $email]) === 0, 'rollback: no queda aceptacion');

    $pdfPath = (string) ($fake->calls[0]['pdf_path'] ?? '');
    $check($pdfPath !== '' && !is_file($pdfPath), 'rollback: se borra el PDF huerfano');
});

// -----------------------------------------------------------------------------
// 8. PDF no accesible por URL
// -----------------------------------------------------------------------------
echo "\n== PDF no accesible por URL ==\n";
$generatedDir = TIENDA_BASE . '/storage/contracts/generated';
$dummy = $generatedDir . '/url-test-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($dummy, "%PDF-1.4\n%%EOF\n");
@chmod($dummy, 0640);
$url = 'http://local.tienda/storage/contracts/generated/' . basename($dummy);
$status = trim((string) shell_exec('curl -s -o /dev/null --max-time 5 -w "%{http_code}" ' . escapeshellarg($url)));
$check(in_array($status, ['403', '404'], true), 'el PDF generado responde 403/404 por URL (recibido: ' . $status . ')');
@unlink($dummy);

$templateUrl = 'http://local.tienda/storage/contracts/templates/Contrato_web_clientes_v1.html';
$templateStatus = trim((string) shell_exec('curl -s -o /dev/null --max-time 5 -w "%{http_code}" ' . escapeshellarg($templateUrl)));
$check(in_array($templateStatus, ['403', '404'], true), 'la plantilla contractual responde 403/404 por URL (recibido: ' . $templateStatus . ')');

// -----------------------------------------------------------------------------
// 9. Logs sin datos personales
// -----------------------------------------------------------------------------
echo "\n== Logs sin datos personales ==\n";
$logText = '';
foreach (glob($logsTmp . '/*/*/*.log') ?: [] as $logFile) {
    $logText .= (string) file_get_contents($logFile);
}
foreach (glob($logsTmp . '/*/*.log') ?: [] as $logFile) {
    $logText .= (string) file_get_contents($logFile);
}
$check($logText !== '', 'se han generado logs de auditoria interna');
foreach ([
    '@example.test',
    'Empresa Prueba',
    'Ana Perez',
    '203.0.113.7',
    'Mozilla/5.0 (Pruebas de alta)',
] as $needle) {
    $check(!str_contains($logText, $needle), 'el log no contiene dato personal/tecnico sensible: ' . $needle);
}

foreach ($artifacts as $artifact) {
    @unlink($artifact);
}
$rmdir($logsTmp);

echo "\nResultado: {$ok} OK, {$fail} fallos\n";
exit($fail === 0 ? 0 : 1);
