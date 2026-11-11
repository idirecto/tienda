<?php
/**
 * Portada publica de la plataforma de tiendas de IDIRECTO.
 *
 * Contenido estatico: no consulta tiendas, productos, banners ni menus.
 *
 * @var string $base
 */
?>
<section class="platform-hero">
    <div class="platform-shell platform-hero-grid">
        <div class="platform-hero-copy">
            <p class="platform-eyebrow">Servicio para las tiendas de IDIRECTO</p>
            <h1>Tu tienda online,<br><span>con la fuerza de IDIRECTO</span></h1>
            <p class="platform-lead">
                La plataforma que IDIRECTO pone a disposición de sus tiendas para vender online:
                catálogo, pedidos, clientes y gestión diaria en un solo panel.
            </p>
            <p class="platform-note">
                <strong>Importante:</strong> este es el servicio de tiendas online para clientes de
                IDIRECTO, no la tienda online del mayorista.
            </p>
            <div class="platform-hero-actions">
                <a class="platform-cta" href="<?= e($base) ?>/registro">Crear mi tienda</a>
                <a class="platform-secondary" href="<?= e($base) ?>/panel/login">Ya tengo tienda</a>
            </div>
            <ul class="platform-hero-points">
                <li><?= icon_svg('check', 'platform-icon') ?> Catálogo conectado a IDIRECTO</li>
                <li><?= icon_svg('check', 'platform-icon') ?> Tu marca y tu dirección web</li>
                <li><?= icon_svg('check', 'platform-icon') ?> Pedidos y clientes en un panel</li>
            </ul>
        </div>

        <div class="platform-hero-visual" aria-hidden="true">
            <div class="platform-window">
                <div class="platform-window-bar">
                    <span></span><span></span><span></span>
                    <div class="platform-window-url">tu-tienda.idirecto</div>
                </div>
                <div class="platform-window-body">
                    <div class="platform-mini-head">
                        <span class="platform-mini-logo">idirecto</span>
                        <span class="platform-mini-menu"></span>
                        <span class="platform-mini-menu platform-mini-menu-sm"></span>
                    </div>
                    <div class="platform-mini-hero">
                        <div>
                            <span class="platform-mini-kicker">Tu escaparate</span>
                            <strong>Tu tienda online</strong>
                            <span class="platform-mini-line"></span>
                            <span class="platform-mini-line platform-mini-line-sm"></span>
                            <span class="platform-mini-button"></span>
                        </div>
                        <div class="platform-mini-visual"></div>
                    </div>
                    <div class="platform-mini-cards">
                        <div class="platform-mini-card">
                            <span class="platform-mini-thumb"></span>
                            <span class="platform-mini-text"></span>
                            <span class="platform-mini-price"></span>
                        </div>
                        <div class="platform-mini-card">
                            <span class="platform-mini-thumb platform-mini-thumb-alt"></span>
                            <span class="platform-mini-text"></span>
                            <span class="platform-mini-price"></span>
                        </div>
                        <div class="platform-mini-card">
                            <span class="platform-mini-thumb platform-mini-thumb-third"></span>
                            <span class="platform-mini-text"></span>
                            <span class="platform-mini-price"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="platform-float platform-float-top">
                <span><?= icon_svg('package', 'platform-icon') ?></span>
                <div><strong>Pedidos</strong><small>Gestión centralizada</small></div>
            </div>
            <div class="platform-float platform-float-bottom">
                <span><?= icon_svg('shield', 'platform-icon') ?></span>
                <div><strong>Tu espacio</strong><small>Aislado por tienda</small></div>
            </div>
        </div>
    </div>
</section>

<section class="platform-section" id="plataforma">
    <div class="platform-shell">
        <div class="platform-section-head">
            <p class="platform-eyebrow">La plataforma</p>
            <h2>Todo lo que necesitas para vender online</h2>
            <p>
                Una tienda completa para clientes de IDIRECTO, preparada para mostrar tu catálogo,
                recibir pedidos y gestionar tu negocio desde un único lugar.
            </p>
        </div>

        <div class="platform-cards">
            <article class="platform-card">
                <span class="platform-card-icon"><?= icon_svg('grid', 'platform-icon') ?></span>
                <h3>Catálogo conectado</h3>
                <p>Muestra el catálogo central de IDIRECTO y añade tus propios productos y categorías.</p>
            </article>
            <article class="platform-card">
                <span class="platform-card-icon"><?= icon_svg('star', 'platform-icon') ?></span>
                <h3>Tu marca</h3>
                <p>Nombre, logotipo, colores y dirección web propios para que la tienda sea tuya.</p>
            </article>
            <article class="platform-card">
                <span class="platform-card-icon"><?= icon_svg('cart', 'platform-icon') ?></span>
                <h3>Pedidos y clientes</h3>
                <p>Recibe pedidos, consulta su estado y gestiona clientes y direcciones desde el panel.</p>
            </article>
            <article class="platform-card">
                <span class="platform-card-icon"><?= icon_svg('truck', 'platform-icon') ?></span>
                <h3>Envíos y pagos</h3>
                <p>Configura zonas de envío, gastos, entregas y las formas de pago que aceptas.</p>
            </article>
            <article class="platform-card">
                <span class="platform-card-icon"><?= icon_svg('list', 'platform-icon') ?></span>
                <h3>Diseño y menú</h3>
                <p>Elige cómo se presenta tu tienda y organiza el menú de categorías a tu manera.</p>
            </article>
            <article class="platform-card">
                <span class="platform-card-icon"><?= icon_svg('shield', 'platform-icon') ?></span>
                <h3>Control y seguridad</h3>
                <p>Acceso privado al panel, roles de usuario y datos separados para cada tienda.</p>
            </article>
        </div>
    </div>
