<?php
use Bhelpdesk\Tickets;
// link keeping the other parameters (status filter, page of the other list)
$url = static function (array $over): string {
    $q = array_filter(array_merge(['status' => $_GET['status'] ?? '', 'po' => $_GET['po'] ?? '', 'pc' => $_GET['pc'] ?? ''], $over), static fn ($v) => $v !== '' && $v !== 1 && $v !== '1');
    return '/customer/tickets' . ($q ? '?' . http_build_query($q) : '');
};
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
<?php };
$pager = static function (array $sec, string $anchor) use ($url): void {
    if ($sec['pages'] < 2) {
        return;
    } ?>
    <nav class="pager">
        <?php for ($i = 1; $i <= $sec['pages']; $i++): ?>
            <a class="<?= $i === $sec['page'] ? 'active' : '' ?>" href="<?= e($url([$sec['param'] => $i]) . '#' . $anchor) ?>"><?= $i ?></a>
        <?php endfor; ?>
    </nav>
<?php };
?>
<div class="page-head">
    <h1><?= t('mytickets.title') ?> <span class="count-pill"><?= (int) $count ?></span></h1>
    <a class="btn btn-primary" href="/customer/new">+ <?= t('nav.open_ticket') ?></a>
</div>

<?php if (!$count): ?>
    <div class="card center-text">
        <p class="muted"><?= t('mytickets.empty') ?></p>
        <p><a class="btn btn-primary" href="/customer/new"><?= t('home.new.title') ?></a></p>
    </div>
<?php else: ?>
<nav class="status-filter" aria-label="<?= t('mytickets.filter') ?>">
    <a href="<?= e($url(['status' => '', 'po' => '', 'pc' => ''])) ?>"<?= $status === '' ? ' class="active"' : '' ?>><?= t('mytickets.all') ?> <span class="count-pill"><?= (int) $count ?></span></a>
    <?php foreach (Tickets::STATUSES as $st): ?>
        <a href="<?= e($url(['status' => $st, 'po' => '', 'pc' => ''])) ?>"<?= $status === $st ? ' class="active"' : '' ?>><?= e(status_label($st)) ?> <span class="count-pill"><?= (int) ($counts[$st] ?? 0) ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($status !== 'closed'): ?>
<h2 class="ticket-list-title" id="open"><?= t('mytickets.open') ?> <span class="count-pill"><?= (int) $open['total'] ?></span></h2>
<?php if ($open['rows']): ?>
<div class="ticket-cards"><?php foreach ($open['rows'] as $tk) { $card($tk); } ?></div>
<?php $pager($open, 'open'); ?>
<?php else: ?>
<p class="muted"><?= t('mytickets.no_open') ?></p>
<?php endif; ?>
<?php endif; ?>

<?php if ($status === '' || $status === 'closed'): ?>
<h2 class="ticket-list-title" id="closed"><?= t('mytickets.closed') ?> <span class="count-pill"><?= (int) $closed['total'] ?></span></h2>
<?php if ($closed['rows']): ?>
<div class="ticket-cards"><?php foreach ($closed['rows'] as $tk) { $card($tk); } ?></div>
<?php $pager($closed, 'closed'); ?>
<?php else: ?>
<p class="muted"><?= t('mytickets.no_closed') ?></p>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
