<?php
/**
 * Avisos.
 * @var array $notices @var int $used @var int $max @var string $base
 */
use Tienda\Core\Csrf;
?>
<section class="two-col">
    <div class="card">
        <div class="card-head">
            <h2>Avisos</h2>
            <span class="badge"><?= (int) $used ?> / <?= (int) $max ?></span>
        </div>

        <?php if (empty($notices)): ?>
            <p class="muted">Sin avisos. Publica uno para informar a tus clientes.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($notices as $n): ?>
                    <li class="list-item">
                        <span class="pill pill-<?= e($n['type']) ?>"><?= e($n['type']) ?></span>
                        <div class="list-body">
                            <strong><?= e($n['message']) ?></strong>
                            <small><?= (int) $n['visible'] === 1 ? 'visible' : 'oculto' ?> · <?= e($n['created_at']) ?></small>
                        </div>
                        <form method="post" action="<?= e($base) ?>/panel/avisos/<?= (int) $n['id'] ?>/borrar"
                              onsubmit="return confirm('¿Eliminar este aviso?')">
                            <?= Csrf::field() ?>
                            <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Nuevo aviso</h2>
        <?php if ($used >= $max): ?>
            <div class="alert alert-error">Has alcanzado el limite de avisos de tu plan.</div>
        <?php else: ?>
            <form method="post" action="<?= e($base) ?>/panel/avisos" class="stack">
                <?= Csrf::field() ?>
                <label>Tipo
                    <select name="type">
                        <option value="info">Informacion</option>
                        <option value="promo">Promocion</option>
                        <option value="warning">Aviso importante</option>
                    </select>
                </label>
                <label>Mensaje<textarea name="message" rows="3" maxlength="500" required></textarea></label>
                <label class="check"><input type="checkbox" name="visible" value="1" checked> Visible</label>
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Publicar aviso</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
