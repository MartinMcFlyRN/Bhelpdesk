<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <p><a href="/op/config/departments">← <?= t('cfg.back') ?></a></p>
        <h1><?= t('dep.edit') ?>: <?= e($dep['name']) ?></h1>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="card narrow-left">
            <form method="post" action="/op/config/departments/<?= (int) $dep['id'] ?>" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <div class="row-2 narrow-right">
                    <label><?= t('dep.name') ?> <input type="text" name="name" required maxlength="100" value="<?= e($dep['name']) ?>"></label>
                    <label><?= t('dep.order') ?> <input type="number" name="sort_order" value="<?= (int) $dep['sort_order'] ?>" min="-999" max="999"></label>
                </div>
                <label class="check"><input type="checkbox" name="active" value="1" <?= $dep['active'] ? 'checked' : '' ?>> <?= t('dep.active_label') ?></label>
                <small class="muted hint field-help"><?= t('dep.active_help') ?></small>
                <button class="btn btn-primary" type="submit"><?= t('cfg.save_short') ?></button>
            </form>
        </div>

        <div class="card narrow-left danger-zone">
            <h2><?= t('dep.delete') ?></h2>
            <?php if ((int) $dep['ticket_count'] > 0): ?>
                <p class="muted"><?= t('dep.cannot_delete', ['count' => (int) $dep['ticket_count']]) ?></p>
            <?php else: ?>
                <form method="post" action="/op/config/departments/<?= (int) $dep['id'] ?>/delete" class="inline">
                    <?= \Bhelpdesk\Csrf::field() ?>
                    <button class="btn btn-danger" type="submit" data-confirm="<?= t('dep.confirm_delete', ['name' => $dep['name']]) ?>"><?= t('dep.delete') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
