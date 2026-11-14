<?php use Bhelpdesk\Invoices; $isNew = $invoice === null; ?>
<p><a href="/op/billing/invoices">← <?= t('inv.back') ?></a></p>
<div class="page-head">
    <h1><?= $isNew ? t('inv.new_title') : t('inv.edit_title', ['id' => $invoice['id']]) ?></h1>
    <?php if (!$isNew): ?><span class="inv-toggle <?= $invoice['issued'] ? 'on' : '' ?>"><?= $invoice['issued'] ? '☑ ' . t('inv.state_issued') : '☐ ' . t('inv.state_todo') ?></span><?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="layout-side">
    <form method="post" action="<?= $isNew ? '/op/billing/invoices' : '/op/billing/invoices/' . (int) $invoice['id'] ?>" class="form card">
        <?= \Bhelpdesk\Csrf::field() ?>
        <div class="row-2">
            <label><?= t('inv.f_customer') ?>
                <input type="email" name="customer_email" required maxlength="190" value="<?= e($values['customer_email']) ?>"
                       list="inv-customer-list" data-customer-lookup="inv-customer-list" data-name-field="inv-customer-name"
                       placeholder="<?= t('inv.f_customer_ph') ?>" autocomplete="off">
                <datalist id="inv-customer-list"></datalist>
            </label>
            <label><?= t('inv.f_customer_name') ?>
                <input type="text" id="inv-customer-name" value="<?= e($values['customer_name']) ?>" readonly tabindex="-1" class="readonly">
            </label>
        </div>
        <label><?= t('inv.f_text') ?>
            <textarea name="description" rows="5" required maxlength="5000"><?= e($values['description']) ?></textarea>
        </label>
        <div class="row-3">
            <label><?= t('inv.f_amount') ?>
                <input type="text" name="amount" required inputmode="decimal" value="<?= e($values['amount_input']) ?>" placeholder="0,00">
            </label>
            <label><?= t('inv.f_start') ?>
                <input type="date" name="start_date" required value="<?= e($values['start_date']) ?>">
            </label>
            <label><?= t('inv.f_type') ?>
                <select name="recurrence">
                    <?php foreach (Invoices::RECURRENCES as $r): ?>
                        <option value="<?= $r ?>" <?= $values['recurrence'] === $r ? 'selected' : '' ?>><?= t('inv.rec_' . $r) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="check inv-issued-check"><input type="checkbox" name="issued" value="1" <?= $values['issued'] ? 'checked' : '' ?>> <strong><?= t('inv.f_issued') ?></strong> <span class="muted">— <?= t('inv.f_issued_hint') ?></span></label>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?= t($isNew ? 'inv.create' : 'cfg.save_short') ?></button>
        </div>
    </form>

    <?php if (!$isNew): ?>
    <aside class="card">
        <dl class="meta">
            <dt>ID</dt><dd><?= (int) $invoice['id'] ?></dd>
            <dt><?= t('inv.col_next') ?></dt><dd><?= e(Invoices::day(Invoices::nextDate($invoice))) ?></dd>
            <dt><?= t('inv.created_by') ?></dt><dd><?= e((string) ($invoice['created_by_name'] ?? '—')) ?> · <?= e(fmt_date((string) $invoice['created_at'])) ?></dd>
            <?php if ($invoice['issued']): ?><dt><?= t('inv.state_issued') ?></dt><dd><?= e((string) ($invoice['issued_by_name'] ?? '—')) ?> · <?= e(fmt_date((string) $invoice['issued_at'])) ?></dd><?php endif; ?>
        </dl>
        <?php if ($op['role'] === 'admin' || (int) $invoice['created_by'] === (int) $op['id']): ?>
        <form method="post" action="/op/billing/invoices/<?= (int) $invoice['id'] ?>/delete" class="inline">
            <?= \Bhelpdesk\Csrf::field() ?>
            <button class="btn btn-small btn-danger" type="submit" data-confirm="<?= t('inv.confirm_delete', ['id' => $invoice['id']]) ?>"><?= t('inv.delete') ?></button>
        </form>
        <?php endif; ?>
    </aside>
    <?php endif; ?>
</div>
