<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <p><a href="/op/config/departments">← <?= t('cfg.back') ?></a></p>
        <h1><?= t('dep.edit') ?>: <?= e($dep['name']) ?></h1>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="card narrow-left">
            <form method="post" action="/op/config/departments/<?= (int) $dep['id'] ?>" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <input type="hidden" name="format" value="text">
                <div class="row-2 narrow-right">
                    <label><?= t('dep.name') ?> <input type="text" name="name" required maxlength="100" value="<?= e($dep['name']) ?>"></label>
                    <label><?= t('dep.order') ?> <input type="number" name="sort_order" value="<?= (int) $dep['sort_order'] ?>" min="-999" max="999"></label>
                </div>
                <label class="check"><input type="checkbox" name="active" value="1" <?= $dep['active'] ? 'checked' : '' ?>> <?= t('dep.active_label') ?></label>
                <small class="muted hint field-help"><?= t('dep.active_help') ?></small>
                <label><?= t('dep.send_identity') ?>
                    <select name="send_identity_id">
                        <option value="0"><?= t('dep.send_identity_auto') ?></option>
                        <?php foreach ($identities as $idn): ?>
                            <option value="<?= (int) $idn['id'] ?>" <?= (int) ($dep['send_identity_id'] ?? 0) === (int) $idn['id'] ? 'selected' : '' ?>><?= e($idn['name'] . ' <' . $idn['email'] . '>') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted hint"><?= t('dep.send_identity_help') ?></small>
                </label>
                <label><?= t('dep.signature') ?>
                    <textarea name="signature" rows="6" data-editor="signature" placeholder="<?= t('dep.signature_placeholder') ?>"><?= e($dep['signature'] ?? '') ?></textarea>
                    <small class="muted hint"><?= t('dep.signature_help') ?></small>
                </label>
                <?php require BHD_ROOT . '/templates/op/config/_placeholders.php'; ?>
                <button class="btn btn-primary" type="submit"><?= t('cfg.save_short') ?></button>
            </form>
        </div>

        <div class="card narrow-left" id="links">
            <h2><?= t('dlinks.title') ?></h2>
            <p class="muted hint"><?= t('dlinks.help') ?></p>
            <?php if ($linkErrors): ?>
                <div class="alert alert-error"><ul><?php foreach ($linkErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <form method="post" action="/op/config/departments/<?= (int) $dep['id'] ?>/links" class="form">
                <?= \Bhelpdesk\Csrf::field() ?>
                <div class="dlinks-rows">
                    <div class="dlinks-head"><span><?= t('dlinks.name') ?></span><span><?= t('dlinks.url') ?></span><span></span></div>
                    <?php $rows = array_merge($links, array_fill(0, 2, ['name' => '', 'url' => ''])); ?>
                    <?php foreach ($rows as $i => $l): ?>
                    <div class="dlinks-row">
                        <input type="text" name="links[<?= $i ?>][name]" value="<?= e((string) ($l['name'] ?? '')) ?>" maxlength="100" placeholder="<?= t('dlinks.name_ph') ?>" aria-label="<?= t('dlinks.name') ?>">
                        <input type="text" name="links[<?= $i ?>][url]" value="<?= e((string) ($l['url'] ?? '')) ?>" maxlength="500" placeholder="https://…" aria-label="<?= t('dlinks.url') ?>" inputmode="url" autocomplete="off" spellcheck="false">
                        <?php if (($l['name'] ?? '') !== '' || ($l['url'] ?? '') !== ''): ?>
                            <button type="button" class="btn btn-small btn-link dlinks-remove" data-clear-row title="<?= t('dlinks.remove') ?>" aria-label="<?= t('dlinks.remove') ?>">✕</button>
                        <?php else: ?><span></span><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <small class="muted hint"><?= t('dlinks.more_rows') ?></small>
                <button class="btn btn-primary" type="submit"><?= t('dlinks.save') ?></button>
            </form>
        </div>

        <div class="card narrow-left danger-zone">
            <h2><?= t('dep.delete') ?></h2>
            <?php if ((int) $dep['ticket_count'] > 0): ?>
                <p class="muted"><?= t('dep.cannot_delete', ['count' => (int) $dep['ticket_count']]) ?></p>
            <?php else: ?>
                <form method="post" action="/op/config/departments/<?= (int) $dep['id'] ?>/delete" class="inline">
                    <?= \Bhelpdesk\Csrf::field() ?>
                    <button class="btn btn-danger" type="submit" data-confirm="<?= t('dep.confirm_delete', ['name' => $dep['name']]) ?>"><?= t('dep.delete') ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
