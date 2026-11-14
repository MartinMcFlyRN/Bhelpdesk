<?php foreach (take_flashes() as $f): ?>
    <?php if ($f['type'] === 'link'): ?>
        <div class="alert alert-success alert-link">
            <?= t($f['key'] ?? '', $f['params'] ?? []) ?>
            <input type="text" readonly class="mono" value="<?= e($f['params']['url'] ?? '') ?>">
        </div>
    <?php else: ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= t($f['key'] ?? '', $f['params'] ?? []) ?></div>
    <?php endif; ?>
<?php endforeach; ?>
