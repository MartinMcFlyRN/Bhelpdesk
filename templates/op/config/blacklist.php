<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <h1><?= t('cfg.menu.blacklist') ?></h1>
        <p class="muted"><?= t('bl.hint') ?></p>

        <form method="post" action="/op/config/blacklist" class="form card narrow-left">
            <?= \Bhelpdesk\Csrf::field() ?>
            <label><?= t('bl.patterns') ?>
                <textarea name="patterns" rows="4" required placeholder="spam@example.com&#10;example.net"></textarea>
                <small class="muted hint"><?= t('bl.patterns_help') ?></small>
            </label>
            <label><?= t('bl.note') ?> <span class="muted">(<?= t('tasks.optional') ?>)</span>
                <input type="text" name="note" maxlength="255">
            </label>
            <button class="btn btn-primary" type="submit"><?= t('bl.add') ?></button>
        </form>

        <table class="table">
            <thead><tr><th><?= t('bl.col_pattern') ?></th><th><?= t('bl.col_note') ?></th><th class="num"><?= t('bl.col_hits') ?></th><th><?= t('bl.col_added') ?></th><th></th></tr></thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="5" class="muted center"><?= t('bl.empty_list') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $b): ?>
                <tr>
                    <td><strong><?= e($b['pattern']) ?></strong> <span class="badge bl-type"><?= t('bl.type_' . $b['type']) ?></span></td>
                    <td class="muted"><?= e((string) $b['note']) ?></td>
                    <td class="num"><?php if ($b['hits']): ?><a href="/op/config/email/log?status=blocked&amp;q=<?= e(urlencode($b['type'] === 'email' ? $b['pattern'] : '@' . $b['pattern'])) ?>"><?= (int) $b['hits'] ?></a><br><small class="muted"><?= e(fmt_date((string) $b['last_hit_at'])) ?></small><?php else: ?>0<?php endif; ?></td>
                    <td><small><?= e((string) ($b['created_by_name'] ?? '—')) ?><br><span class="muted"><?= e(fmt_date((string) $b['created_at'])) ?></span></small></td>
                    <td class="right">
                        <form method="post" action="/op/config/blacklist/<?= (int) $b['id'] ?>/delete" class="inline">
                            <?= \Bhelpdesk\Csrf::field() ?>
                            <button class="btn btn-small" type="submit" data-confirm="<?= t('bl.delete_confirm', ['pattern' => $b['pattern']]) ?>"><?= t('bl.delete') ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
