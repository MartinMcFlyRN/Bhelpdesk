<?php $langs = \Bhelpdesk\Lang::available(); if (count($langs) > 1): ?>
<span class="lang-switch" title="<?= t('lang.label') ?>">
    <?php foreach ($langs as $code => $name): ?>
        <?php if ($code === \Bhelpdesk\Lang::current()): ?>
            <strong><?= e(strtoupper($code)) ?></strong>
        <?php else: ?>
            <a href="?lang=<?= e($code) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>" title="<?= e($name) ?>"><?= e(strtoupper($code)) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</span>
<?php endif; ?>
