<div class="card narrow">
    <h1><?= t('opw.set_title') ?></h1>
    <p class="muted"><?= t('opw.set_intro', ['username' => $target['username']]) ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/op/password/<?= e($token) ?>" class="form" autocomplete="off">
        <?= \Bhelpdesk\Csrf::field() ?>
        <input type="text" name="username" value="<?= e($target['username']) ?>" autocomplete="username" hidden>
        <label><?= t('profile.new_password') ?>
            <input type="password" name="password" required minlength="<?= \Bhelpdesk\Operators::MIN_PASSWORD ?>" autocomplete="new-password" autofocus>
            <small class="muted hint"><?= t('opw.rules', ['min' => \Bhelpdesk\Operators::MIN_PASSWORD]) ?></small>
        </label>
        <label><?= t('profile.confirm_password') ?>
            <input type="password" name="confirm" required minlength="<?= \Bhelpdesk\Operators::MIN_PASSWORD ?>" autocomplete="new-password">
        </label>
        <button class="btn btn-primary" type="submit"><?= t('opw.set_submit') ?></button>
    </form>
</div>
