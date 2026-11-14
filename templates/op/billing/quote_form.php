<?php $isNew = $quote === null; $ticket = $ticket ?? null; ?>
<p><a href="<?= $ticket ? '/op/ticket/' . (int) $ticket['id'] : '/op/billing/quotes' ?>">← <?= t($ticket ? 'tasks.back_ticket' : 'quote.back') ?></a></p>
<h1><?= $isNew ? t('quote.new_title') : t('quote.edit_title', ['id' => $quote['id']]) ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="layout-side">
    <form method="post" action="<?= $isNew ? '/op/billing/quotes' : '/op/billing/quotes/' . (int) $quote['id'] ?>" class="form card">
        <?= \Bhelpdesk\Csrf::field() ?>
        <?php if ($ticket): ?>
            <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">
            <p class="task-ticket-ref">🎫 <?= t('quote.linked_ticket') ?> <a href="/op/ticket/<?= (int) $ticket['id'] ?>">#<?= e($ticket['number']) ?> · <?= e($ticket['subject']) ?></a></p>
        <?php elseif (!$isNew && !empty($quote['ticket_number'])): ?>
            <p class="task-ticket-ref">🎫 <?= t('quote.linked_ticket') ?> <?= $op['role'] === 'billing' ? '#' . e($quote['ticket_number']) . ' · ' . e((string) $quote['ticket_subject']) : '<a href="/op/ticket/' . (int) $quote['ticket_id'] . '">#' . e($quote['ticket_number']) . ' · ' . e((string) $quote['ticket_subject']) . '</a>' ?></p>
        <?php endif; ?>
        <div class="row-2">
            <label><?= t('inv.f_customer') ?>
                <input type="email" name="customer_email" required maxlength="190" value="<?= e($values['customer_email']) ?>"
                       list="quote-customer-list" data-customer-lookup="quote-customer-list" data-name-field="quote-customer-name"
                       placeholder="<?= t('inv.f_customer_ph') ?>" autocomplete="off">
                <datalist id="quote-customer-list"></datalist>
            </label>
            <label><?= t('inv.f_customer_name') ?>
                <input type="text" id="quote-customer-name" value="<?= e($values['customer_name']) ?>" readonly tabindex="-1" class="readonly">
            </label>
        </div>
        <label><?= t('inv.f_text') ?>
            <textarea name="description" rows="6" required maxlength="5000"><?= e($values['description']) ?></textarea>
        </label>
        <div class="row-3">
            <label><?= t('inv.f_amount') ?>
                <input type="text" name="amount" required inputmode="decimal" value="<?= e($values['amount_input']) ?>" placeholder="0,00">
            </label>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= t($isNew ? 'quote.create' : 'cfg.save_short') ?></button>
        </div>
    </form>

    <?php if (!$isNew): ?>
    <aside class="card">
        <dl class="meta">
            <dt>ID</dt><dd><?= (int) $quote['id'] ?></dd>
            <?php if (!empty($quote['ticket_number'])): ?><dt><?= t('tasks.col_ticket') ?></dt><dd><?= $op['role'] === 'billing' ? '#' . e($quote['ticket_number']) : '<a href="/op/ticket/' . (int) $quote['ticket_id'] . '">#' . e($quote['ticket_number']) . '</a>' ?></dd><?php endif; ?>
            <dt><?= t('inv.created_by') ?></dt><dd><?= e((string) ($quote['created_by_name'] ?? '—')) ?> · <?= e(fmt_date((string) $quote['created_at'])) ?></dd>
        </dl>
        <?php if (in_array($op['role'], ['admin', 'billing'], true) || (int) $quote['created_by'] === (int) $op['id']): ?>
        <form method="post" action="/op/billing/quotes/<?= (int) $quote['id'] ?>/delete" class="inline">
            <?= \Bhelpdesk\Csrf::field() ?>
            <button class="btn btn-small btn-danger" type="submit" data-confirm="<?= t('quote.confirm_delete', ['id' => $quote['id']]) ?>"><?= t('quote.delete') ?></button>
        </form>
        <?php endif; ?>
    </aside>
    <?php endif; ?>
</div>
