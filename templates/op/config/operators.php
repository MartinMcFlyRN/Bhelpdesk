<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <h1><?= t('cfg.menu.operators') ?></h1>

        <table class="table">
            <thead><tr>
                <th><?= t('ops.username') ?></th><th><?= t('ops.name') ?></th><th><?= t('ops.email') ?></th><th><?= t('ops.role') ?></th><th><?= t('ops.departments') ?></th>
                <th><?= t('ops.open_tickets') ?></th><th><?= t('ops.last_login') ?></th><th><?= t('ops.state') ?></th><th></th>
            </tr></thead>
            <tbody>
            <?php foreach ($operators as $o): ?>
                <tr class="<?= $o['active'] ? '' : 'disabled' ?>">
                    <td><a href="/op/config/operators/<?= (int) $o['id'] ?>"><?= e($o['username']) ?></a></td>
                    <td><?= e($o['name']) ?></td>
                    <td><?= e($o['email']) ?></td>
                    <td><?= t('role.' . $o['role']) ?></td>
                    <td class="dep-list">
                        <?php if ($o['role'] === 'billing'): ?>
                            <span class="muted"><?= t('ops.billing_only') ?></span>
                        <?php elseif (!empty($o['all_departments'])): ?>
                            <span class="muted"><?= t('ops.all_departments') ?></span>
                        <?php elseif (!empty($depNames[(int) $o['id']])): ?>
                            <?= e(implode(', ', $depNames[(int) $o['id']])) ?>
                        <?php else: ?>
                            <span class="badge status-on_hold"><?= t('ops.no_departments') ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $o['open_tickets'] ?></td>
                    <td><?= fmt_date($o['last_login_at']) ?><?= $o['last_login_ip'] ? '<br><small class="muted">' . e($o['last_login_ip']) . '</small>' : '' ?></td>
                    <td><?= $o['active'] ? t('ops.active') : t('ops.disabled') ?></td>
                    <td class="right nowrap">
                        <a class="btn btn-small" href="/op/config/operators/<?= (int) $o['id'] ?>"><?= t('cfg.edit') ?></a>
                        <?php if ((int) $o['id'] !== (int) $op['id']): ?>
                        <form method="post" action="/op/config/operators/<?= (int) $o['id'] ?>/toggle" class="inline">
                            <?= \Bhelpdesk\Csrf::field() ?>
                            <button class="btn btn-small" type="submit"><?= $o['active'] ? t('ops.disable') : t('ops.enable') ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="card">
            <h2><?= t('ops.new') ?></h2>
            <?php if ($errors): ?>
                <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="/op/config/operators" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <div class="row-2">
                    <label><?= t('ops.username') ?> <input type="text" name="username" required pattern="[A-Za-z0-9._\-]{3,50}" value="<?= e(old('username')) ?>"></label>
                    <label><?= t('ops.name') ?> <input type="text" name="name" required maxlength="100" value="<?= e(old('name')) ?>"></label>
                </div>
                <div class="row-2">
                    <label><?= t('ops.email') ?> <input type="email" name="email" required value="<?= e(old('email')) ?>"></label>
                    <label><?= t('ops.role') ?>
                        <select name="role">
                            <option value="agent"><?= t('role.agent') ?></option>
                            <option value="admin" <?= old('role') === 'admin' ? 'selected' : '' ?>><?= t('role.admin') ?></option>
                            <option value="billing" <?= old('role') === 'billing' ? 'selected' : '' ?>><?= t('role.billing') ?></option>
                        </select>
                    </label>
                </div>
                <?php $role = old('role', 'agent'); $allDeps = (bool) old('all_departments', ''); require BHD_ROOT . '/templates/op/config/_departments_pick.php'; ?>
                <label><?= t('ops.password') ?> <input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
                <button class="btn btn-primary" type="submit"><?= t('ops.create') ?></button>
            </form>
        </div>
    </div>
</div>
