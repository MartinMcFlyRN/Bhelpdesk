<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <p><a href="/op/config/email/templates">← <?= t('cfg.back') ?></a></p>
        <h1><?= t('tpl.' . $code) ?></h1>
        <p class="muted"><?= t('tpl.' . $code . '.when') ?>
            <?php if ($tpl['custom']): ?> · <?= t('tpl.modified', ['date' => fmt_date((string) $tpl['updated_at']), 'who' => (string) ($tpl['updated_by'] ?? '—')]) ?><?php else: ?> · <?= t('tpl.using_default') ?><?php endif; ?>
        </p>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="tpl-layout">
            <form method="post" action="/op/config/email/templates/<?= e($code) ?>" class="form card">
                <?= \Bhelpdesk\Csrf::field() ?>
                <input type="hidden" name="format" value="text">
                <label><?= t('tpl.subject') ?>
                    <input type="text" name="subject" id="tpl-subject" required maxlength="255" value="<?= e($values['subject']) ?>">
                </label>
                <?php if (str_starts_with($code, 'customer_') && !in_array($code, ['customer_password_setup', 'customer_password_reset'], true)): ?>
                    <small class="muted hint"><?= t('tpl.subject_number_hint') ?></small>
                <?php endif; ?>
                <label><?= t('tpl.body') ?>
                    <textarea name="body" id="tpl-body" rows="16" data-editor="full"><?= e($values['body']) ?></textarea>
                </label>
                <?php if (in_array($code, ['customer_opened', 'customer_reply'], true)): ?>
                    <small class="muted hint"><?= t('tpl.marker_hint') ?></small>
                <?php endif; ?>
                <div class="form-actions">
                    <button class="btn btn-primary" type="submit"><?= t('cfg.save_short') ?></button>
                </div>
            </form>

            <aside class="card tpl-vars">
                <h2><?= t('tpl.vars_title') ?></h2>
                <p class="muted hint"><?= t('tpl.vars_hint') ?></p>
                <ul>
                    <?php foreach ($vars as $v): ?>
                        <li><button type="button" class="chip" data-insert-var="{<?= e($v) ?>}" data-target="tpl-body" data-subject="tpl-subject"><code>{<?= e($v) ?>}</code></button>
                            <small class="muted"><?= t('tpl.var.' . $v) ?></small></li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        </div>

        <div class="card narrow-left">
            <h2><?= t('tpl.test_title') ?></h2>
            <p class="muted hint"><?= t('tpl.test_hint', ['email' => (string) (\Bhelpdesk\Auth::user()['email'] ?? '')]) ?></p>
            <form method="post" action="/op/config/email/templates/<?= e($code) ?>/test" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn" type="submit"><?= t('tpl.test_send') ?></button>
            </form>
        </div>

        <?php if ($tpl['custom']): ?>
        <div class="card narrow-left">
            <h2><?= t('tpl.reset_title') ?></h2>
            <details class="tpl-default"><summary><?= t('tpl.show_default') ?></summary>
                <p><strong><?= e($defaults['subject']) ?></strong></p>
                <div class="rich"><?= \Bhelpdesk\Html::sanitize($defaults['body']) ?></div>
            </details>
            <form method="post" action="/op/config/email/templates/<?= e($code) ?>/reset" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn btn-danger" type="submit" data-confirm="<?= t('tpl.reset_confirm') ?>"><?= t('tpl.reset') ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
