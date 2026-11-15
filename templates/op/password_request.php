<div class="card narrow">
    <h1><?= t('opw.title') ?></h1>
    <?php if ($sent): ?>
        <div class="alert alert-success"><?= t('opw.sent', ['minutes' => \Bhelpdesk\OperatorPassword::LINK_MINUTES]) ?></div>
    <?php else: ?>
        <p class="muted"><?= t('opw.intro') ?></p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" action="/op/password" class="form">
            <?= \Bhelpdesk\Csrf::field() ?>
            <label><?= t('opw.login') ?>
                <input type="text" name="login" required maxlength="190" autocomplete="username" autofocus>
            </label>
            <button class="btn btn-primary" type="submit"><?= t('opw.submit') ?></button>
        </form>
    <?php endif; ?>
    <p class="auth-links"><a href="/op/login">← <?= t('opw.back') ?></a></p>
</div>
