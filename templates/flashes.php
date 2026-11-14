<?php foreach (take_flashes() as $f): ?>
    <div class="alert alert-<?= e($f['type']) ?>"><?= t($f['key'] ?? '', $f['params'] ?? []) ?></div>
<?php endforeach; ?>
