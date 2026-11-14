<?php $isSelf = (int) $target['id'] === (int) $op['id']; ?>
<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <p><a href="/op/config/operators">← <?= t('cfg.back') ?></a></p>
        <h1><?= t('ops.edit') ?>: <?= e($target['username']) ?></h1>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="card narrow-left">
            <form method="post" action="/op/config/operators/<?= (int) $target['id'] ?>" class="form" autocomplete="off">
                <?= \Bhelpdesk\Csrf::field() ?>
                <label><?= t('ops.username') ?> <input type="text" value="<?= e($target['username']) ?>" disabled></label>
                <div class="row-2">
                    <label><?= t('ops.name') ?> <input type="text" name="name" required maxlength="100" value="<?= e($target['name']) ?>"></label>
                    <label><?= t('ops.email') ?> <input type="email" name="email" required value="<?= e($target['email']) ?>"></label>
                </div>
                <label><?= t('ops.role') ?>
                    <select name="role">
                        <option value="agent" <?= $target['role'] === 'agent' ? 'selected' : '' ?>><?= t('role.agent') ?></option>
                        <option value="admin" <?= $target['role'] === 'admin' ? 'selected' : '' ?>><?= t('role.admin') ?></option>
                    </select>
                </label>
                <?php if ($isSelf): ?>
                    <input type="hidden" name="active" value="1">
                <?php else: ?>
                    <label class="check"><input type="checkbox" name="active" value="1" <?= $target['active'] ? 'checked' : '' ?>> <?= t('ops.active') ?></label>
                <?php endif; ?>
                <label><?= t('ops.new_password') ?>
                    <input type="password" name="password" minlength="12" autocomplete="new-password">
                </label>
                <button class="btn btn-primary" type="submit"><?= t('cfg.save_short') ?></button>
            </form>
        </div>
    </div>
</div>
