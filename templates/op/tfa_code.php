<div class="card narrow tfa-card">
    <h1><?= t('tfa.title') ?></h1>
    <p class="muted"><?= t('tfa.code_intro', ['name' => $pending['name']]) ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/op/2fa" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <label><?= t('tfa.code') ?>
            <input type="text" name="code" required autofocus autocomplete="one-time-code" inputmode="numeric" maxlength="20" class="tfa-input" placeholder="123456">
        </label>
        <label class="check"><input type="checkbox" name="remember" value="1" checked> <?= t('tfa.remember', ['days' => \Bhelpdesk\TwoFactor::TRUST_DAYS]) ?></label>
        <button class="btn btn-primary" type="submit"><?= t('tfa.verify') ?></button>
    </form>
    <p class="muted hint tfa-help"><?= t('tfa.code_help') ?></p>
    <form method="post" action="/op/logout" class="inline"><?= \Bhelpdesk\Csrf::field() ?><button class="btn-link" type="submit"><?= t('tfa.cancel') ?></button></form>
</div>
