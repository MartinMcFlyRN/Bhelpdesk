<div class="card narrow auth-card">
    <h1><?= t('login.customer_title') ?></h1>
    <p class="muted"><?= t('login.customer_intro') ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($info): ?><div class="alert alert-success"><?= e($info) ?></div><?php endif; ?>
    <form method="post" action="/customer/login" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label><?= t('new.email') ?>
            <input type="email" name="email" required autocomplete="email" autofocus value="<?= e(old('email')) ?>">
        </label>
        <label><?= t('login.password') ?>
            <input type="password" name="password" autocomplete="current-password">
        </label>
        <button class="btn btn-primary btn-block" type="submit"><?= t('login.submit') ?></button>
    </form>
    <div class="auth-links">
        <a href="/customer/password"><?= t('login.forgot') ?></a>
        <span class="muted">·</span>
        <a href="/customer/password"><?= t('login.first_access') ?></a>
    </div>
    <p class="muted hint auth-hint"><?= t('login.first_access_hint') ?></p>
</div>
