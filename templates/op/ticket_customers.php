<?php
/**
 * "Customers" card in the operator ticket sidebar.
 * Expects: $ticket, $participants
 */
$tid = (int) $ticket['id'];
?>
<div class="card people-card">
    <h2><?= t('tc.title') ?> <span class="count-pill"><?= 1 + count($participants) ?></span></h2>

    <ul class="people">
        <li>
            <span class="avatar avatar-sm" aria-hidden="true"><?= e(initials($ticket['customer_name'])) ?></span>
            <span class="person">
                <a href="/op/customers/<?= (int) $ticket['customer_id'] ?>"><strong><?= e($ticket['customer_name']) ?></strong></a>
                <small class="muted"><?= e($ticket['customer_email']) ?></small>
            </span>
            <span class="badge badge-main"><?= t('tc.main') ?></span>
            <form method="post" action="/op/ticket/<?= $tid ?>/customers/<?= (int) $ticket['customer_id'] ?>/link" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn-icon btn-icon-link" type="submit" title="<?= t('tc.make_link') ?>" aria-label="<?= t('tc.make_link') ?>">🔗</button>
            </form>
        </li>
        <?php foreach ($participants as $pc): ?>
        <li>
            <span class="avatar avatar-sm avatar-cc" aria-hidden="true"><?= e(initials($pc['name'])) ?></span>
            <span class="person">
                <a href="/op/customers/<?= (int) $pc['id'] ?>"><strong><?= e($pc['name']) ?></strong></a>
                <small class="muted"><?= e($pc['email']) ?></small>
            </span>
            <form method="post" action="/op/ticket/<?= $tid ?>/customers/<?= (int) $pc['id'] ?>/link" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn-icon btn-icon-link" type="submit" title="<?= t('tc.make_link') ?>" aria-label="<?= t('tc.make_link') ?>">🔗</button>
            </form>
            <form method="post" action="/op/ticket/<?= $tid ?>/customers/<?= (int) $pc['id'] ?>/remove" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn-icon" type="submit" title="<?= t('tc.remove') ?>" aria-label="<?= t('tc.remove') ?>"
                        data-confirm="<?= t('tc.confirm_remove', ['name' => $pc['name']]) ?>">×</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>

    <details class="inline-form" <?= ($openForm ?? '') === 'add' ? 'open' : '' ?>>
        <summary>+ <?= t('tc.add') ?></summary>
        <form method="post" action="/op/ticket/<?= $tid ?>/customers" class="form">
            <?= \Bhelpdesk\Csrf::field() ?>
            <label><?= t('tc.email_or_search') ?>
                <input type="email" name="email" required list="cust-list-add" autocomplete="off"
                       data-customer-lookup="cust-list-add" data-name-field="add-name">
            </label>
            <datalist id="cust-list-add"></datalist>
            <label><?= t('cust.name') ?> <span class="optional"><?= t('tc.name_hint') ?></span>
                <input type="text" name="name" id="add-name" maxlength="100">
            </label>
            <button class="btn btn-small btn-primary" type="submit"><?= t('tc.add_button') ?></button>
        </form>
    </details>

    <details class="inline-form" <?= ($openForm ?? '') === 'change' ? 'open' : '' ?>>
        <summary><?= t('tc.change') ?></summary>
        <form method="post" action="/op/ticket/<?= $tid ?>/customer" class="form">
            <?= \Bhelpdesk\Csrf::field() ?>
            <label><?= t('tc.new_main') ?>
                <input type="email" name="email" required list="cust-list-change" autocomplete="off"
                       data-customer-lookup="cust-list-change" data-name-field="change-name">
            </label>
            <datalist id="cust-list-change"></datalist>
            <label><?= t('cust.name') ?> <span class="optional"><?= t('tc.name_hint') ?></span>
                <input type="text" name="name" id="change-name" maxlength="100">
            </label>
            <label class="check"><input type="checkbox" name="keep_previous" value="1" checked> <?= t('tc.keep_previous', ['name' => $ticket['customer_name']]) ?></label>
            <button class="btn btn-small btn-primary" type="submit"><?= t('tc.change_button') ?></button>
        </form>
    </details>
    <p class="muted hint"><?= t('tc.access_hint', ['days' => \Bhelpdesk\AccessLinks::days()]) ?></p>
</div>
