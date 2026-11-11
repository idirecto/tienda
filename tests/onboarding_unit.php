<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/Support/FakeEmailApiClient.php';

use Tienda\Core\Mail\EmailApiClient;
use Tienda\Core\Onboarding\VerificationService;
use Tienda\Models\ContractAcceptance;
use Tienda\Models\ContractVersion;
use Tienda\Models\Store;
use Tienda\Tests\Support\FakeEmailApiClient;

$fail = 0;
$ok = 0;
$check = static function (bool $condition, string $label) use (&$fail, &$ok): void {
    if ($condition) {
        $ok++;
        echo "  OK    {$label}\n";
    } else {
        $fail++;
        echo "  FALLO {$label}\n";
    }
};

echo "== Codigo de verificacion ==\n";
$service = new VerificationService([
    'digits' => 8,
    'ttl_seconds' => 900,
    'max_attempts' => 5,
    'resend_cooldown_seconds' => 60,
    'max_resends_per_window' => 5,
    'resend_window_seconds' => 3600,
]);
$tz = new DateTimeZone('UTC');
$now = new DateTimeImmutable('2026-11-10 12:00:00', $tz);
$issued = $service->issue($now);
$code = $issued['code'];

$check((bool) preg_match('/^\d{8}$/', $code), 'el codigo tiene 8 digitos');
$check($issued['hash'] !== $code && password_verify($code, $issued['hash']), 'solo se persiste un hash verificable');
$check($issued['expires_at']->getTimestamp() === $now->getTimestamp() + 900, 'la caducidad usa el TTL configurado');
$check($service->normalizeCode(substr($code, 0, 4) . ' ' . substr($code, 4, 2) . '-' . substr($code, 6)) === $code, 'normaliza espacios y guiones');
$check(
    $service->verify($code, $issued['hash'], 0, null, null, $issued['expires_at'], $now) === VerificationService::VALID,
    'el codigo correcto es valido'
);
$check(
    $service->verify('00000000', $issued['hash'], 0, null, null, $issued['expires_at'], $now) === VerificationService::INVALID,
    'un codigo incorrecto no es valido'
);
$check(
    $service->verify($code, $issued['hash'], 0, null, null, $now, $now) === VerificationService::EXPIRED,
    'un codigo caducado no es valido'
);
$check(
    $service->verify($code, $issued['hash'], 0, '2026-11-10 12:01:00', null, $issued['expires_at'], $now) === VerificationService::USED,
    'un codigo usado no vuelve a servir'
);
$check(
    $service->verify($code, $issued['hash'], 0, null, '2026-11-10 12:01:00', $issued['expires_at'], $now) === VerificationService::SUPERSEDED,
    'un codigo reenviado queda anulado'
);
$check(
    $service->verify($code, $issued['hash'], 5, null, null, $issued['expires_at'], $now) === VerificationService::LOCKED,
    'el limite de intentos bloquea el codigo'
);
$check($service->mayResend(null, 0, $now), 'se puede enviar el primer codigo');
$check(!$service->mayResend($now->modify('-30 seconds'), 1, $now), 'la espera entre reenvios se respeta');
$check($service->mayResend($now->modify('-61 seconds'), 1, $now), 'pasada la espera se permite reenviar');
$check(!$service->mayResend($now->modify('-61 seconds'), 5, $now), 'el maximo de reenvios por ventana se respeta');

echo "\n== Estados de tienda ==\n";
$check(Store::statusAllowsPublicAccess(Store::STATUS_ACTIVE), 'el estado activo permite storefront');
$check(!Store::statusAllowsPublicAccess(Store::STATUS_PENDING), 'el estado pendiente no permite storefront');
$check(!Store::statusAllowsPublicAccess(Store::STATUS_SUSPENDED), 'el estado suspendido no permite storefront');
$check(!Store::statusAllowsPublicAccess(null), 'un estado ausente falla cerrado');

echo "\n== Plantilla contractual ==\n";
$check(
    ContractVersion::templateAbsolutePath([
        'template_path' => '../fuera.pdf',
        'template_sha256' => str_repeat('a', 64),
    ]) === null,
    'la ruta de plantilla no puede salir de storage/contracts/templates'
);
$check(
    ContractVersion::templateAbsolutePath([
        'template_path' => 'no-existe.pdf',
        'template_sha256' => str_repeat('a', 64),
    ]) === null,
    'una plantilla inexistente no se considera utilizable'
);
$invalidAcceptanceRejected = false;
try {
    ContractAcceptance::record(['event_uuid' => 'incompleto']);
} catch (InvalidArgumentException) {
    $invalidAcceptanceRejected = true;
}
$check($invalidAcceptanceRejected, 'una aceptacion incompleta se rechaza antes de tocar la BD');

echo "\n== Cliente de correo ==\n";
$disabled = new EmailApiClient([
    'enabled' => false,
    'base_url' => '',
    'username' => '',
    'password' => '',
]);
$disabledResult = $disabled->sendWithPdf([], '/no/existe.pdf', 'contrato.pdf');
$check(!$disabled->isEnabled(), 'la API real esta desactivada por defecto');
$check(!$disabledResult['ok'] && $disabledResult['http_status'] === 0, 'desactivada no intenta enviar ni abrir red');

$fake = new FakeEmailApiClient(true, true);
$tmp = tempnam(sys_get_temp_dir(), 'tienda-pdf-test-');
$tmpPdf = $tmp . '.pdf';
rename($tmp, $tmpPdf);
file_put_contents($tmpPdf, "%PDF-1.4\n%%EOF\n");
$fakeResult = $fake->sendWithPdf([
    'from' => 'no-responder@example.test',
    'to' => [['email' => 'cliente@example.test', 'name' => 'Cliente']],
    'subject' => 'Prueba',
    'html' => 'Prueba',
    'host' => 'https://example.test/registro',
], $tmpPdf, 'contrato-opaco.pdf');
$check($fakeResult['ok'] && count($fake->calls) === 1, 'el fake captura la llamada y no usa red');
$check(($fake->calls[0]['message']['to'][0]['email'] ?? '') === 'cliente@example.test', 'el fake conserva el destinatario validado');
$check(($fake->calls[0]['pdf_sha256'] ?? '') === hash_file('sha256', $tmpPdf), 'el fake recibe el hash del PDF de prueba');
@unlink($tmpPdf);

$fakeFail = new FakeEmailApiClient(true, false);
$failResult = $fakeFail->sendWithPdf([], $tmpPdf, 'contrato-opaco.pdf');
$check(!$failResult['ok'], 'el fake puede simular un fallo de envio');
$check($failResult['error'] !== null, 'el fallo devuelve un mensaje seguro');

echo "\nResultado: {$ok} OK, {$fail} fallos\n";
exit($fail === 0 ? 0 : 1);
