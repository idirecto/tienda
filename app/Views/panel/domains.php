<?php
/**
 * Dominios de la tienda.
 *
 * Arriba, la direccion de plataforma que SIEMPRE tiene la tienda
 * (<slug>.<dominio base>): se puede cambiar sin comprar nada y no depende de
 * que la tienda tenga un dominio propio. Abajo, los dominios propios con su
 * verificacion DNS.
 *
 * @var array $domains @var string $platformIp @var string $platformCname @var string $base
 * @var string $subdomain @var string $subdomainHost @var string $subdomainUrl
 * @var string $baseDomain @var array $baseDomains @var array|null $subdomainCheck
 */
use Tienda\Core\Csrf;

$checkLevel = (string) ($subdomainCheck['level'] ?? '');
$checkClass = match ($checkLevel) {
    'ok'      => 'alert-success',
    'warning' => 'alert-warning',
    default   => 'alert-error',
};
?>
<section class="card">
    <h2>Tu direccion web (subdominio de la plataforma)</h2>
    <p class="muted">
        Toda tienda tiene un subdominio de regalo en <strong><?= e($baseDomain ?: 'la plataforma') ?></strong>.
        Funciona desde el primer dia y no hace falta comprar ningun dominio.
    </p>

    <?php if ($subdomain === '' || $baseDomain === ''): ?>
        <div class="alert alert-error">
            La plataforma no tiene un dominio base configurado. Revisa <code>BASE_DOMAINS</code>
            en el <code>.env</code> del servidor.
        </div>
    <?php else: ?>
        <p class="muted small">
            Direccion actual:
            <a href="<?= e($subdomainUrl) ?>" target="_blank" rel="noopener"><strong><?= e($subdomainHost) ?></strong></a>
        </p>

        <form method="post" action="<?= e($base) ?>/panel/dominios/subdominio" class="stack">
            <?= Csrf::field() ?>
            <label>Subdominio
                <input type="text" name="subdomain" value="<?= e($subdomain) ?>" required
                       minlength="3" maxlength="40" pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?"
                       autocomplete="off" spellcheck="false">
            </label>
            <p class="muted small">
                Sera <code><?= e($subdomainHost) ?></code>. Solo letras, numeros y guiones;
                no puede empezar ni terminar por guion. Si escribes el nombre completo,
                se queda solo con la etiqueta.
            </p>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar subdominio</button>
                <a class="btn btn-ghost" href="<?= e($subdomainUrl) ?>" target="_blank" rel="noopener">Ver mi tienda</a>
            </div>
        </form>

        <form method="post" action="<?= e($base) ?>/panel/dominios/subdominio/comprobar" class="actions" style="margin-top:.75rem">
            <?= Csrf::field() ?>
            <button class="btn btn-ghost" type="submit">Comprobar que carga</button>
            <span class="muted small">Revisa DNS, HTTPS y que el certificado del subdominio sea valido.</span>
        </form>

        <?php if ($subdomainCheck): ?>
            <div class="alert <?= e($checkClass) ?>" style="margin-top:1rem">
                <strong><?= $checkLevel === 'ok' ? 'Todo correcto' : ($checkLevel === 'warning' ? 'Aviso' : 'No carga todavia') ?></strong>
                <p style="margin:.35rem 0 0"><?= e((string) ($subdomainCheck['message'] ?? '')) ?></p>
                <?php if (!empty($subdomainCheck['web']['message'])): ?>
                    <p class="small" style="margin:.25rem 0 0">Web: <?= e((string) $subdomainCheck['web']['message']) ?></p>
                <?php endif; ?>
                <p class="small" style="margin:.25rem 0 0">
                    Comprobado el <?= e((string) ($subdomainCheck['checked_at'] ?? '')) ?>.
                    <?php if (!empty($subdomainCheck['url'])): ?>
                        <a href="<?= e((string) $subdomainCheck['url']) ?>" target="_blank" rel="noopener">Abrir</a>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="hint-box">
            <strong>Si el subdominio no carga</strong>
            <p>El DNS wildcard <code>*.<span><?= e($baseDomain) ?></span></code> debe apuntar a este servidor
                y el certificado TLS debe cubrir <code>*.<span><?= e($baseDomain) ?></span></code>.</p>
            <p class="muted small">Eso se configura una sola vez en el servidor (nginx/Apache). No es algo que
                tengas que tocar en tu registrador.</p>
        </div>
    <?php endif; ?>
</section>

<section class="two-col">
    <div class="card">
        <h2>Tus dominios propios</h2>

        <?php if (empty($domains)): ?>
            <p class="muted">Aun no has conectado ningun dominio propio. Mientras tanto, tu tienda ya se ve
                en <strong><?= e($subdomainHost ?: 'tu-subdominio') ?></strong>.</p>
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
        <h2>Conectar un dominio propio</h2>
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
