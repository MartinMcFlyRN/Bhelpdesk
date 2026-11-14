<div class="ticket-head">
    <div>
        <h1>#<?= e($ticket['number']) ?> · <?= e($ticket['subject']) ?></h1>
        <p class="muted"><?= e($ticket['customer_name']) ?> &lt;<?= e($ticket['customer_email']) ?>&gt;
            · aperto il <?= fmt_date($ticket['created_at']) ?> da <?= e($ticket['created_ip'] ?? '?') ?></p>
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
                <div class="msg-system"><?= e($m['body']) ?> · <?= fmt_date($m['created_at']) ?></div>
                <?php continue; ?>
            <?php endif; ?>
            <article class="msg msg-<?= e($m['author_type']) ?><?= $m['is_internal'] ? ' msg-internal' : '' ?>">
                <header>
                    <strong><?= $m['author_type'] === 'operator' ? e($m['operator_name'] ?? 'Operatore') : e($ticket['customer_name']) ?></strong>
                    <?php if ($m['is_internal']): ?><span class="badge">nota interna</span><?php endif; ?>
                    <span class="muted"><?= fmt_date($m['created_at']) ?><?= $m['ip'] ? ' · ' . e($m['ip']) : '' ?></span>
                </header>
                <div class="body"><?= nl2br_e($m['body']) ?></div>
            </article>
        <?php endforeach; ?>
        </div>

        <div class="card">
            <h2>Rispondi al cliente</h2>
            <form method="post" action="/op/ticket/<?= (int) $ticket['id'] ?>/reply" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <textarea name="message" rows="6" required></textarea>
                <div class="row-inline">
                    <label>Stato dopo l'invio
                        <select name="status">
                            <?php foreach (STATUS_LABELS as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $k === 'answered' ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="btn btn-primary" type="submit">Invia risposta</button>
                </div>
            </form>
        </div>

        <div class="card card-internal">
            <h2>Nota interna</h2>
            <form method="post" action="/op/ticket/<?= (int) $ticket['id'] ?>/reply" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <input type="hidden" name="internal" value="1">
                <input type="hidden" name="status" value="<?= e($ticket['status']) ?>">
                <textarea name="message" rows="3" required placeholder="Visibile solo agli operatori"></textarea>
                <button class="btn" type="submit">Aggiungi nota</button>
            </form>
        </div>
    </div>

    <aside class="card">
        <h2>Gestione</h2>
        <form method="post" action="/op/ticket/<?= (int) $ticket['id'] ?>/update" class="form">
            <?= \Bhelpdesk\Csrf::field() ?>
            <label>Stato
                <select name="status">
                    <?php foreach (STATUS_LABELS as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $k === $ticket['status'] ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Priorità
                <select name="priority">
                    <?php foreach (PRIORITY_LABELS as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= $k === $ticket['priority'] ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Reparto
                <select name="department_id">
                    <option value="0">—</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === (int) $ticket['department_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Assegnato a
                <select name="assigned_to">
                    <option value="0">— nessuno —</option>
                    <?php foreach ($operators as $o): ?>
                        <option value="<?= (int) $o['id'] ?>" <?= (int) $o['id'] === (int) $ticket['assigned_to'] ? 'selected' : '' ?>><?= e($o['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-primary" type="submit">Salva</button>
        </form>
        <dl class="meta">
            <dt>Creato</dt><dd><?= fmt_date($ticket['created_at']) ?></dd>
            <dt>Aggiornato</dt><dd><?= fmt_date($ticket['updated_at']) ?></dd>
            <?php if ($ticket['closed_at']): ?><dt>Chiuso</dt><dd><?= fmt_date($ticket['closed_at']) ?></dd><?php endif; ?>
        </dl>
    </aside>
</div>
