<div class="card narrow auth-card">
    <h1><?= t('pw.request_title') ?></h1>
    <?php if ($sent): ?>
        <div class="alert alert-success"><?= t('pw.request_sent', ['minutes' => \Bhelpdesk\CustomerAuth::PASSWORD_LINK_MINUTES]) ?></div>
        <p><a href="/customer/login">← <?= t('pw.back_login') ?></a></p>
    <?php else: ?>
        <p class="muted"><?= t('pw.request_intro') ?></p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="/customer/password" class="form">
            <?= \Bhelpdesk\Csrf::field() ?>
            <label><?= t('new.email') ?>
                <input type="email" name="email" required autocomplete="email" autofocus
                       value="<?= e(old('email', (string) (\Bhelpdesk\CustomerAuth::user()['email'] ?? ''))) ?>">
            </label>
            <button class="btn btn-primary btn-block" type="submit"><?= t('pw.request_submit') ?></button>
        </form>
        <div class="auth-links"><a href="/customer/login">← <?= t('pw.back_login') ?></a></div>
    <?php endif; ?>
</div>
