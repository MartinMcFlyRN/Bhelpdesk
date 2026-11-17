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
    <div class="page-head-actions">
        <label class="autorefresh"><?= t('dash.autorefresh') ?>
            <select data-autorefresh aria-label="<?= t('dash.autorefresh') ?>">
                <option value="0">OFF</option>
                <option value="5">5s</option>
                <option value="10">10s</option>
                <option value="30">30s</option>
            </select>
        </label>
        <a class="btn btn-primary" href="/op/tickets/new">+ <?= t('nt.button') ?></a>
    </div>
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

<form method="post" action="/op/tickets/bulk" id="bulk-form" class="bulk-form">
<?= \Bhelpdesk\Csrf::field() ?>
<input type="hidden" name="back" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/op/')) ?>">

<div class="bulk-bar" id="bulk-bar" hidden>
    <span class="bulk-count"><strong data-bulk-count>0</strong> <?= t('bulk.selected') ?></span>
    <button type="button" class="btn btn-small" data-open-dialog="dlg-assign"><?= t('bulk.assign') ?></button>
    <button type="button" class="btn btn-small" data-open-dialog="dlg-status"><?= t('bulk.status') ?></button>
    <button type="button" class="btn btn-small" data-open-dialog="dlg-move"><?= t('bulk.move') ?></button>
    <button type="button" class="btn btn-small" data-open-dialog="dlg-merge" data-min="2"><?= t('bulk.merge') ?></button>
    <button type="button" class="btn btn-small btn-danger-outline" data-open-dialog="dlg-delete"><?= t('bulk.delete') ?></button>
    <button type="button" class="btn btn-link btn-small" data-bulk-clear><?= t('bulk.clear') ?></button>
</div>

<table class="table">
    <thead>
    <tr>
        <th class="col-check"><input type="checkbox" data-bulk-all aria-label="<?= t('bulk.select_all') ?>"></th>
        <th><?= t('col.number') ?></th><th><?= t('col.subject') ?></th><th><?= t('col.customer') ?></th><th><?= t('col.department') ?></th>
        <th><?= t('col.priority') ?></th><th><?= t('col.status') ?></th><th><?= t('col.assignee') ?></th><th><?= t('col.updated') ?></th>
    </tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="9" class="muted center"><?= t('dash.empty') ?></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td class="col-check"><input type="checkbox" name="ids[]" value="<?= (int) $row['id'] ?>" data-bulk-item
                data-number="<?= e($row['number']) ?>" data-subject="<?= e($row['subject']) ?>" data-customer="<?= e($row['customer_name']) ?>"
                aria-label="#<?= e($row['number']) ?>"></td>
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

<!-- assign -->
<dialog id="dlg-assign" class="dialog">
    <h2><?= t('bulk.assign_title') ?></h2>
    <p class="muted"><?= t('bulk.assign_text') ?></p>
    <label><?= t('col.assignee') ?>
        <select name="assign_to">
            <option value=""><?= t('nt.choose') ?></option>
            <?php foreach ($assignOperators as $o): ?>
                <option value="<?= (int) $o['id'] ?>"><?= e($o['name']) ?></option>
            <?php endforeach; ?>
            <option value="none"><?= t('bulk.assign_nobody') ?></option>
        </select>
    </label>
    <label><?= t('bulk.note') ?> <span class="optional"><?= t('bulk.note_hint') ?></span>
        <textarea name="note[assign]" rows="3" maxlength="5000"></textarea>
    </label>
    <div class="dialog-actions">
        <button type="button" class="btn" data-close-dialog><?= t('bulk.cancel') ?></button>
        <button type="submit" class="btn btn-primary" name="action" value="assign"><?= t('bulk.assign_confirm') ?></button>
    </div>
</dialog>

<!-- status -->
<dialog id="dlg-status" class="dialog">
    <h2><?= t('bulk.status_title') ?></h2>
    <p class="muted"><?= t('bulk.status_text') ?></p>
    <label><?= t('col.status') ?>
        <select name="status">
            <option value=""><?= t('nt.choose') ?></option>
            <?php foreach (Tickets::STATUSES as $k): ?>
                <option value="<?= e($k) ?>"><?= e(status_label($k)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?= t('bulk.note') ?> <span class="optional"><?= t('bulk.note_hint') ?></span>
        <textarea name="note[status]" rows="3" maxlength="5000"></textarea>
    </label>
    <div class="dialog-actions">
        <button type="button" class="btn" data-close-dialog><?= t('bulk.cancel') ?></button>
        <button type="submit" class="btn btn-primary" name="action" value="status"><?= t('bulk.status_confirm') ?></button>
    </div>
</dialog>

<!-- move -->
<dialog id="dlg-move" class="dialog">
    <h2><?= t('bulk.move_title') ?></h2>
    <p class="muted"><?= t('bulk.move_text') ?></p>
    <label><?= t('col.department') ?>
        <select name="department">
            <option value=""><?= t('nt.choose') ?></option>
            <?php foreach ($allDepartments as $d): ?>
                <option value="<?= (int) $d['id'] ?>"><?= e($d['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?= t('bulk.note') ?> <span class="optional"><?= t('bulk.note_hint') ?></span>
        <textarea name="note[move]" rows="3" maxlength="5000"></textarea>
    </label>
    <div class="dialog-actions">
        <button type="button" class="btn" data-close-dialog><?= t('bulk.cancel') ?></button>
        <button type="submit" class="btn btn-primary" name="action" value="move"><?= t('bulk.move_confirm') ?></button>
    </div>
</dialog>

<!-- merge -->
<dialog id="dlg-merge" class="dialog">
    <h2><?= t('bulk.merge_title') ?></h2>
    <p class="muted"><?= t('bulk.merge_text') ?></p>
    <div class="merge-targets" data-merge-targets></div>
    <label><?= t('bulk.note') ?> <span class="optional"><?= t('bulk.note_hint_merge') ?></span>
        <textarea name="note[merge]" rows="3" maxlength="5000"></textarea>
    </label>
    <div class="dialog-actions">
        <button type="button" class="btn" data-close-dialog><?= t('bulk.cancel') ?></button>
        <button type="submit" class="btn btn-primary" name="action" value="merge"><?= t('bulk.merge_confirm') ?></button>
    </div>
</dialog>

<!-- delete -->
<dialog id="dlg-delete" class="dialog">
    <h2><?= t('bulk.delete_title') ?></h2>
    <p><?= t('bulk.delete_text') ?></p>
    <ul class="delete-list" data-delete-list></ul>
    <p class="muted small"><?= t('bulk.delete_no_note') ?></p>
    <label class="check"><input type="checkbox" name="confirm" value="1" data-delete-confirm> <?= t('bulk.delete_check') ?></label>
    <div class="dialog-actions">
        <button type="button" class="btn" data-close-dialog><?= t('bulk.cancel') ?></button>
        <button type="submit" class="btn btn-danger" name="action" value="delete" data-delete-submit disabled><?= t('bulk.delete_confirm') ?></button>
    </div>
</dialog>
</form>

<?php $pages = (int) ceil($total / $perPage); if ($pages > 1): ?>
<nav class="pager">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
