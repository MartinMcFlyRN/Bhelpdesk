<div class="ticket-head">
    <div>
        <h1>#<?= e($ticket['number']) ?> · <?= e($ticket['subject']) ?></h1>
        <p class="muted"><?= t('ticket.opened_on', ['date' => fmt_date($ticket['created_at'])]) ?>
            <?php if ($ticket['department_name']): ?> · <?= e($ticket['department_name']) ?><?php endif; ?>
            · <?= t('ticket.last_update', ['date' => fmt_date($ticket['updated_at'])]) ?></p>
        <?php if ($participants): ?>
            <p class="muted participants-line"><?= t('ticket.participants') ?>
                <?= e(implode(', ', array_merge([$ticket['customer_name']], array_column($participants, 'name')))) ?></p>
        <?php endif; ?>
    </div>
    <span class="badge status-<?= e($ticket['status']) ?>"><?= e(status_label($ticket['status'])) ?></span>
</div>

<div class="thread">
<?php foreach ($messages as $m): ?>
    <?php if ($m['author_type'] === 'system') continue; ?>
    <?php $mine = $m['author_type'] === 'customer' && (int) ($m['customer_id'] ?? $ticket['customer_id']) === (int) $ticket['viewer_id']; ?>
    <article class="msg msg-<?= e($m['author_type']) ?><?= $m['author_type'] === 'customer' && !$mine ? ' msg-other-customer' : '' ?>">
        <header>
            <?php if ($m['author_type'] === 'operator'): ?>
                <span class="msg-role msg-role-support"><?= t('ticket.badge_support') ?></span>
            <?php elseif ($mine): ?>
                <span class="msg-role msg-role-you"><?= t('ticket.badge_you') ?></span>
            <?php else: ?>
                <span class="msg-role msg-role-other"><?= t('ticket.badge_other') ?></span>
            <?php endif; ?>
            <strong><?php if ($m['author_type'] !== 'operator'): ?><?= e($m['customer_name'] ?? $ticket['customer_name']) ?><?php elseif (config('hide_operator_name', false) || empty($m['operator_name'])): ?><?= t('ticket.staff_operator') ?><?php else: ?><?= e($m['operator_name']) ?> (<?= t('ticket.support') ?>)<?php endif; ?></strong>
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

<p class="ticket-foot">
<?php if (!empty($me)): ?>
    <a href="/customer/tickets">← <?= t('ticket.back_mine') ?></a>
<?php else: ?>
    <span class="muted"><?= t('ticket.via_link', ['days' => \Bhelpdesk\AccessLinks::days()]) ?></span>
    <a href="/customer/login"><?= t('ticket.login_all') ?></a>
<?php endif; ?>
</p>
