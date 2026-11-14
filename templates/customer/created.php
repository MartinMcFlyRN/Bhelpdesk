<div class="card narrow">
    <h1><?= t('created.title') ?></h1>
    <p><?= t('created.intro') ?></p>
    <p class="ticket-number">#<?= e($number) ?></p>

    <h2><?= t('created.link_title') ?></h2>
    <p><?= t('created.link_text', ['days' => $days]) ?></p>
    <p><input type="text" readonly class="mono" value="<?= e($link) ?>" aria-label="<?= t('created.link_title') ?>"></p>
    <p class="muted hint"><?= t('created.link_hint') ?></p>

    <p class="actions">
        <a class="btn btn-primary" href="/customer/ticket/<?= e($number) ?>"><?= t('created.go') ?></a>
        <?php if (!empty($me)): ?>
            <a class="btn" href="/customer/tickets"><?= t('nav.my_tickets_customer') ?></a>
        <?php else: ?>
            <a class="btn" href="/customer/login"><?= t('created.login') ?></a>
        <?php endif; ?>
    </p>
</div>
