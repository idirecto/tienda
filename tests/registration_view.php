<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use Tienda\Core\View;

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

$fakeContract = [
    'id' => 99,
    'version_label' => 'PRUEBA-2026-01',
    'effective_date' => '2026-11-01',
];
$data = [
    'pageTitle' => 'Registra tu tienda',
    'open' => true,
    'ready' => true,
    'contract' => $fakeContract,
    'slug' => '',
];
View::setBasePath('');
$html = View::render('register', $data, 'panel_blank');
$text = preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '';

$expectedDeclaration = 'Declaro que estoy autorizado/a para activar y utilizar la cuenta web en nombre del cliente de IDIRECTO asociado a este acceso. He leído y acepto el Contrato de acceso y uso de la web en nombre de dicho cliente. La aceptación quedará registrada electrónicamente y se enviará una confirmación al correo indicado';

$check(str_contains($html, 'Contrato de acceso y uso de la web'), 'la declaración enlaza al contrato');
$check(str_contains($text, $expectedDeclaration), 'el texto de aceptación se reproduce literalmente');
$check(str_contains($html, 'name="contract_accepted"'), 'la aceptación tiene su propia casilla');
$check(!preg_match('/name="contract_accepted"[^>]*checked/i', $html), 'la casilla no viene marcada');
$check(str_contains($html, 'Crear Cuenta y aceptar contrato'), 'el botón usa el texto exacto aprobado');
$check(str_contains($html, 'data-registration-submit disabled'), 'el botón nace desactivado');
foreach (['representative_first_name', 'representative_last_name', 'representative_role', 'representative_phone'] as $field) {
    $check((bool) preg_match('/name="' . preg_quote($field, '/') . '"[^>]*required/', $html), 'campo obligatorio: ' . $field);
}
$check(str_contains($html, 'PRUEBA-2026-01'), 'la versión contractual se muestra junto al enlace');
$check(str_contains($html, 'data-ready="1"'), 'la vista indica que el flujo está listo solo con la versión recibida');

$htmlNoContract = View::render('register', [
    'pageTitle' => 'Registra tu tienda',
    'open' => true,
    'ready' => false,
    'contract' => null,
    'slug' => '',
], 'panel_blank');
$check(!str_contains($htmlNoContract, 'name="contract_accepted"'), 'sin versión vigente no se muestra la aceptación');
$check(str_contains($htmlNoContract, 'El alta todavía no está habilitada'), 'sin versión vigente se informa del bloqueo');
$check(str_contains($htmlNoContract, 'data-registration-submit disabled'), 'sin versión vigente el botón sigue desactivado');

echo "\nResultado: {$ok} OK, {$fail} fallos\n";
exit($fail === 0 ? 0 : 1);
