<div class="ticket-head">
    <div>
        <h1>#<?= e($ticket['number']) ?> · <?= e($ticket['subject']) ?></h1>
        <p class="muted"><?= t('ticket.opened_on', ['date' => fmt_date($ticket['created_at'])]) ?>
            <?php if ($ticket['department_name']): ?> · <?= e($ticket['department_name']) ?><?php endif; ?>
            · <?= t('ticket.last_update', ['date' => fmt_date($ticket['updated_at'])]) ?></p>
    </div>
    <span class="badge status-<?= e($ticket['status']) ?>"><?= e(status_label($ticket['status'])) ?></span>
</div>

<div class="thread">
<?php foreach ($messages as $m): ?>
    <?php if ($m['author_type'] === 'system') continue; ?>
    <article class="msg msg-<?= e($m['author_type']) ?>">
        <header>
            <strong><?= $m['author_type'] === 'operator' ? e($m['operator_name'] ?? __('ticket.support')) . ' (' . t('ticket.support') . ')' : e($ticket['customer_name']) ?></strong>
            <span class="muted"><?= fmt_date($m['created_at']) ?></span>
        </header>
        <div class="body"><?= nl2br_e($m['body']) ?></div>
        <?php
        $files = $attachments[(int) $m['id']] ?? [];
        $fileUrl = static fn (int $id): string => '/customer/ticket/' . rawurlencode($ticket['number']) . '/file/' . $id;
        require BHD_ROOT . '/templates/attachments.php';
        ?>
    </article>
<?php endforeach; ?>
</div>

<?php if ($ticket['status'] !== 'closed' || config('allow_reopen', true)): ?>
<div class="card">
    <h2><?= $ticket['status'] === 'closed' ? t('ticket.reply_reopen') : t('ticket.reply') ?></h2>
    <form method="post" action="/customer/ticket/<?= e($ticket['number']) ?>/reply" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <textarea name="message" rows="5" required></textarea>
        <button class="btn btn-primary" type="submit"><?= t('ticket.send') ?></button>
    </form>
</div>
<?php else: ?>
    <p class="muted"><?= t('ticket.closed_info') ?> <a href="/customer/new"><?= t('ticket.closed_new') ?></a></p>
<?php endif; ?>

<form method="post" action="/customer/logout" class="inline">
    <?= \Bhelpdesk\Csrf::field() ?>
    <button class="btn btn-link" type="submit"><?= t('ticket.leave') ?></button>
</form>
