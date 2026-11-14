<?php
use Bhelpdesk\Tasks;
$base = $mine ? '/op/tasks/mine' : '/op/tasks';
$qs = static fn (array $over): string => $base . '?' . http_build_query(array_filter(array_merge(['state' => $filters['state'], 'q' => $filters['q']], $over), static fn ($v) => $v !== '' && $v !== null));
?>
<div class="page-head">
    <h1><?= t($mine ? 'tasks.mine_title' : 'tasks.all_title') ?></h1>
    <div class="page-head-actions">
        <a class="btn btn-primary" href="/op/tasks/new">+ <?= t('tasks.new') ?></a>
    </div>
</div>

<nav class="tabs">
    <?php foreach (['open', 'todo', 'scheduled', 'done', 'all'] as $st): ?>
        <a href="<?= e($qs(['state' => $st, 'page' => null])) ?>" class="<?= $filters['state'] === $st ? 'active' : '' ?>"><?= t('tasks.tab_' . $st) ?></a>
    <?php endforeach; ?>
    <form method="get" action="<?= e($base) ?>" class="tabs-search">
        <input type="hidden" name="state" value="<?= e($filters['state']) ?>">
        <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= t('tasks.search') ?>">
    </form>
</nav>

<table class="table tasks-table">
    <thead><tr><th><?= t('tasks.col_title') ?></th><th><?= t('tasks.col_date') ?></th><th><?= t('tasks.col_assignee') ?></th><th><?= t('tasks.col_state') ?></th><th><?= t('tasks.col_created_by') ?></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="5" class="muted center"><?= t('tasks.empty') ?></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $k): $state = Tasks::state($k); ?>
        <tr class="task-row task-<?= e($state) ?>">
            <td><a href="/op/tasks/<?= (int) $k['id'] ?>"><strong><?= e($k['title']) ?></strong></a>
                <?php if (!empty($k['ticket_number'])): ?><br><small>🎫 <a href="/op/ticket/<?= (int) $k['ticket_id'] ?>">#<?= e($k['ticket_number']) ?></a> <span class="muted"><?= e(mb_strimwidth((string) $k['ticket_subject'], 0, 60, '…')) ?></span></small><?php endif; ?></td>
            <td class="nowrap"><?= e(Tasks::dueLabel($k)) ?></td>
            <td><?= e(Tasks::assigneeLabel($k)) ?></td>
            <td><span class="task-state task-state-<?= e($state) ?>"><?= t('tasks.state_' . $state) ?></span>
                <?php if ($state === 'done'): ?><br><small class="muted"><?= e((string) ($k['done_by_name'] ?? '—')) ?> · <?= e(fmt_date((string) $k['done_at'])) ?></small><?php endif; ?></td>
            <td class="muted"><?= e((string) ($k['created_by_name'] ?? '—')) ?></td>
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
