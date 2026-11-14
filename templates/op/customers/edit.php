<?php $isNew = $customer === null; ?>
<p><a href="/op/customers">← <?= t('cust.back') ?></a></p>
<div class="page-head">
    <h1><?= $isNew ? t('cust.new') : e($customer['name']) ?></h1>
    <?php if (!$isNew): ?>
        <span class="muted"><?= t('cust.since', ['date' => fmt_date($customer['created_at'])]) ?></span>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="layout-side customer-layout">
    <form method="post" action="<?= $isNew ? '/op/customers' : '/op/customers/' . (int) $customer['id'] ?>" class="form card">
        <?= \Bhelpdesk\Csrf::field() ?>
        <div class="row-2">
            <label><?= t('cust.name') ?> <input type="text" name="name" required maxlength="100" value="<?= e($values['name'] ?? '') ?>"></label>
            <label><?= t('cust.company') ?> <span class="optional"><?= t('new.optional') ?></span> <input type="text" name="company" maxlength="150" value="<?= e($values['company'] ?? '') ?>"></label>
        </div>
        <div class="row-2">
            <label><?= t('cust.email') ?> <input type="email" name="email" required maxlength="190" value="<?= e($values['email'] ?? '') ?>"></label>
            <label><?= t('cust.phone') ?> <span class="optional"><?= t('new.optional') ?></span> <input type="tel" name="phone" maxlength="30" value="<?= e($values['phone'] ?? '') ?>"></label>
        </div>
        <label><?= t('cust.language') ?>
            <select name="language">
                <?php foreach ($languages as $code => $lname): ?>
                    <option value="<?= e($code) ?>" <?= $code === ($values['language'] ?? '') ? 'selected' : '' ?>><?= e($lname) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= t('cust.notes') ?> <span class="optional"><?= t('cust.notes_hint') ?></span>
            <textarea name="notes" rows="5" maxlength="5000"><?= e($values['notes'] ?? '') ?></textarea>
        </label>
        <?php if (!$isNew): ?><p class="muted hint"><?= t('cust.email_hint') ?></p><?php endif; ?>
        <button class="btn btn-primary" type="submit"><?= $isNew ? t('cust.create') : t('cfg.save_short') ?></button>
    </form>

    <?php if (!$isNew): ?>
    <aside class="card">
        <h2><?= t('cust.ticket_history') ?> <span class="count-pill"><?= count($tickets) ?></span></h2>
        <?php if (!$tickets): ?>
            <p class="muted"><?= t('cust.no_tickets') ?></p>
        <?php else: ?>
            <ul class="ticket-mini">
            <?php foreach ($tickets as $tk): ?>
                <li>
                    <a href="/op/ticket/<?= (int) $tk['id'] ?>"><strong>#<?= e($tk['number']) ?></strong> <?= e($tk['subject']) ?></a>
                    <div>
                        <span class="badge status-<?= e($tk['status']) ?>"><?= e(status_label($tk['status'])) ?></span>
                        <?php if (($tk['role'] ?? 'main') === 'cc'): ?><span class="badge"><?= t('tc.cc_badge') ?></span><?php endif; ?>
                        <small class="muted"><?= fmt_date($tk['updated_at']) ?><?= $tk['department_name'] ? ' · ' . e($tk['department_name']) : '' ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>
    <?php endif; ?>
</div>
