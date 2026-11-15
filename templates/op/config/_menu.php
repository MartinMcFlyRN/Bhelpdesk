<?php
// key => [url, label, pages that highlight it, sub-items (same shape)]
$cfgMenu = [
    'general'     => ['/op/config/general', 'cfg.menu.general', ['general']],
    'departments' => ['/op/config/departments', 'cfg.menu.departments', ['departments', 'department_edit']],
    'email'       => ['/op/config/email', 'cfg.menu.email', [], [
        'accounts'  => ['/op/config/email', 'cfg.menu.email_accounts', ['email', 'email_edit']],
        'templates' => ['/op/config/email/templates', 'cfg.menu.templates', ['templates', 'template_edit']],
        'log'       => ['/op/config/email/log', 'cfg.menu.email_log', ['email_log']],
    ]],
    'blacklist'   => ['/op/config/blacklist', 'cfg.menu.blacklist', ['blacklist']],
    'canned'      => ['/op/config/canned', 'cfg.menu.canned', ['canned', 'canned_edit']],
    'operators'   => ['/op/config/operators', 'cfg.menu.operators', ['operators', 'operator_edit']],
];
$cfgActive = static fn (array $pages): bool => in_array($cfgPage ?? '', $pages, true);
?>
<nav class="cfg-menu" aria-label="<?= t('cfg.title') ?>">
    <h2><?= t('cfg.title') ?></h2>
    <?php foreach ($cfgMenu as $cfgItem): ?>
        <?php [$cfgUrl, $cfgLabel, $cfgPages] = $cfgItem; $cfgChildren = $cfgItem[3] ?? []; ?>
        <?php if ($cfgChildren): ?>
            <?php $cfgOpen = (bool) array_filter($cfgChildren, static fn (array $c): bool => $cfgActive($c[2])); ?>
            <div class="cfg-group<?= $cfgOpen ? ' open' : '' ?>">
                <a href="<?= e($cfgUrl) ?>" class="cfg-group-title"><?= t($cfgLabel) ?></a>
                <?php foreach ($cfgChildren as [$cfgSubUrl, $cfgSubLabel, $cfgSubPages]): ?>
                    <a href="<?= e($cfgSubUrl) ?>" class="cfg-sub <?= $cfgActive($cfgSubPages) ? 'active' : '' ?>"><?= t($cfgSubLabel) ?></a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <a href="<?= e($cfgUrl) ?>" class="<?= $cfgActive($cfgPages) ? 'active' : '' ?>"><?= t($cfgLabel) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
