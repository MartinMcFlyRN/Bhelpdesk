<h1><?= t('ops.title') ?></h1>
<table class="table">
    <thead><tr>
        <th><?= t('ops.username') ?></th><th><?= t('ops.name') ?></th><th><?= t('ops.email') ?></th><th><?= t('ops.role') ?></th>
        <th><?= t('ops.last_login') ?></th><th><?= t('ops.state') ?></th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($operators as $o): ?>
        <tr class="<?= $o['active'] ? '' : 'disabled' ?>">
            <td><?= e($o['username']) ?></td>
            <td><?= e($o['name']) ?></td>
            <td><?= e($o['email']) ?></td>
            <td><?= t('role.' . $o['role']) ?></td>
            <td><?= fmt_date($o['last_login_at']) ?><?= $o['last_login_ip'] ? '<br><small class="muted">' . e($o['last_login_ip']) . '</small>' : '' ?></td>
            <td><?= $o['active'] ? t('ops.active') : t('ops.disabled') ?></td>
            <td>
                <?php if ((int) $o['id'] !== (int) $op['id']): ?>
                <form method="post" action="/op/operators/<?= (int) $o['id'] ?>/toggle" class="inline">
                    <?= \Bhelpdesk\Csrf::field() ?>
                    <button class="btn btn-small" type="submit"><?= $o['active'] ? t('ops.disable') : t('ops.enable') ?></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="card narrow">
    <h2><?= t('ops.new') ?></h2>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/op/operators" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <div class="row-2">
            <label><?= t('ops.username') ?> <input type="text" name="username" required value="<?= e(old('username')) ?>"></label>
            <label><?= t('ops.name') ?> <input type="text" name="name" required value="<?= e(old('name')) ?>"></label>
        </div>
        <div class="row-2">
            <label><?= t('ops.email') ?> <input type="email" name="email" required value="<?= e(old('email')) ?>"></label>
            <label><?= t('ops.role') ?>
                <select name="role">
                    <option value="agent"><?= t('role.agent') ?></option>
                    <option value="admin" <?= old('role') === 'admin' ? 'selected' : '' ?>><?= t('role.admin') ?></option>
                </select>
            </label>
        </div>
        <label><?= t('ops.password') ?> <input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
        <button class="btn btn-primary" type="submit"><?= t('ops.create') ?></button>
    </form>
</div>
