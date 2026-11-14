<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <h1><?= t('cfg.menu.templates') ?></h1>
        <p class="muted"><?= t('tpl.hint') ?></p>

        <?php foreach (['customer', 'operator'] as $group): ?>
            <h2 class="tpl-group"><?= t('tpl.group_' . $group) ?></h2>
            <table class="table">
                <thead><tr><th><?= t('tpl.col_email') ?></th><th><?= t('tpl.col_subject') ?></th><th><?= t('tpl.col_state') ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($items[$group] as $code => $tpl): ?>
                    <tr>
                        <td><a href="/op/config/email/templates/<?= e($code) ?>"><strong><?= t('tpl.' . $code) ?></strong></a><br><small class="muted"><?= t('tpl.' . $code . '.when') ?></small></td>
                        <td class="muted"><?= e($tpl['subject']) ?></td>
                        <td><?= $tpl['custom'] ? '<span class="badge badge-main">' . t('tpl.custom') . '</span>' : '<span class="badge">' . t('tpl.default') . '</span>' ?></td>
                        <td class="right"><a class="btn btn-small" href="/op/config/email/templates/<?= e($code) ?>"><?= t('cfg.edit') ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    </div>
</div>
