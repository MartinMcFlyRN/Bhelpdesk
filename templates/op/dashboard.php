<?php
use Bhelpdesk\Tickets;

$qs = static function (array $over) use ($filters): string {
    $q = array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== 0 && $v !== null);
    return '/op/' . ($q ? '?' . http_build_query($q) : '');
};
$openTotal = $counts['open'] + $counts['answered'] + $counts['on_hold'];
?>
<div class="page-head">
    <h1><?= t('dash.title') ?></h1>
    <a class="btn btn-primary" href="/op/tickets/new">+ <?= t('nt.button') ?></a>
</div>
<div class="tabs">
    <a class="<?= $filters['status'] === '' ? 'active' : '' ?>" href="<?= e($qs(['status' => '', 'page' => ''])) ?>"><?= t('dash.active') ?> <span class="count"><?= $openTotal ?></span></a>
    <?php foreach (Tickets::STATUSES as $k): ?>
        <a class="<?= $filters['status'] === $k ? 'active' : '' ?>" href="<?= e($qs(['status' => $k, 'page' => ''])) ?>"><?= e(status_label($k)) ?> <span class="count"><?= $counts[$k] ?></span></a>
    <?php endforeach; ?>
    <a class="<?= $filters['status'] === 'all' ? 'active' : '' ?>" href="<?= e($qs(['status' => 'all', 'page' => ''])) ?>"><?= t('dash.all') ?></a>
</div>

<form method="get" action="/op/" class="filters">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <input type="search" name="q" placeholder="<?= t('dash.search') ?>" value="<?= e($filters['q']) ?>">
    <select name="assigned">
        <option value=""><?= t('dash.all_operators') ?></option>
        <option value="me" <?= $filters['assigned'] === 'me' ? 'selected' : '' ?>><?= t('dash.assigned_me') ?></option>
        <option value="none" <?= $filters['assigned'] === 'none' ? 'selected' : '' ?>><?= t('dash.unassigned') ?></option>
    </select>
    <select name="department">
        <option value="0"><?= t('dash.all_departments') ?></option>
        <?php foreach ($departments as $d): ?>
            <option value="<?= (int) $d['id'] ?>" <?= $filters['department'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit"><?= t('dash.filter') ?></button>
</form>

<table class="table">
    <thead>
    <tr>
        <th><?= t('col.number') ?></th><th><?= t('col.subject') ?></th><th><?= t('col.customer') ?></th><th><?= t('col.department') ?></th>
        <th><?= t('col.priority') ?></th><th><?= t('col.status') ?></th><th><?= t('col.assignee') ?></th><th><?= t('col.updated') ?></th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="8" class="muted center"><?= t('dash.empty') ?></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><a href="/op/ticket/<?= (int) $row['id'] ?>">#<?= e($row['number']) ?></a></td>
            <td><a href="/op/ticket/<?= (int) $row['id'] ?>"><?= e($row['subject']) ?></a></td>
            <td><a href="/op/customers/<?= (int) $row['customer_id'] ?>"><?= e($row['customer_name']) ?></a><?= !empty($row['customer_company']) ? ' <small class="muted">· ' . e($row['customer_company']) . '</small>' : '' ?><br><small class="muted"><?= e($row['customer_email']) ?><?= !empty($row['phone']) ? ' · ' . e($row['phone']) : '' ?></small></td>
            <td><?= e($row['department_name'] ?? '—') ?></td>
            <td><span class="badge prio-<?= e($row['priority']) ?>"><?= e(priority_label($row['priority'])) ?></span></td>
            <td><span class="badge status-<?= e($row['status']) ?>"><?= e(status_label($row['status'])) ?></span></td>
            <td><?= e($row['assignee_name'] ?? '—') ?></td>
            <td><?= fmt_date($row['updated_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php $pages = (int) ceil($total / $perPage); if ($pages > 1): ?>
<nav class="pager">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
