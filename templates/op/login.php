<div class="card narrow">
    <h1><?= t('login.title') ?></h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/op/login" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <label><?= t('login.username') ?>
            <input type="text" name="username" required autocomplete="username" autofocus value="<?= e(old('username')) ?>">
        </label>
        <label><?= t('login.password') ?>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary" type="submit"><?= t('login.submit') ?></button>
    </form>
</div>
