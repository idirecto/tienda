<?php
/**
 * Formulario de direccion (nueva o edicion).
 *
 * @var array $customer @var array|null $address @var bool $isFirst @var string $base
 */
use Tienda\Core\Csrf;

$v = static fn (string $key, string $default = ''): string => (string) ($address[$key] ?? $default);
$id = (int) ($address['id'] ?? 0);
$espana = (string) ($address['country'] ?? 'Espana');
?>
<section class="container section section-narrow">
    <div class="page-head">
        <h1><?= $id > 0 ? 'Editar direccion' : 'Nueva direccion' ?></h1>
        <p class="muted">Se usara para el envio y podras elegirla en tus proximas compras.</p>
    </div>

    <form class="form-card" method="post" action="<?= e($base) ?>/cuenta/direcciones">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="field-grid">
            <label>Nombre para reconocerla (opcional)
                <input type="text" name="label" maxlength="60" value="<?= e($v('label')) ?>" placeholder="Casa, oficina...">
            </label>
            <label>Nombre y apellidos
                <input type="text" name="name" required maxlength="150" value="<?= e($v('name', (string) $customer['name'])) ?>">
            </label>
            <label>DNI / NIF
                <input type="text" name="tax_id" maxlength="30" value="<?= e($v('tax_id', (string) ($customer['tax_id'] ?? ''))) ?>">
            </label>
            <label class="field-wide">Direccion
                <input type="text" name="address" required maxlength="250" value="<?= e($v('address')) ?>">
            </label>
            <label class="field-wide">Portal, escalera, piso
                <input type="text" name="detail" maxlength="200" value="<?= e($v('detail')) ?>">
            </label>
            <label>Codigo postal
                <input type="text" name="postal_code" maxlength="20" value="<?= e($v('postal_code')) ?>">
            </label>
            <label>Poblacion
                <input type="text" name="city" maxlength="120" value="<?= e($v('city')) ?>">
            </label>
            <label>Provincia
                <input type="text" name="province" maxlength="120" value="<?= e($v('province')) ?>">
            </label>
            <label>Pais
                <input type="text" name="country" maxlength="60" value="<?= e($espana) ?>">
            </label>
            <label>Telefono
                <input type="text" name="phone" maxlength="40" value="<?= e($v('phone', (string) ($customer['phone'] ?? ''))) ?>">
            </label>
            <label>Movil
                <input type="text" name="mobile" maxlength="40" value="<?= e($v('mobile', (string) ($customer['mobile'] ?? ''))) ?>">
            </label>
        </div>

        <label class="check">
            <input type="checkbox" name="is_default_ship" value="1" <?= (int) $v('is_default_ship', $isFirst ? '1' : '0') === 1 ? 'checked' : '' ?>>
            Usar como direccion de envio por defecto
        </label>
        <label class="check">
            <input type="checkbox" name="is_default_bill" value="1" <?= (int) $v('is_default_bill', $isFirst ? '1' : '0') === 1 ? 'checked' : '' ?>>
            Usar como direccion de facturacion por defecto
        </label>

        <button class="btn btn-primary" type="submit">Guardar direccion</button>
        <a class="link" href="<?= e($base) ?>/cuenta/direcciones">Cancelar</a>
    </form>
</section>