</section>

<section class="platform-section platform-section-alt" id="como-funciona">
    <div class="platform-shell">
        <div class="platform-section-head">
            <p class="platform-eyebrow">Cómo funciona</p>
            <h2>De tu cuenta de IDIRECTO a tu tienda online</h2>
            <p>Tres pasos para empezar a vender con tu propia web.</p>
        </div>

        <ol class="platform-steps">
            <li>
                <span class="platform-step-number">01</span>
                <h3>Accede con tu cuenta de IDIRECTO</h3>
                <p>Solo pueden crear tienda los clientes activos del mayorista. Validamos tu cuenta de IDIRECTO.</p>
            </li>
            <li>
                <span class="platform-step-number">02</span>
                <h3>Configura tu tienda</h3>
                <p>Elige tu dirección web, tu marca, el diseño, las categorías y las condiciones de venta.</p>
            </li>
            <li>
                <span class="platform-step-number">03</span>
                <h3>Publica y gestiona</h3>
                <p>Comparte tu tienda con tus clientes y gestiona pedidos, productos y envíos desde el panel.</p>
            </li>
        </ol>

        <div class="platform-center-action">
            <a class="platform-cta" href="<?= e($base) ?>/registro">Empezar ahora</a>
        </div>
    </div>
</section>

<section class="platform-section" id="ventajas">
    <div class="platform-shell platform-split">
        <div>
            <p class="platform-eyebrow">Para tiendas de IDIRECTO</p>
            <h2>Tu negocio, tu tienda, tu marca</h2>
            <p class="platform-split-lead">
                Si ya trabajas con IDIRECTO, no empiezas de cero. La plataforma te da una tienda
                online preparada para conectar tu catálogo y tu actividad comercial con tus clientes.
            </p>
            <ul class="platform-list">
                <li><?= icon_svg('check', 'platform-icon') ?>Catálogo mayorista y productos propios en la misma tienda.</li>
                <li><?= icon_svg('check', 'platform-icon') ?>Panel de gestión para pedidos, clientes, envíos y ajustes.</li>
                <li><?= icon_svg('check', 'platform-icon') ?>Diseño adaptable a móvil, tableta y escritorio.</li>
                <li><?= icon_svg('check', 'platform-icon') ?>Una dirección web propia dentro del dominio de la plataforma.</li>
            </ul>
        </div>
        <div class="platform-quote-card">
            <span class="platform-quote-mark">“</span>
            <p>
                IDIRECTO aporta el catálogo y la experiencia mayorista; la plataforma te da
                la herramienta para tener tu propia tienda online.
            </p>
            <strong>IDIRECTO · Plataforma para tiendas</strong>
        </div>
    </div>
</section>

<section class="platform-cta-band">
    <div class="platform-shell platform-cta-band-inner">
        <div>
            <p class="platform-eyebrow">¿Listo para tener tu tienda?</p>
            <h2>Crea tu tienda online con IDIRECTO</h2>
            <p>Accede con tu cuenta de cliente del mayorista y empieza a configurar tu escaparate.</p>
        </div>
        <div class="platform-cta-band-actions">
            <a class="platform-cta platform-cta-light" href="<?= e($base) ?>/registro">Crear mi tienda</a>
            <a class="platform-secondary platform-secondary-light" href="<?= e($base) ?>/panel/login">Acceder al panel</a>
        </div>
    </div>
</section>

<section class="platform-section platform-contact-section">
    <div class="platform-shell platform-contact-grid">
        <div>
            <p class="platform-eyebrow">Contacto</p>
            <h2>¿Tienes dudas?</h2>
            <p>
                Si eres cliente de IDIRECTO y quieres abrir tu tienda online, estaremos encantados
                de ayudarte a empezar.
            </p>
        </div>
        <div class="platform-contact-card">
            <ul>
                <li><span><?= icon_svg('phone', 'platform-icon') ?></span><a href="tel:+34976910100">976 910 100</a></li>
                <li><span><?= icon_svg('headset', 'platform-icon') ?></span><a href="mailto:ventas@i-directo.com">ventas@i-directo.com</a></li>
                <li><span><?= icon_svg('pin', 'platform-icon') ?></span>C/ Poeta Rosalía Castro, 9<br>50018 Zaragoza</li>
            </ul>
            <a class="platform-link-arrow" href="https://www.idirecto.es/contacte" target="_blank" rel="noopener">
                Ver página de contacto de IDIRECTO
            </a>
        </div>
    </div>
</section>
