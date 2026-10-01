<?php
/**
 * Dominios y verificacion DNS.
 * @var array $domains @var string $platformIp @var string $platformCname @var string $base
 */
use Tienda\Core\Csrf;
?>
<section class="two-col">
    <div class="card">
        <h2>Tus dominios</h2>

        <?php if (empty($domains)): ?>
            <p class="muted">Aun no has conectado ningun dominio. Tu tienda es accesible en
                <strong>tu-subdominio</strong> desde el primer dia.</p>
        <?php else: ?>
            <?php foreach ($domains as $d): ?>
                <article class="domain">
                    <header>
                        <strong><?= e($d['domain']) ?></strong>
                        <span class="pill pill-<?= (int) $d['status'] === 1 ? 'promo' : ((int) $d['status'] === 2 ? 'warning' : 'info') ?>">
                            <?= (int) $d['status'] === 1 ? 'verificado' : ((int) $d['status'] === 2 ? 'error' : 'pendiente') ?>
                        </span>
                    </header>
                    <?php if ($d['last_result']): ?>
                        <p class="muted small"><?= e($d['last_result']) ?></p>
                    <?php endif; ?>

                    <div class="dns-table">
                        <div class="dns-row"><span>Tipo</span><span>Nombre</span><span>Valor</span></div>
                        <div class="dns-row"><code>A</code><code>@</code><code><?= e($d['expected_a'] ?: $platformIp) ?></code></div>
                        <div class="dns-row"><code>CNAME</code><code>www</code><code><?= e($d['expected_cname'] ?: $platformCname) ?></code></div>
                    </div>

                    <div class="actions">
                        <form method="post" action="<?= e($base) ?>/panel/dominios/<?= (int) $d['id'] ?>/verificar" class="inline">
                            <?= Csrf::field() ?>
                            <button class="btn btn-primary btn-sm" type="submit">Verificar DNS</button>
                        </form>
                        <form method="post" action="<?= e($base) ?>/panel/dominios/<?= (int) $d['id'] ?>/borrar" class="inline"
                              onsubmit="return confirm('¿Eliminar este dominio?')">
                            <?= Csrf::field() ?>
                            <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Conectar un dominio</h2>
        <ol class="steps">
            <li>Compra tu dominio en el registrador que prefieras.</li>
            <li>Anade aqui el dominio y pulsa <strong>Guardar</strong>.</li>
            <li>Crea en tu registrador los registros DNS que te indicamos.</li>
            <li>Espera la propagacion y pulsa <strong>Verificar DNS</strong>.</li>
        </ol>

        <form method="post" action="<?= e($base) ?>/panel/dominios" class="stack">
            <?= Csrf::field() ?>
            <label>Dominio
                <input type="text" name="domain" placeholder="mitienda.com" required>
            </label>
            <label>Metodo de verificacion
                <select name="method">
                    <option value="A">Registro A</option>
                    <option value="CNAME">CNAME</option>
                    <option value="BOTH">Ambos</option>
                </select>
            </label>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar dominio</button>
            </div>
        </form>

        <div class="hint-box">
            <strong>Datos de la plataforma</strong>
            <p>IP: <code><?= e($platformIp ?: 'no configurada') ?></code></p>
            <p>CNAME: <code><?= e($platformCname) ?></code></p>
            <p class="muted small">Configurables en <code>.env</code> (PLATFORM_IP, PLATFORM_CNAME).</p>
        </div>
    </div>
</section>
