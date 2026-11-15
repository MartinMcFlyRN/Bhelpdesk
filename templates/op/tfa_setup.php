<?php $uri = \Bhelpdesk\Totp::uri($secret, (string) $pending['username'], (string) config('app_name', 'Bhelpdesk')); ?>
<div class="card tfa-card tfa-setup">
    <h1><?= t('tfa.setup_title') ?></h1>
    <p><?= t('tfa.setup_intro', ['name' => $pending['name']]) ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <ol class="tfa-steps">
        <li><?= t('tfa.step_app') ?></li>
        <li><?= t('tfa.step_scan') ?>
            <div class="tfa-qr" data-otpauth="<?= e($uri) ?>" aria-label="QR code"></div>
            <p class="muted hint"><?= t('tfa.manual') ?></p>
            <code class="tfa-secret"><?= e(\Bhelpdesk\Totp::pretty($secret)) ?></code>
            <p class="muted hint"><?= t('tfa.manual_details', ['account' => $pending['username']]) ?></p>
        </li>
        <li><?= t('tfa.step_confirm') ?>
            <form method="post" action="/op/2fa/setup" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <label><?= t('tfa.code') ?>
                    <input type="text" name="code" required autocomplete="one-time-code" inputmode="numeric" maxlength="7" class="tfa-input" placeholder="123456">
                </label>
                <label class="check"><input type="checkbox" name="remember" value="1" checked> <?= t('tfa.remember', ['days' => \Bhelpdesk\TwoFactor::TRUST_DAYS]) ?></label>
                <button class="btn btn-primary" type="submit"><?= t('tfa.activate') ?></button>
            </form>
        </li>
    </ol>
    <form method="post" action="/op/logout" class="inline"><?= \Bhelpdesk\Csrf::field() ?><button class="btn-link" type="submit"><?= t('tfa.cancel') ?></button></form>
</div>
<script src="/assets/vendor/qrcode-generator-1.4.4/qrcode.js"></script>
