<?php $isNew = $customer === null; ?>
<p><a href="/op/customers">← <?= t('cust.back') ?></a></p>
<div class="page-head">
    <h1><?= $isNew ? t('cust.new') : e($customer['name']) ?></h1>
    <?php if (!$isNew): ?>
        <span class="page-head-actions">
            <span class="muted"><?= t('cust.since', ['date' => fmt_date($customer['created_at'])]) ?></span>
            <a class="btn btn-primary btn-small" href="/op/tickets/new?customer=<?= (int) $customer['id'] ?>">+ <?= t('nt.button') ?></a>
        </span>
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
    <aside class="side-stack">
    <div class="card account-card">
        <h2><?= t('cust.account') ?></h2>
        <?php if (!empty($customer['password_hash'])): ?>
            <p><span class="badge status-answered"><?= t('cust.account_active') ?></span></p>
            <dl class="meta">
                <dt><?= t('ops.last_login') ?></dt><dd><?= fmt_date($customer['last_login_at']) ?><?= $customer['last_login_ip'] ? ' · ' . e($customer['last_login_ip']) : '' ?></dd>
            </dl>
        <?php else: ?>
            <p><span class="badge status-on_hold"><?= t('cust.account_none') ?></span></p>
            <p class="muted hint"><?= t('cust.account_none_hint') ?></p>
        <?php endif; ?>
        <form method="post" action="/op/customers/<?= (int) $customer['id'] ?>/password-link">
            <?= \Bhelpdesk\Csrf::field() ?>
            <button class="btn btn-small" type="submit"><?= empty($customer['password_hash']) ? t('cust.send_setup') : t('cust.send_reset') ?></button>
        </form>
    </div>
    <div class="card">
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
    </div>
    <div class="card">
        <div class="row-between">
            <h2><?= t('inv.title') ?> <span class="count-pill"><?= count($invoices) ?></span></h2>
            <a class="btn btn-small" href="/op/billing/invoices/new?customer=<?= (int) $customer['id'] ?>">+ <?= t('inv.new') ?></a>
        </div>
        <?php if (!$invoices): ?>
            <p class="muted"><?= t('inv.empty') ?></p>
        <?php else: ?>
            <ul class="ticket-mini">
            <?php foreach ($invoices as $inv): ?>
                <li>
                    <a href="/op/billing/invoices/<?= (int) $inv['id'] ?>"><strong><?= (int) $inv['id'] ?></strong> <?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $inv['description']), 0, 50, '…')) ?></a>
                    <div>
                        <strong><?= e(\Bhelpdesk\Invoices::money((float) $inv['amount'])) ?></strong>
                        <span class="badge"><?= t('inv.rec_' . $inv['recurrence']) ?></span>
                        <span class="inv-toggle small <?= $inv['issued'] ? 'on' : '' ?>"><?= $inv['issued'] ? t('inv.state_issued') : t('inv.state_todo') ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <div class="card">
        <div class="row-between">
            <h2><?= t('quote.title') ?> <span class="count-pill"><?= count($quotes) ?></span></h2>
            <a class="btn btn-small" href="/op/billing/quotes/new?customer=<?= (int) $customer['id'] ?>">+ <?= t('quote.new') ?></a>
        </div>
        <?php if (!$quotes): ?>
            <p class="muted"><?= t('quote.empty') ?></p>
        <?php else: ?>
            <ul class="ticket-mini">
            <?php foreach ($quotes as $qt): ?>
                <li>
                    <a href="/op/billing/quotes/<?= (int) $qt['id'] ?>"><strong><?= (int) $qt['id'] ?></strong> <?= e(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $qt['description']), 0, 50, '…')) ?></a>
                    <div><strong><?= e(\Bhelpdesk\Invoices::money((float) $qt['amount'])) ?></strong> <small class="muted"><?= e(fmt_date((string) $qt['created_at'])) ?></small></div>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    </aside>
    <?php endif; ?>
</div>
