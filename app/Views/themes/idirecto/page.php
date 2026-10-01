<?php /** Pagina de contenido. @var array $block */ ?>
<section class="container section">
    <div class="page-head"><h1><?= e($block['title'] ?? '') ?></h1></div>
    <article class="prose">
        <?= nl2br(e($block['body'] ?? '')) ?>
    </article>
</section>
