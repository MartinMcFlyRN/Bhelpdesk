<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <h1><?= t('cfg.menu.departments') ?></h1>
        <p class="muted"><?= t('dep.hint') ?></p>

        <table class="table">
            <thead><tr>
                <th><?= t('dep.name') ?></th><th><?= t('dep.order') ?></th><th><?= t('dep.state') ?></th><th><?= t('dep.tickets') ?></th><th><?= t('dep.signature') ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php if (!$departments): ?>
                <tr><td colspan="6" class="muted center"><?= t('dep.empty') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($departments as $d): ?>
                <tr class="<?= $d['active'] ? '' : 'disabled' ?>">
                    <td><a href="/op/config/departments/<?= (int) $d['id'] ?>"><?= e($d['name']) ?></a></td>
                    <td><?= (int) $d['sort_order'] ?></td>
                    <td><?= $d['active'] ? t('dep.active') : t('dep.inactive') ?></td>
                    <td><?= (int) $d['ticket_count'] ?></td>
                    <td><?= trim((string) ($d['signature'] ?? '')) !== '' ? '✓' : '<span class="muted">—</span>' ?></td>
                    <td class="right"><a class="btn btn-small" href="/op/config/departments/<?= (int) $d['id'] ?>"><?= t('cfg.edit') ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="card">
            <h2><?= t('dep.new') ?></h2>
            <?php if ($errors): ?>
                <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="/op/config/departments" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <div class="row-2 narrow-right">
                    <label><?= t('dep.name') ?> <input type="text" name="name" required maxlength="100" value="<?= e(old('name')) ?>"></label>
                    <label><?= t('dep.order') ?> <input type="number" name="sort_order" value="<?= e(old('sort_order', '0')) ?>" min="-999" max="999"></label>
                </div>
                <button class="btn btn-primary" type="submit"><?= t('dep.create') ?></button>
            </form>
        </div>
    </div>
</div>
