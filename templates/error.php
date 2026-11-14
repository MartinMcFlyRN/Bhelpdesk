<?php $home = str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/op') ? '/op/' : '/customer/'; ?>
<div class="card narrow">
    <h1><?= e($title) ?></h1>
    <p><?= e($message) ?></p>
    <p><a class="btn" href="<?= e($home) ?>">Torna all'inizio</a></p>
</div>
