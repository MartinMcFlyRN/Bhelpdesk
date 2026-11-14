<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <div class="page-head">
            <h1><?= t('mi.accounts_title') ?></h1>
            <a class="btn btn-primary" href="/op/config/email/new">+ <?= t('mi.new') ?></a>
        </div>
        <p class="muted"><?= t('mi.hint') ?></p>

        <table class="table">
            <thead><tr>
                <th><?= t('mi.identity') ?></th><th><?= t('mi.receives_for') ?></th><th><?= t('mi.sends_for') ?></th>
                <th>SMTP</th><th>POP3</th><th></th>
            </tr></thead>
            <tbody>
            <?php if (!$items): ?>
                <tr><td colspan="6" class="muted center"><?= t('mi.empty') ?></td></tr>
            <?php endif; ?>
            <?php foreach ($items as $i): ?>
                <tr class="<?= $i['active'] ? '' : 'disabled' ?>">
                    <td><a href="/op/config/email/<?= (int) $i['id'] ?>"><strong><?= e($i['email']) ?></strong></a><br><small class="muted"><?= e($i['name']) ?></small></td>
                    <td><?= e($i['department_name']) ?></td>
                    <td><?= $i['sending_for'] ? e($i['sending_for']) : '<span class="muted">—</span>' ?></td>
                    <td class="mono-list"><?= $i['smtp_host'] !== '' ? e($i['smtp_host'] . ':' . $i['smtp_port']) . ' <small class="muted">' . e(strtoupper($i['smtp_security'])) . '</small>' : '<span class="muted">—</span>' ?></td>
                    <td class="mono-list">
                        <?php if ($i['pop_enabled']): ?>
                            <?= e($i['pop_host'] . ':' . $i['pop_port']) ?>
                            <br><small class="muted"><?= $i['pop_keep'] === 'days' ? t('mi.keep_days_short', ['n' => (int) $i['pop_keep_days']]) : t('mi.keep_' . $i['pop_keep'] . '_short') ?></small>
                            <?php if ($i['pop_status']): ?>
                                <br><small class="<?= str_starts_with($i['pop_status'], 'OK') ? 'text-ok' : 'text-err' ?>" title="<?= e(fmt_date($i['pop_checked_at'])) ?>"><?= e(mb_strimwidth($i['pop_status'], 0, 60, '…')) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted"><?= t('mi.pop_off') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="right"><a class="btn btn-small" href="/op/config/email/<?= (int) $i['id'] ?>"><?= t('cfg.edit') ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <form method="post" action="/op/config/email/default" class="card form narrow-left">
            <?= \Bhelpdesk\Csrf::field() ?>
            <h2><?= t('mi.default_title') ?></h2>
            <p class="muted hint"><?= t('mi.default_hint') ?></p>
            <div class="row-inline left">
                <label class="grow"><span class="sr-only"><?= t('mi.default_title') ?></span>
                    <select name="default_identity">
                        <option value="0"><?= t('mi.default_none') ?></option>
                        <?php foreach ($options as $o): ?>
                            <option value="<?= (int) $o['id'] ?>" <?= (int) $o['id'] === $default ? 'selected' : '' ?>><?= e($o['name'] . ' <' . $o['email'] . '>') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn" type="submit"><?= t('cfg.save_short') ?></button>
            </div>
        </form>
    </div>
</div>
