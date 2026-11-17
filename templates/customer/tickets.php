<div class="page-head">
    <h1><?= t('mytickets.title') ?> <span class="count-pill"><?= count($tickets) ?></span></h1>
    <a class="btn btn-primary" href="/customer/new">+ <?= t('nav.open_ticket') ?></a>
</div>

<?php if (!$tickets): ?>
    <div class="card center-text">
        <p class="muted"><?= t('mytickets.empty') ?></p>
        <p><a class="btn btn-primary" href="/customer/new"><?= t('home.new.title') ?></a></p>
    </div>
<?php else: ?>
<?php
$open = array_values(array_filter($tickets, static fn ($tk) => $tk['status'] !== 'closed'));
$closed = array_values(array_filter($tickets, static fn ($tk) => $tk['status'] === 'closed'));
$card = static function (array $tk): void { ?>
    <a class="card card-link ticket-card<?= $tk['status'] === 'closed' ? ' is-closed' : '' ?>" href="/customer/ticket/<?= e($tk['number']) ?>">
        <div class="ticket-card-main">
            <span class="ticket-card-number">#<?= e($tk['number']) ?></span>
            <strong><?= e($tk['subject']) ?></strong>
            <small class="muted">
                <?= t('ticket.opened_on', ['date' => fmt_date($tk['created_at'])]) ?>
                · <?= t('ticket.last_update', ['date' => fmt_date($tk['updated_at'])]) ?>
                <?= $tk['department_name'] ? ' · ' . e($tk['department_name']) : '' ?>
            </small>
        </div>
        <div class="ticket-card-side">
            <span class="badge status-<?= e($tk['status']) ?>"><?= e(status_label($tk['status'])) ?></span>
            <?php if ($tk['role'] === 'cc'): ?><span class="badge"><?= t('mytickets.cc') ?></span><?php endif; ?>
        </div>
    </a>
<?php }; ?>
<h2 class="ticket-list-title"><?= t('mytickets.open') ?> <span class="count-pill"><?= count($open) ?></span></h2>
<?php if ($open): ?>
<div class="ticket-cards"><?php foreach ($open as $tk) { $card($tk); } ?></div>
<?php else: ?>
<p class="muted"><?= t('mytickets.no_open') ?></p>
<?php endif; ?>

<?php if ($closed): ?>
<h2 class="ticket-list-title" id="closed"><?= t('mytickets.closed') ?> <span class="count-pill"><?= count($closed) ?></span></h2>
<div class="ticket-cards"><?php foreach ($closed as $tk) { $card($tk); } ?></div>
<?php endif; ?>
<?php endif; ?>
