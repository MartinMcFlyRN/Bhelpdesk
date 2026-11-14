<?php
$cfgMenu = [
    'general'     => ['/op/config/general', 'cfg.menu.general', ['general']],
    'departments' => ['/op/config/departments', 'cfg.menu.departments', ['departments', 'department_edit']],
    'email'       => ['/op/config/email', 'cfg.menu.email', ['email', 'email_edit']],
    'canned'      => ['/op/config/canned', 'cfg.menu.canned', ['canned', 'canned_edit']],
    'operators'   => ['/op/config/operators', 'cfg.menu.operators', ['operators', 'operator_edit']],
];
?>
<nav class="cfg-menu" aria-label="<?= t('cfg.title') ?>">
    <h2><?= t('cfg.title') ?></h2>
    <?php foreach ($cfgMenu as [$url, $label, $pages]): ?>
        <a href="<?= e($url) ?>" class="<?= in_array($cfgPage ?? '', $pages, true) ? 'active' : '' ?>"><?= t($label) ?></a>
    <?php endforeach; ?>
</nav>
