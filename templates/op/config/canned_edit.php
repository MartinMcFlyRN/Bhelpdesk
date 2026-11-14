<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <p><a href="/op/config/canned">← <?= t('cfg.back') ?></a></p>
        <h1><?= t('canned.edit') ?>: <?= e($item['title']) ?></h1>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="card narrow-left">
            <?php $formAction = '/op/config/canned/' . (int) $item['id']; require BHD_ROOT . '/templates/op/config/_canned_form.php'; ?>
        </div>

        <div class="card narrow-left danger-zone">
            <h2><?= t('canned.delete') ?></h2>
            <form method="post" action="/op/config/canned/<?= (int) $item['id'] ?>/delete" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn btn-danger" type="submit" data-confirm="<?= t('canned.confirm_delete', ['name' => $item['title']]) ?>"><?= t('canned.delete') ?></button>
            </form>
        </div>
    </div>
</div>
