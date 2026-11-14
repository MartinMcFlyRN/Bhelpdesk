<?php
$qs = static function (array $over) use ($filters): string {
    $q = array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== 0 && $v !== null);
    return '/op/' . ($q ? '?' . http_build_query($q) : '');
};
$openTotal = $counts['open'] + $counts['answered'] + $counts['on_hold'];
?>
<div class="tabs">
    <a class="<?= $filters['status'] === '' ? 'active' : '' ?>" href="<?= e($qs(['status' => '', 'page' => ''])) ?>">Attivi <span class="count"><?= $openTotal ?></span></a>
    <?php foreach (STATUS_LABELS as $k => $label): ?>
        <a class="<?= $filters['status'] === $k ? 'active' : '' ?>" href="<?= e($qs(['status' => $k, 'page' => ''])) ?>"><?= e($label) ?> <span class="count"><?= $counts[$k] ?></span></a>
    <?php endforeach; ?>
    <a class="<?= $filters['status'] === 'all' ? 'active' : '' ?>" href="<?= e($qs(['status' => 'all', 'page' => ''])) ?>">Tutti</a>
</div>

<form method="get" action="/op/" class="filters">
    <input type="hidden" name="status" value="<?= e($filters['status']) ?>">
    <input type="search" name="q" placeholder="Numero, oggetto, email, nome…" value="<?= e($filters['q']) ?>">
    <select name="assigned">
        <option value="">Tutti gli operatori</option>
        <option value="me" <?= $filters['assigned'] === 'me' ? 'selected' : '' ?>>Assegnati a me</option>
        <option value="none" <?= $filters['assigned'] === 'none' ? 'selected' : '' ?>>Non assegnati</option>
    </select>
    <select name="department">
        <option value="0">Tutti i reparti</option>
        <?php foreach ($departments as $d): ?>
            <option value="<?= (int) $d['id'] ?>" <?= $filters['department'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filtra</button>
</form>

<table class="table">
    <thead>
    <tr><th>Numero</th><th>Oggetto</th><th>Cliente</th><th>Reparto</th><th>Priorità</th><th>Stato</th><th>Assegnato</th><th>Aggiornato</th></tr>
    </thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="8" class="muted center">Nessun ticket.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $t): ?>
        <tr>
            <td><a href="/op/ticket/<?= (int) $t['id'] ?>">#<?= e($t['number']) ?></a></td>
            <td><a href="/op/ticket/<?= (int) $t['id'] ?>"><?= e($t['subject']) ?></a></td>
            <td><?= e($t['customer_name']) ?><br><small class="muted"><?= e($t['customer_email']) ?></small></td>
            <td><?= e($t['department_name'] ?? '—') ?></td>
            <td><span class="badge prio-<?= e($t['priority']) ?>"><?= e(priority_label($t['priority'])) ?></span></td>
            <td><span class="badge status-<?= e($t['status']) ?>"><?= e(status_label($t['status'])) ?></span></td>
            <td><?= e($t['assignee_name'] ?? '—') ?></td>
            <td><?= fmt_date($t['updated_at']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php $pages = (int) ceil($total / $perPage); if ($pages > 1): ?>
<nav class="pager">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= e($qs(['page' => $i])) ?>"><?= $i ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
