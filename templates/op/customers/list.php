<?php
$qs = static function (array $over) use ($q, $page): string {
    $p = array_filter(array_merge(['q' => $q, 'page' => $page], $over), static fn ($v) => $v !== '' && $v !== 1 && $v !== null);
    return '/op/customers' . ($p ? '?' . http_build_query($p) : '');
};
?>
<div class="page-head">
    <h1><?= t('cust.title') ?> <span class="count-pill"><?= (int) $total ?></span></h1>
    <a class="btn btn-primary" href="/op/customers/new">+ <?= t('cust.new') ?></a>
</div>

<form method="get" action="/op/customers" class="filters">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('cust.search') ?>">
    <button class="btn" type="submit"><?= t('dash.filter') ?></button>
    <?php if ($q !== ''): ?><a class="btn btn-link" href="/op/customers"><?= t('cust.clear') ?></a><?php endif; ?>
</form>

<table class="table">
    <thead><tr>
        <th><?= t('cust.name') ?></th><th><?= t('cust.company') ?></th><th><?= t('cust.email') ?></th><th><?= t('cust.phone') ?></th>
        <th><?= t('cust.tickets') ?></th><th><?= t('cust.last_activity') ?></th>
    </tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="6" class="muted center"><?= t('cust.empty') ?></td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $c): ?>
        <tr>
            <td><a href="/op/customers/<?= (int) $c['id'] ?>"><strong><?= e($c['name']) ?></strong></a></td>
            <td><?= e($c['company'] ?? '') ?: '<span class="muted">—</span>' ?></td>
            <td><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></td>
            <td><?= $c['phone'] ? '<a href="tel:' . e(preg_replace('/[^0-9+]/', '', $c['phone'])) . '">' . e($c['phone']) . '</a>' : '<span class="muted">—</span>' ?></td>
            <td>
                <?= (int) $c['ticket_count'] ?>
                <?php if ((int) $c['open_count'] > 0): ?><span class="badge status-open"><?= t('cust.open_n', ['n' => (int) $c['open_count']]) ?></span><?php endif; ?>
            </td>
            <td><?= fmt_date($c['last_activity'] ?? $c['created_at']) ?></td>
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
