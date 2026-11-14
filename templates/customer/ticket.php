<div class="ticket-head">
    <div>
        <h1>#<?= e($ticket['number']) ?> · <?= e($ticket['subject']) ?></h1>
        <p class="muted">Aperto il <?= fmt_date($ticket['created_at']) ?>
            <?php if ($ticket['department_name']): ?> · <?= e($ticket['department_name']) ?><?php endif; ?>
            · Ultimo aggiornamento <?= fmt_date($ticket['updated_at']) ?></p>
    </div>
    <span class="badge status-<?= e($ticket['status']) ?>"><?= e(status_label($ticket['status'])) ?></span>
</div>

<div class="thread">
<?php foreach ($messages as $m): ?>
    <?php if ($m['author_type'] === 'system') continue; ?>
    <article class="msg msg-<?= e($m['author_type']) ?>">
        <header>
            <strong><?= $m['author_type'] === 'operator' ? e($m['operator_name'] ?? 'Supporto') . ' (Supporto)' : e($ticket['customer_name']) ?></strong>
            <span class="muted"><?= fmt_date($m['created_at']) ?></span>
        </header>
        <div class="body"><?= nl2br_e($m['body']) ?></div>
    </article>
<?php endforeach; ?>
</div>

<?php if ($ticket['status'] !== 'closed' || config('allow_reopen', true)): ?>
<div class="card">
    <h2><?= $ticket['status'] === 'closed' ? 'Rispondi (il ticket verrà riaperto)' : 'Rispondi' ?></h2>
    <form method="post" action="/customer/ticket/<?= e($ticket['number']) ?>/reply" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <textarea name="message" rows="5" required></textarea>
        <button class="btn btn-primary" type="submit">Invia</button>
    </form>
</div>
<?php else: ?>
    <p class="muted">Il ticket è chiuso. Per una nuova richiesta <a href="/customer/new">apri un ticket</a>.</p>
<?php endif; ?>

<form method="post" action="/customer/logout" class="inline">
    <?= \Bhelpdesk\Csrf::field() ?>
    <button class="btn btn-link" type="submit">Esci da questo ticket</button>
</form>
