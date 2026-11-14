<?php
use Bhelpdesk\Invoices;
$qs = static fn (array $over): string => '/op/billing/quotes?' . http_build_query(array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== null));
?>
<div class="page-head">
    <h1><?= t('quote.title') ?></h1>
    <div class="page-head-actions">
        <a class="btn btn-primary" href="/op/billing/quotes/new">+ <?= t('quote.new') ?></a>
    </div>
</div>

<nav class="tabs">
    <a href="/op/billing/quotes" class="active"><?= t('quote.tab_all') ?></a>
    <form method="get" action="/op/billing/quotes" class="tabs-search">
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= t('quote.search') ?>">
    </form>
</nav>

<p class="muted inv-summary"><?= t('quote.summary', ['n' => $total, 'sum' => Invoices::money($sum)]) ?></p>

<table class="table invoices-table">
    <thead><tr>
        <th class="num">ID</th><th><?= t('inv.col_customer') ?></th><th><?= t('inv.col_text') ?></th><th class="num"><?= t('inv.col_amount') ?></th><th><?= t('quote.col_created') ?></th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="5" class="muted center"><?= t('quote.empty') ?></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $q): ?>
        <tr>
            <td class="num"><a href="/op/billing/quotes/<?= (int) $q['id'] ?>"><strong><?= (int) $q['id'] ?></strong></a></td>
            <td><a href="/op/customers/<?= (int) $q['customer_id'] ?>"><?= e($q['customer_name']) ?></a><?= $q['customer_company'] ? '<br><small class="muted">' . e($q['customer_company']) . '</small>' : '' ?></td>
            <td><a href="/op/billing/quotes/<?= (int) $q['id'] ?>" class="inv-text"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $q['description']), 0, 90, '…')) ?></a>
                <?php if (!empty($q['ticket_number'])): ?><br><small>🎫 <a href="/op/ticket/<?= (int) $q['ticket_id'] ?>">#<?= e($q['ticket_number']) ?></a></small><?php endif; ?></td>
            <td class="num nowrap"><strong><?= e(Invoices::money((float) $q['amount'])) ?></strong></td>
            <td class="nowrap"><?= e(fmt_date((string) $q['created_at'])) ?><br><small class="muted"><?= e((string) ($q['created_by_name'] ?? '—')) ?></small></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php $pages = (int) ceil($total / $perPage); if ($pages > 1): ?>
<nav class="pager">
    <?php for ($n = 1; $n <= $pages; $n++): ?>
        <a class="<?= $n === $page ? 'active' : '' ?>" href="<?= e($qs(['page' => $n])) ?>"><?= $n ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
