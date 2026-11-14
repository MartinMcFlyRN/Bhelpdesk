<div class="card narrow auth-card center-text">
    <div class="big-icon" aria-hidden="true">⏱</div>
    <?php if (!empty($password)): ?>
        <h1><?= t('pw.link_invalid_title') ?></h1>
        <p><?= t('pw.link_invalid_text', ['minutes' => \Bhelpdesk\CustomerAuth::PASSWORD_LINK_MINUTES]) ?></p>
        <p><a class="btn btn-primary" href="/customer/password"><?= t('pw.request_submit') ?></a></p>
    <?php else: ?>
        <h1><?= $invalid ? t('link.invalid_title') : t('link.expired_title') ?></h1>
        <p><?= $invalid ? t('link.invalid_text') : t('link.expired_text', ['days' => \Bhelpdesk\AccessLinks::days()]) ?></p>
        <p>
            <?php if (\Bhelpdesk\CustomerAuth::user()): ?>
                <a class="btn btn-primary" href="/customer/tickets"><?= t('ticket.back_mine') ?></a>
            <?php else: ?>
                <a class="btn btn-primary" href="/customer/login"><?= t('link.login') ?></a>
                <a class="btn" href="/customer/password"><?= t('login.first_access') ?></a>
            <?php endif; ?>
        </p>
    <?php endif; ?>
</div>
