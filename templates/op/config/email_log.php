<?php
$qs = static function (array $over) use ($filters): string {
    return '/op/config/email/log?' . http_build_query(array_filter(array_merge($filters, $over), static fn ($v) => $v !== '' && $v !== 0 && $v !== null));
};
?>
<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <h1><?= t('cfg.menu.email_log') ?></h1>
        <p class="muted"><?= t('mailin.log_hint', ['days' => \Bhelpdesk\MailFetcher::LOG_DAYS]) ?></p>

        <?php if ($identities): ?>
        <div class="mail-boxes">
            <?php foreach ($identities as $i): ?>
                <div class="mail-box">
                    <strong><?= e($i['email']) ?></strong>
                    <?php if (!$i['pop_enabled']): ?>
                        <small class="muted"><?= t('mi.pop_off') ?></small>
                    <?php else: ?>
                        <small class="<?= str_starts_with((string) $i['pop_status'], 'OK') ? 'text-ok' : 'text-err' ?>"><?= $i['pop_checked_at'] ? e(fmt_date((string) $i['pop_checked_at'])) . ' · ' . e((string) $i['pop_status']) : '—' ?></small>
                        <form method="post" action="/op/config/email/<?= (int) $i['id'] ?>/fetch" class="inline">
                            <?= \Bhelpdesk\Csrf::field() ?>
                            <button class="btn btn-small" type="submit"><?= t('mailin.fetch_now') ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="get" action="/op/config/email/log" class="filters">
            <select name="identity">
                <option value=""><?= t('mailin.f_all_identities') ?></option>
                <?php foreach ($identities as $i): ?>
                    <option value="<?= (int) $i['id'] ?>" <?= $filters['identity'] === (int) $i['id'] ? 'selected' : '' ?>><?= e($i['email']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="direction">
                <option value=""><?= t('mailin.f_both') ?></option>
                <option value="in" <?= $filters['direction'] === 'in' ? 'selected' : '' ?>>↓ <?= t('mailin.dir_in') ?></option>
                <option value="out" <?= $filters['direction'] === 'out' ? 'selected' : '' ?>>↑ <?= t('mailin.dir_out') ?></option>
            </select>
            <select name="status">
                <option value=""><?= t('mailin.f_all_results') ?></option>
                <?php foreach (['ticket', 'reply', 'ignored', 'blocked', 'error', 'big', 'sent', 'alert', 'skipped'] as $st): ?>
                    <option value="<?= $st ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>><?= t('mailin.st_' . $st) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= t('mailin.f_search') ?>">
            <button class="btn" type="submit"><?= t('mailin.f_apply') ?></button>
            <?php if (array_filter($filters)): ?><a href="/op/config/email/log"><?= t('mailin.f_reset') ?></a><?php endif; ?>
        </form>

        <p class="muted"><?= t('mailin.f_count', ['n' => $total]) ?></p>
        <?php if (!$rows): ?>
            <p class="muted"><?= t('mailin.log_empty') ?></p>
        <?php else: ?>
        <div class="table-wrap">
            <table class="table mail-log">
                <thead><tr><th><?= t('mailin.col_date') ?></th><th></th><th><?= t('mailin.col_identity') ?></th><th><?= t('mailin.col_address') ?></th><th><?= t('mailin.col_subject') ?></th><th><?= t('mailin.col_result') ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="nowrap"><?= e(fmt_date((string) $row['created_at'])) ?></td>
                        <td title="<?= t($row['direction'] === 'in' ? 'mailin.dir_in' : 'mailin.dir_out') ?>"><?= $row['direction'] === 'in' ? '↓' : '↑' ?></td>
                        <td><small><?= e((string) ($row['identity_email'] ?? '—')) ?></small></td>
                        <td><?= e((string) $row['address']) ?>
                            <?php if ($row['direction'] === 'in' && $row['address'] && $row['status'] !== 'blocked'): ?>
                                <form method="post" action="/op/config/blacklist" class="inline bl-quick">
                                    <?= \Bhelpdesk\Csrf::field() ?>
                                    <input type="hidden" name="patterns" value="<?= e((string) $row['address']) ?>">
                                    <input type="hidden" name="note" value="<?= e(__('bl.from_log', ['subject' => mb_strimwidth((string) $row['subject'], 0, 80, '…')])) ?>">
                                    <input type="hidden" name="back" value="<?= e($qs([])) ?>">
                                    <button type="submit" class="btn-link" title="<?= t('bl.block_sender') ?>" data-confirm="<?= t('bl.block_confirm', ['address' => $row['address']]) ?>">🚫</button>
                                </form>
                            <?php endif; ?></td>
                        <td><?php if (!empty($row['raw_path'])): ?><a href="/op/config/email/source/<?= (int) $row['id'] ?>" target="_blank" rel="noopener" title="<?= t('src.open') ?>"><?= e((string) $row['subject']) ?> ↗</a><?php else: ?><?= e((string) $row['subject']) ?><?php endif; ?></td>
                        <td>
                            <span class="mail-status mail-<?= e($row['status']) ?>"><?= t('mailin.st_' . $row['status']) ?></span>
                            <?php if ($row['number']): ?> <a href="/op/ticket/<?= (int) $row['ticket_id'] ?>">#<?= e($row['number']) ?></a><?php endif; ?>
                            <?php if ($row['note']): ?><br><small class="muted"><?= e((string) $row['note']) ?></small><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pages > 1): ?>
        <nav class="pager">
            <?php for ($n = 1; $n <= $pages; $n++): ?>
                <a class="<?= $n === $page ? 'active' : '' ?>" href="<?= e($qs(['page' => $n])) ?>"><?= $n ?></a>
            <?php endfor; ?>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
