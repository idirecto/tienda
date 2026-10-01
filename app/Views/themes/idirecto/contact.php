<?php /** Contacto. @var \Tienda\Core\Tenant $tenant */ ?>
<section class="container section">
    <div class="page-head"><h1>Contacto</h1></div>

    <div class="contact-grid">
        <div>
            <p>Estamos a tu disposicion para cualquier consulta sobre productos, pedidos o envios.</p>
            <ul class="contact-list">
                <?php if ($tenant->phone()): ?><li><strong>Telefono:</strong> <?= e($tenant->phone()) ?></li><?php endif; ?>
                <?php if ($tenant->whatsapp()): ?><li><strong>WhatsApp:</strong> <?= e($tenant->whatsapp()) ?></li><?php endif; ?>
                <?php if ($tenant->email()): ?><li><strong>Email:</strong> <?= e($tenant->email()) ?></li><?php endif; ?>
                <?php if ($tenant->address()): ?><li><strong>Direccion:</strong> <?= e($tenant->address()) ?>, <?= e($tenant->postalCode()) ?> <?= e($tenant->city()) ?></li><?php endif; ?>
            </ul>
        </div>
        <form class="form-card" method="post" action="<?= e($base) ?>/contacto">
            <label>Nombre<input type="text" name="name" required></label>
            <label>Email<input type="email" name="email" required></label>
            <label>Mensaje<textarea name="message" rows="5" required></textarea></label>
            <button class="btn btn-primary" type="submit" disabled>Enviar (proximamente)</button>
        </form>
    </div>
</section>
