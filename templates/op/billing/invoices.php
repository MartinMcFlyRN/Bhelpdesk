<?php
use Bhelpdesk\Invoices;
$qs = static fn (array $over): string => '/op/billing/invoices?' . http_build_query(array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== null));
$here = $qs([]);
$isBilling = $op['role'] === 'billing';
$canIssue = \Bhelpdesk\Auth::canIssueInvoices($op);
?>
<div class="page-head">
    <h1><?= t('inv.title') ?></h1>
    <div class="page-head-actions">
        <a class="btn btn-primary" href="/op/billing/invoices/new">+ <?= t('inv.new') ?></a>
    </div>
</div>

<nav class="tabs">
    <?php foreach (['todo', 'issued', 'all'] as $st): ?>
        <a href="<?= e($qs(['status' => $st, 'page' => null])) ?>" class="<?= $filters['status'] === $st ? 'active' : '' ?>"><?= t('inv.tab_' . $st) ?></a>
    <?php endforeach; ?>
    <form method="get" action="/op/billing/invoices" class="tabs-search">
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
        <select name="recurrence" class="inline-select" data-autosubmit>
            <option value=""><?= t('inv.all_types') ?></option>
            <?php foreach (Invoices::RECURRENCES as $r): ?>
                <option value="<?= $r ?>" <?= $filters['recurrence'] === $r ? 'selected' : '' ?>><?= t('inv.rec_' . $r) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= t('inv.search') ?>">
    </form>
</nav>

<p class="muted inv-summary"><?= t('inv.summary', ['n' => $total, 'sum' => Invoices::money($sum)]) ?></p>

<table class="table invoices-table">
    <thead><tr>
        <th class="num">ID</th><th><?= t('inv.col_customer') ?></th><th><?= t('inv.col_text') ?></th><th class="num"><?= t('inv.col_amount') ?></th>
        <th><?= t('inv.col_type') ?></th><th><?= t('inv.col_start') ?></th><th><?= t('inv.col_next') ?></th><th><?= t('inv.col_issued') ?></th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="8" class="muted center"><?= t('inv.empty') ?></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $i): $next = Invoices::nextDate($i); $due = !$i['issued'] && $next <= date('Y-m-d'); ?>
        <tr class="<?= $i['issued'] ? 'inv-issued' : ($due ? 'inv-due' : '') ?>">
            <td class="num"><a href="/op/billing/invoices/<?= (int) $i['id'] ?>"><strong><?= (int) $i['id'] ?></strong></a></td>
            <td><?= $isBilling ? '<strong>' . e($i['customer_name']) . '</strong>' : '<a href="/op/customers/' . (int) $i['customer_id'] . '">' . e($i['customer_name']) . '</a>' ?><?= $i['customer_company'] ? '<br><small class="muted">' . e($i['customer_company']) . '</small>' : '' ?></td>
            <td><a href="/op/billing/invoices/<?= (int) $i['id'] ?>" class="inv-text"><?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $i['description']), 0, 90, '…')) ?></a>
                <?php if (!empty($i['ticket_number'])): ?><br><small>🎫 <?= $isBilling ? '#' . e($i['ticket_number']) : '<a href="/op/ticket/' . (int) $i['ticket_id'] . '">#' . e($i['ticket_number']) . '</a>' ?></small><?php endif; ?></td>
            <td class="num nowrap"><strong><?= e(Invoices::money((float) $i['amount'])) ?></strong></td>
            <td><span class="badge inv-rec-<?= e($i['recurrence']) ?>"><?= t('inv.rec_' . $i['recurrence']) ?></span></td>
            <td class="nowrap"><?= e(Invoices::day((string) $i['start_date'])) ?></td>
            <td class="nowrap"><?= e(Invoices::day($next)) ?><?= $due ? ' <span class="inv-due-tag">' . t('inv.due') . '</span>' : '' ?></td>
            <td>
                <?php if ($canIssue): ?>
                <form method="post" action="/op/billing/invoices/<?= (int) $i['id'] ?>/issued" class="inline inv-issued-form">
                    <?= \Bhelpdesk\Csrf::field() ?>
                    <input type="hidden" name="back" value="<?= e($here) ?>">
                    <input type="hidden" name="issued" value="<?= $i['issued'] ? '' : '1' ?>">
                    <button type="submit" class="inv-toggle <?= $i['issued'] ? 'on' : '' ?>" title="<?= t($i['issued'] ? 'inv.click_todo' : 'inv.click_issued') ?>"
                            data-confirm="<?= t($i['issued'] ? 'inv.confirm_todo' : 'inv.confirm_issued', ['id' => $i['id']]) ?>">
                        <?= $i['issued'] ? '☑ ' . t('inv.state_issued') : '☐ ' . t('inv.state_todo') ?>
                    </button>
                </form>
                <?php else: ?>
                    <span class="inv-toggle <?= $i['issued'] ? 'on' : '' ?>" title="<?= t('inv.only_billing') ?>"><?= $i['issued'] ? '☑ ' . t('inv.state_issued') : '☐ ' . t('inv.state_todo') ?></span>
                <?php endif; ?>
                <?php if ($i['issued']): ?><br><small class="muted"><?= e((string) ($i['issued_by_name'] ?? '')) ?> · <?= e(fmt_date((string) $i['issued_at'])) ?></small><?php endif; ?>
            </td>
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
