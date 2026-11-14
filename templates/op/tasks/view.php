<?php use Bhelpdesk\Tasks; ?>
<p><a href="/op/tasks">← <?= t('tasks.back') ?></a></p>
<div class="page-head">
    <h1><?= e($task['title']) ?></h1>
    <span class="task-state task-state-<?= e($state) ?>"><?= t('tasks.state_' . $state) ?></span>
</div>

<div class="layout-side">
    <div>
        <div class="card task-detail task-<?= e($state) ?>">
            <?php if (!empty($task['description'])): ?>
                <div class="rich"><?= \Bhelpdesk\Html::sanitize((string) $task['description']) ?></div>
            <?php else: ?>
                <p class="muted"><?= t('tasks.no_description') ?></p>
            <?php endif; ?>
        </div>

        <?php if ($state !== 'done' && $canComplete): ?>
            <form method="post" action="/op/tasks/<?= (int) $task['id'] ?>/done" class="task-done-form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn btn-primary btn-big" type="submit">✓ <?= t('tasks.mark_done') ?></button>
                <?php if ($task['target'] !== 'operator'): ?><small class="muted"><?= t('tasks.done_for_all') ?></small><?php endif; ?>
            </form>
        <?php elseif ($state === 'done'): ?>
            <div class="alert alert-success"><?= t('tasks.done_info', ['who' => (string) ($task['done_by_name'] ?? '—'), 'date' => fmt_date((string) $task['done_at'])]) ?></div>
        <?php endif; ?>
    </div>

    <aside class="card">
        <dl class="meta">
            <dt><?= t('tasks.col_date') ?></dt><dd><?= e(Tasks::dueLabel($task)) ?></dd>
            <dt><?= t('tasks.col_assignee') ?></dt><dd><?= e(Tasks::assigneeLabel($task)) ?></dd>
            <dt><?= t('tasks.col_created_by') ?></dt><dd><?= e((string) ($task['created_by_name'] ?? '—')) ?> · <?= e(fmt_date((string) $task['created_at'])) ?></dd>
            <?php if ($state === 'done'): ?>
                <dt><?= t('tasks.state_done') ?></dt><dd><?= e((string) ($task['done_by_name'] ?? '—')) ?> · <?= e(fmt_date((string) $task['done_at'])) ?></dd>
            <?php endif; ?>
        </dl>
        <div class="task-actions">
            <?php if ($state === 'done' && ($canComplete || $canEdit)): ?>
                <form method="post" action="/op/tasks/<?= (int) $task['id'] ?>/reopen" class="inline">
                    <?= \Bhelpdesk\Csrf::field() ?>
                    <button class="btn btn-small" type="submit"><?= t('tasks.reopen') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canEdit): ?>
                <a class="btn btn-small" href="/op/tasks/<?= (int) $task['id'] ?>/edit"><?= t('cfg.edit') ?></a>
                <form method="post" action="/op/tasks/<?= (int) $task['id'] ?>/delete" class="inline">
                    <?= \Bhelpdesk\Csrf::field() ?>
                    <button class="btn btn-small btn-danger" type="submit" data-confirm="<?= t('tasks.confirm_delete') ?>"><?= t('tasks.delete') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </aside>
</div>
