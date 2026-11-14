<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <h1><?= t('cfg.menu.canned') ?></h1>
        <p class="muted"><?= t('canned.hint') ?></p>

        <table class="table">
            <thead><tr>
                <th><?= t('canned.title_col') ?></th><th><?= t('canned.department') ?></th><th><?= t('canned.preview') ?></th><th><?= t('dep.state') ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="5" class="muted center"><?= t('canned.empty') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $c): ?>
                <tr class="<?= $c['active'] ? '' : 'disabled' ?>">
                    <td><a href="/op/config/canned/<?= (int) $c['id'] ?>"><strong><?= e($c['title']) ?></strong></a></td>
                    <td><?= $c['department_name'] ? e($c['department_name']) : '<span class="muted">' . t('canned.all_departments') . '</span>' ?></td>
                    <td class="muted canned-preview"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', \Bhelpdesk\Html::toText($c['body'])), 0, 90, '…')) ?></td>
                    <td><?= $c['active'] ? t('dep.active') : t('dep.inactive') ?></td>
                    <td class="right"><a class="btn btn-small" href="/op/config/canned/<?= (int) $c['id'] ?>"><?= t('cfg.edit') ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="card">
            <h2><?= t('canned.new') ?></h2>
            <?php if ($errors): ?>
                <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php $formAction = '/op/config/canned'; require BHD_ROOT . '/templates/op/config/_canned_form.php'; ?>
        </div>
    </div>
</div>
