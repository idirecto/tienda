<?php
/**
 * Layout de la portada estatica de IDIRECTO.
 *
 * No recibe ni usa ningun Tenant: la portada no debe mostrar datos de tiendas.
 *
 * @var string $content
 * @var string $pageTitle
 * @var string $pageDescription
 * @var string $base
 */
$title = $pageTitle ?? 'IDIRECTO | Plataforma web para sus tiendas';
$description = $pageDescription ?? 'La plataforma web que IDIRECTO pone a disposición de sus tiendas para vender online.';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <meta name="theme-color" content="#234b61">
    <link rel="canonical" href="<?= e(absolute_url('/')) ?>">
    <link rel="icon" href="<?= e(asset('assets/img/idirecto-favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= e(asset('assets/css/platform.css')) ?>">
</head>
<body class="platform-site">
<a class="platform-skip" href="#contenido">Saltar al contenido principal</a>

<header class="platform-header">
    <div class="platform-topbar">
        <div class="platform-shell platform-topbar-inner">
            <span>Plataforma web para las tiendas de <strong>IDIRECTO</strong></span>
            <span class="platform-topbar-links">
                <a href="#plataforma">Conoce la plataforma</a>
                <a href="<?= e($base) ?>/panel/login">Acceso tiendas</a>
            </span>
        </div>
    </div>

    <div class="platform-header-main">
        <div class="platform-shell platform-header-inner">
            <a class="platform-brand" href="<?= e($base) ?>/" aria-label="IDIRECTO - Inicio">
                <img src="<?= e(asset('assets/img/idirecto-logo-head.png')) ?>"
                     alt="IDIRECTO" width="260" height="50" decoding="async">
            </a>

            <button type="button" class="platform-menu-btn" data-platform-menu
                    aria-expanded="false" aria-controls="platform-nav" aria-label="Abrir menú">
                <span></span><span></span><span></span>
            </button>

            <nav class="platform-nav" id="platform-nav" aria-label="Navegación principal">
                <a href="#plataforma">Plataforma</a>
                <a href="#como-funciona">Cómo funciona</a>
                <a href="#ventajas">Ventajas</a>
                <a href="#contacto">Contacto</a>
                <span class="platform-nav-actions">
                    <a class="platform-link-btn" href="<?= e($base) ?>/panel/login">Acceder</a>
                    <a class="platform-cta platform-cta-sm" href="<?= e($base) ?>/registro">Crear mi tienda</a>
                </span>
            </nav>
        </div>
    </div>
</header>

<main id="contenido">
    <?= $content ?>
</main>

<footer class="platform-footer" id="contacto">
    <div class="platform-shell">
        <div class="platform-footer-grid">
            <div class="platform-footer-brand">
                <a href="<?= e($base) ?>/" aria-label="IDIRECTO - Inicio">
                    <img src="<?= e(asset('assets/img/idirecto-logo-head.png')) ?>"
                         alt="IDIRECTO" width="220" height="42" loading="lazy">
                </a>
                <p>La plataforma web que IDIRECTO pone a disposición de sus tiendas para vender online.</p>
                <p class="platform-footer-note">Servicio para clientes de IDIRECTO. No es la tienda online del mayorista.</p>
            </div>

            <div>
                <h2>Plataforma</h2>
                <ul>
                    <li><a href="#plataforma">Qué incluye</a></li>
                    <li><a href="#como-funciona">Cómo funciona</a></li>
                    <li><a href="#ventajas">Ventajas</a></li>
                    <li><a href="<?= e($base) ?>/registro">Crear mi tienda</a></li>
                </ul>
            </div>

            <div>
                <h2>IDIRECTO</h2>
                <ul>
                    <li><a href="https://www.idirecto.es/quienes-somos" target="_blank" rel="noopener">Quiénes somos</a></li>
                    <li><a href="https://www.idirecto.es/contacte" target="_blank" rel="noopener">Contacto</a></li>
                    <li><a href="https://www.idirecto.es/condiciones-de-venta" target="_blank" rel="noopener">Condiciones de venta</a></li>
                    <li><a href="https://www.idirecto.es/politica-de-privacidad" target="_blank" rel="noopener">Privacidad</a></li>
                    <li><a href="https://www.idirecto.es/aviso-legal" target="_blank" rel="noopener">Aviso legal</a></li>
                </ul>
            </div>

            <div>
                <h2>Contacto</h2>
                <ul class="platform-contact">
                    <li><a href="tel:+34976910100">976 910 100</a></li>
                    <li><a href="mailto:ventas@i-directo.com">ventas@i-directo.com</a></li>
                    <li>C/ Poeta Rosalía Castro, 9<br>50018 Zaragoza</li>
                </ul>
            </div>
        </div>

        <div class="platform-footer-bottom">
            <span>© <?= date('Y') ?> IDIRECTO MAYORISTA S.L. Todos los derechos reservados.</span>
            <span>Plataforma para tiendas de IDIRECTO</span>
        </div>
    </div>
</footer>

<script>
(function () {
    'use strict';
    var button = document.querySelector('[data-platform-menu]');
    var nav = document.getElementById('platform-nav');
    if (!button || !nav) return;

    function close() {
        nav.classList.remove('is-open');
        button.setAttribute('aria-expanded', 'false');
    }

    button.addEventListener('click', function () {
        var open = nav.classList.toggle('is-open');
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    nav.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') close();
    });
})();
</script>
</body>
</html>
