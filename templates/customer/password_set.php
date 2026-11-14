<?php $first = empty($customer['password_hash']); ?>
<div class="card narrow auth-card">
    <h1><?= $first ? t('pw.create_title') : t('pw.reset_title') ?></h1>
    <p class="muted"><?= t($first ? 'pw.create_intro' : 'pw.reset_intro', ['email' => $customer['email']]) ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/customer/password/<?= e($token) ?>" class="form" autocomplete="off">
        <?= \Bhelpdesk\Csrf::field() ?>
        <!-- helps password managers link the password to the right account -->
        <input type="text" name="username" value="<?= e($customer['email']) ?>" autocomplete="username" hidden>
        <label><?= t('pw.new') ?>
            <input type="password" name="password" required minlength="<?= \Bhelpdesk\CustomerAuth::MIN_PASSWORD ?>" autocomplete="new-password" autofocus>
            <small class="muted hint"><?= t('pw.rules', ['min' => \Bhelpdesk\CustomerAuth::MIN_PASSWORD]) ?></small>
        </label>
        <label><?= t('pw.confirm') ?>
            <input type="password" name="confirm" required minlength="<?= \Bhelpdesk\CustomerAuth::MIN_PASSWORD ?>" autocomplete="new-password">
        </label>
        <button class="btn btn-primary btn-block" type="submit"><?= $first ? t('pw.create_submit') : t('pw.reset_submit') ?></button>
    </form>
</div>
