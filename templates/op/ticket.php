<?php use Bhelpdesk\Tickets; ?>
<div class="ticket-head">
    <div>
        <h1>#<?= e($ticket['number']) ?> · <?= e($ticket['subject']) ?></h1>
        <p class="muted"><a href="/op/customers/<?= (int) $ticket['customer_id'] ?>"><strong><?= e($ticket['customer_name']) ?></strong></a><?= !empty($ticket['customer_company']) ? ' · ' . e($ticket['customer_company']) : '' ?> &lt;<a href="mailto:<?= e($ticket['customer_email']) ?>"><?= e($ticket['customer_email']) ?></a>&gt;
            <?php if (!empty($ticket['phone'])): ?> · <?= t('opticket.phone') ?> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $ticket['phone'])) ?>"><?= e($ticket['phone']) ?></a><?php endif; ?>
            · <?= t('opticket.opened', ['date' => fmt_date($ticket['created_at']), 'ip' => $ticket['created_ip'] ?? '?']) ?></p>
    </div>
    <div>
        <span class="badge prio-<?= e($ticket['priority']) ?>"><?= e(priority_label($ticket['priority'])) ?></span>
        <span class="badge status-<?= e($ticket['status']) ?>"><?= e(status_label($ticket['status'])) ?></span>
    </div>
</div>

<div class="layout-side">
    <div>
        <div class="thread">
        <?php foreach ($messages as $m): ?>
            <?php if ($m['author_type'] === 'system'): ?>
                <div class="msg-system"><?= e(system_message($m['body'])) ?> · <?= fmt_date($m['created_at']) ?></div>
                <?php continue; ?>
            <?php endif; ?>
            <article class="msg msg-<?= e($m['author_type']) ?><?= $m['is_internal'] ? ' msg-internal' : '' ?>">
                <header>
                    <strong><?= $m['author_type'] === 'operator' ? e($m['operator_name'] ?? __('opticket.operator')) : e($m['customer_name'] ?? $ticket['customer_name']) ?></strong>
                    <?php if ($m['is_internal']): ?><span class="badge"><?= t('opticket.internal_badge') ?></span><?php endif; ?>
                    <span class="muted"><?= fmt_date($m['created_at']) ?><?= $m['ip'] ? ' · ' . e($m['ip']) : '' ?></span>
                </header>
                <div class="body"><?= nl2br_e($m['body']) ?></div>
                <?php
                $files = $attachments[(int) $m['id']] ?? [];
                $fileUrl = static fn (int $id): string => '/op/file/' . $id;
                require BHD_ROOT . '/templates/attachments.php';
                ?>
            </article>
        <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="reply-head">
                <h2><?= t('opticket.reply_customer') ?></h2>
                <?php if (!empty($canned)): ?>
                <label class="canned-pick">
                    <span class="sr-only"><?= t('canned.insert') ?></span>
                    <select data-canned-target="reply-message" hidden>
                        <option value=""><?= t('canned.insert') ?>…</option>
                        <?php foreach ($canned as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" data-body="<?= e($c['rendered']) ?>"><?= e($c['title']) ?><?= $c['department_name'] ? ' · ' . e($c['department_name']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php endif; ?>
            </div>
            <form method="post" action="/op/ticket/<?= (int) $ticket['id'] ?>/reply" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <textarea name="message" id="reply-message" rows="7" required></textarea>
                <?php if ($signature !== ''): ?>
                <details class="signature-preview">
                    <summary><?= t('opticket.signature_added', ['department' => $ticket['department_name'] ?? '']) ?></summary>
                    <pre><?= e($signature) ?></pre>
                </details>
                <?php endif; ?>
                <div class="row-inline">
                    <label><?= t('opticket.status_after') ?>
                        <select name="status">
                            <?php foreach (Tickets::STATUSES as $k): ?>
                                <option value="<?= e($k) ?>" <?= $k === 'answered' ? 'selected' : '' ?>><?= e(status_label($k)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit"><?= t('opticket.send_reply') ?></button>
                </div>
            </form>
        </div>

        <div class="card card-internal">
            <h2><?= t('opticket.internal_title') ?></h2>
            <form method="post" action="/op/ticket/<?= (int) $ticket['id'] ?>/reply" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <input type="hidden" name="internal" value="1">
                <input type="hidden" name="status" value="<?= e($ticket['status']) ?>">
                <textarea name="message" rows="3" required placeholder="<?= t('opticket.internal_hint') ?>"></textarea>
                <button class="btn" type="submit"><?= t('opticket.add_note') ?></button>
            </form>
        </div>
    </div>

    <aside class="side-stack">
        <?php require BHD_ROOT . '/templates/op/ticket_customers.php'; ?>
        <div class="card">
        <h2><?= t('opticket.manage') ?></h2>
        <form method="post" action="/op/ticket/<?= (int) $ticket['id'] ?>/update" class="form">
            <?= \Bhelpdesk\Csrf::field() ?>
            <label><?= t('col.status') ?>
                <select name="status">
                    <?php foreach (Tickets::STATUSES as $k): ?>
                        <option value="<?= e($k) ?>" <?= $k === $ticket['status'] ? 'selected' : '' ?>><?= e(status_label($k)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= t('col.priority') ?>
                <select name="priority">
                    <?php foreach (Tickets::PRIORITIES as $k): ?>
                        <option value="<?= e($k) ?>" <?= $k === $ticket['priority'] ? 'selected' : '' ?>><?= e(priority_label($k)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= t('col.department') ?>
                <select name="department_id">
                    <option value="0">—</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === (int) $ticket['department_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= t('col.assignee') ?>
                <select name="assigned_to">
                    <option value="0"><?= t('opticket.nobody') ?></option>
                    <?php foreach ($operators as $o): ?>
                        <option value="<?= (int) $o['id'] ?>" <?= (int) $o['id'] === (int) $ticket['assigned_to'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit"><?= t('opticket.save') ?></button>
        </form>
        <dl class="meta">
            <dt><?= t('opticket.created') ?></dt><dd><?= fmt_date($ticket['created_at']) ?></dd>
            <dt><?= t('opticket.updated_at') ?></dt><dd><?= fmt_date($ticket['updated_at']) ?></dd>
            <?php if ($ticket['closed_at']): ?><dt><?= t('opticket.closed_at') ?></dt><dd><?= fmt_date($ticket['closed_at']) ?></dd><?php endif; ?>
        </dl>
        <?php if (!empty($ticket['customer_notes'])): ?>
            <div class="customer-notes">
                <strong><?= t('cust.notes') ?></strong>
                <p><?= nl2br_e($ticket['customer_notes']) ?></p>
                <a href="/op/customers/<?= (int) $ticket['customer_id'] ?>"><?= t('cfg.edit') ?></a>
            </div>
        <?php endif; ?>
        </div>
    </aside>
</div>
