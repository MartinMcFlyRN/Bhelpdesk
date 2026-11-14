<h1><?= t('profile.title') ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="profile-layout">
    <div class="card profile-card">
        <span class="avatar avatar-lg" aria-hidden="true"><?= e(initials($me['name'])) ?></span>
        <strong><?= e($me['name']) ?></strong>
        <span class="muted"><?= e($me['username']) ?></span>
        <span class="badge"><?= t('role.' . $me['role']) ?></span>
        <dl class="meta">
            <dt><?= t('ops.last_login') ?></dt><dd><?= fmt_date($me['last_login_at']) ?></dd>
            <dt>IP</dt><dd><?= e($me['last_login_ip'] ?? '—') ?></dd>
            <dt><?= t('ops.departments') ?></dt>
            <dd><?= $me['role'] === 'admin' ? t('ops.all_departments') : e(implode(', ', \Bhelpdesk\Operators::departmentNames()[(int) $me['id']] ?? ['—'])) ?></dd>
        </dl>
    </div>

    <form method="post" action="/op/profile" class="form card" autocomplete="off">
        <?= \Bhelpdesk\Csrf::field() ?>
        <h2><?= t('profile.details') ?></h2>
        <div class="row-2">
            <label><?= t('ops.name') ?> <input type="text" name="name" required maxlength="100" value="<?= e($form['name']) ?>"></label>
            <label><?= t('ops.email') ?> <input type="email" name="email" required maxlength="190" value="<?= e($form['email']) ?>"></label>
        </div>

        <h2><?= t('profile.change_password') ?></h2>
        <p class="muted hint"><?= t('profile.password_hint') ?></p>
        <div class="row-2">
            <label><?= t('profile.new_password') ?> <input type="password" name="new_password" minlength="12" autocomplete="new-password"></label>
            <label><?= t('profile.confirm_password') ?> <input type="password" name="confirm_password" minlength="12" autocomplete="new-password"></label>
        </div>

        <label class="current-pwd"><?= t('profile.current_password') ?>
            <input type="password" name="current_password" autocomplete="current-password">
            <small class="muted hint"><?= t('profile.current_hint') ?></small>
        </label>

        <button class="btn btn-primary" type="submit"><?= t('cfg.save_short') ?></button>
    </form>
</div>
