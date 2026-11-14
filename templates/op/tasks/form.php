<?php $isNew = $task === null; $ticket = $ticket ?? null; ?>
<p><a href="<?= $ticket ? '/op/ticket/' . (int) $ticket['id'] : ($isNew ? '/op/tasks' : '/op/tasks/' . (int) $task['id']) ?>">← <?= t($ticket ? 'tasks.back_ticket' : 'tasks.back') ?></a></p>
<h1><?= t($isNew ? 'tasks.new_title' : 'tasks.edit_title') ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" action="<?= $isNew ? '/op/tasks' : '/op/tasks/' . (int) $task['id'] ?>" class="form card task-form">
    <?= \Bhelpdesk\Csrf::field() ?>
    <input type="hidden" name="format" value="text">
    <?php if ($ticket): ?>
        <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
        <p class="task-ticket-ref">🎫 <?= t('tasks.linked_ticket') ?> <a href="/op/ticket/<?= (int) $ticket['id'] ?>">#<?= e($ticket['number']) ?> · <?= e($ticket['subject']) ?></a></p>
    <?php elseif (!$isNew && !empty($task['ticket_number'])): ?>
        <p class="task-ticket-ref">🎫 <?= t('tasks.linked_ticket') ?> <a href="/op/ticket/<?= (int) $task['ticket_id'] ?>">#<?= e($task['ticket_number']) ?> · <?= e((string) $task['ticket_subject']) ?></a></p>
    <?php endif; ?>
    <label><?= t('tasks.f_title') ?>
        <input type="text" name="title" required maxlength="200" value="<?= e($values['title']) ?>" autofocus>
    </label>
    <label><?= t('tasks.f_description') ?> <span class="muted">(<?= t('tasks.optional') ?>)</span>
        <textarea name="description" rows="6" data-editor="full"><?= e($values['description']) ?></textarea>
    </label>
    <div class="row-3">
        <label><?= t('tasks.f_date') ?>
            <input type="date" name="due_date" required value="<?= e($values['due_date']) ?>">
        </label>
        <label><?= t('tasks.f_time') ?> <span class="muted">(<?= t('tasks.optional') ?>)</span>
            <input type="time" name="due_time" value="<?= e($values['due_time']) ?>">
        </label>
        <label><?= t('tasks.f_assign') ?>
            <select name="assign" required>
                <option value="all" <?= $values['assign'] === 'all' ? 'selected' : '' ?>><?= t('tasks.everyone') ?></option>
                <optgroup label="<?= t('tasks.f_departments') ?>">
                    <?php foreach ($departments as $d): ?>
                        <option value="d:<?= (int) $d['id'] ?>" <?= $values['assign'] === 'd:' . $d['id'] ? 'selected' : '' ?>><?= t('tasks.dep_prefix') ?> <?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <optgroup label="<?= t('tasks.f_operators') ?>">
                    <?php foreach ($operators as $o): ?>
                        <option value="o:<?= (int) $o['id'] ?>" <?= $values['assign'] === 'o:' . $o['id'] ? 'selected' : '' ?>><?= e($o['name']) ?><?= (int) $o['id'] === (int) $op['id'] ? ' (' . t('tasks.me') . ')' : '' ?></option>
                    <?php endforeach; ?>
                </optgroup>
            </select>
        </label>
    </div>
    <p class="muted hint"><?= t('tasks.f_hint') ?></p>
    <div class="form-actions">
        <button class="btn btn-primary" type="submit"><?= t($isNew ? 'tasks.create' : 'cfg.save_short') ?></button>
    </div>
</form>
