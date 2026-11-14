<?php
use Bhelpdesk\MailIdentities;

$isNew = $item === null;
$sec = static function (string $name, string $current): string {
    $out = '';
    foreach (MailIdentities::SECURITY as $s) {
        $out .= '<option value="' . e($s) . '"' . ($s === $current ? ' selected' : '') . '>' . t('mi.sec_' . $s) . '</option>';
    }
    return $out;
};
$saved = static fn (string $field): bool => !$isNew && !empty($item[$field]);
?>
<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <p><a href="/op/config/email">← <?= t('cfg.back') ?></a></p>
        <h1><?= $isNew ? t('mi.new') : e($item['email']) ?></h1>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if (!empty($test)): ?>
            <div class="alert <?= $test['ok'] ? 'alert-success' : 'alert-error' ?>" id="test">
                <strong><?= $test['what'] === 'pop' ? 'POP3' : 'SMTP' ?>:</strong> <?= e($test['message']) ?>
                <?php if (!empty($test['log'])): ?>
                    <details class="smtp-log"><summary><?= t('mi.test_log') ?></summary><pre><?= e(implode("\n", $test['log'])) ?></pre></details>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= $isNew ? '/op/config/email' : '/op/config/email/' . (int) $item['id'] ?>" class="form" autocomplete="off">
            <?= \Bhelpdesk\Csrf::field() ?>

            <fieldset class="card">
                <legend><?= t('mi.identity') ?></legend>
                <div class="row-2">
                    <label><?= t('mi.email') ?> <input type="email" name="email" required maxlength="190" value="<?= e($values['email']) ?>" placeholder="assistenza@example.com"></label>
                    <label><?= t('mi.name') ?> <input type="text" name="name" required maxlength="100" value="<?= e($values['name']) ?>" placeholder="<?= t('mi.name_placeholder') ?>"></label>
                </div>
                <label><?= t('mi.department') ?>
                    <select name="department_id" required>
                        <option value=""><?= t('nt.choose') ?></option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= (int) $d['id'] ?>" <?= (int) $values['department_id'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="muted hint"><?= t('mi.department_help') ?></small>
                </label>
                <label class="check"><input type="checkbox" name="active" value="1" <?= !empty($values['active']) ? 'checked' : '' ?>> <?= t('mi.active') ?></label>
            </fieldset>

            <fieldset class="card">
                <legend><?= t('mi.smtp') ?></legend>
                <p class="muted hint"><?= t('mi.smtp_hint') ?></p>
                <div class="row-3">
                    <label><?= t('mi.host') ?> <input type="text" name="smtp_host" maxlength="253" value="<?= e($values['smtp_host']) ?>" placeholder="smtp.example.com"></label>
                    <label><?= t('mi.port') ?> <input type="number" name="smtp_port" min="1" max="65535" value="<?= (int) $values['smtp_port'] ?>"></label>
                    <label><?= t('mi.security') ?> <select name="smtp_security" data-port-for="smtp_port" data-ports="465,587,25"><?= $sec('smtp_security', (string) $values['smtp_security']) ?></select></label>
                </div>
                <div class="row-2">
                    <label><?= t('mi.user') ?> <input type="text" name="smtp_user" data-lpignore="true" data-1p-ignore data-bwignore maxlength="190" value="<?= e($values['smtp_user']) ?>" autocomplete="off"></label>
                    <label><?= t('mi.password') ?>
                        <input type="password" name="smtp_pass" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-bwignore placeholder="<?= $saved('smtp_pass') ? t('mi.pass_saved') : '' ?>">
                        <?php if ($saved('smtp_pass')): ?><label class="check small"><input type="checkbox" name="smtp_pass_clear" value="1"> <?= t('mi.pass_clear') ?></label><?php endif; ?>
                    </label>
                </div>
                <label class="check"><input type="checkbox" name="smtp_verify" value="1" <?= !empty($values['smtp_verify']) ? 'checked' : '' ?>> <?= t('mi.verify') ?></label>
            </fieldset>

            <fieldset class="card">
                <legend><?= t('mi.pop') ?></legend>
                <label class="check"><input type="checkbox" name="pop_enabled" value="1" data-toggle="pop-fields" <?= !empty($values['pop_enabled']) ? 'checked' : '' ?>> <?= t('mi.pop_enabled') ?></label>
                <div id="pop-fields">
                    <div class="row-3">
                        <label><?= t('mi.host') ?> <input type="text" name="pop_host" maxlength="253" value="<?= e($values['pop_host']) ?>" placeholder="pop.example.com"></label>
                        <label><?= t('mi.port') ?> <input type="number" name="pop_port" min="1" max="65535" value="<?= (int) $values['pop_port'] ?>"></label>
                        <label><?= t('mi.security') ?> <select name="pop_security" data-port-for="pop_port" data-ports="995,110,110"><?= $sec('pop_security', (string) $values['pop_security']) ?></select></label>
                    </div>
                    <div class="row-2">
                        <label><?= t('mi.user') ?> <input type="text" name="pop_user" data-lpignore="true" data-1p-ignore data-bwignore maxlength="190" value="<?= e($values['pop_user']) ?>" autocomplete="off"></label>
                        <label><?= t('mi.password') ?> <input type="password" name="pop_pass" autocomplete="new-password" data-lpignore="true" data-1p-ignore data-bwignore placeholder="<?= $saved('pop_pass') ? t('mi.pass_saved') : '' ?>"></label>
                    </div>
                    <div class="field">
                        <span class="label"><?= t('mi.keep') ?></span>
                        <label class="check"><input type="radio" name="pop_keep" value="delete" <?= $values['pop_keep'] === 'delete' ? 'checked' : '' ?>> <?= t('mi.keep_delete') ?></label>
                        <label class="check"><input type="radio" name="pop_keep" value="days" <?= $values['pop_keep'] === 'days' ? 'checked' : '' ?>>
                            <?= t('mi.keep_days_before') ?> <input type="number" name="pop_keep_days" class="tiny" min="1" max="3650" value="<?= (int) $values['pop_keep_days'] ?>"> <?= t('mi.keep_days_after') ?></label>
                        <label class="check"><input type="radio" name="pop_keep" value="forever" <?= $values['pop_keep'] === 'forever' ? 'checked' : '' ?>> <?= t('mi.keep_forever') ?></label>
                    </div>
                    <label class="check"><input type="checkbox" name="pop_verify" value="1" <?= !empty($values['pop_verify']) ? 'checked' : '' ?>> <?= t('mi.verify') ?></label>
                    <p class="muted hint"><?= t('mi.pop_hint') ?></p>
                </div>
            </fieldset>

            <button class="btn btn-primary" type="submit"><?= $isNew ? t('mi.create') : t('cfg.save_short') ?></button>
        </form>

        <?php if (!$isNew): ?>
        <div class="card narrow-left">
            <h2><?= t('mi.test_title') ?></h2>
            <p class="muted hint"><?= t('mi.test_hint') ?></p>
            <form method="post" action="/op/config/email/<?= (int) $item['id'] ?>/test" class="form row-inline left">
                <?= \Bhelpdesk\Csrf::field() ?>
                <input type="hidden" name="what" value="smtp">
                <label class="grow"><?= t('mi.test_to') ?>
                    <input type="email" name="to" value="<?= e(\Bhelpdesk\Auth::user()['email'] ?? '') ?>">
                </label>
                <button class="btn" type="submit" <?= $item['smtp_host'] === '' ? 'disabled' : '' ?>><?= t('mi.test_smtp') ?></button>
            </form>
            <form method="post" action="/op/config/email/<?= (int) $item['id'] ?>/test" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <input type="hidden" name="what" value="pop">
                <button class="btn" type="submit" <?= !$item['pop_enabled'] ? 'disabled' : '' ?>><?= t('mi.test_pop') ?></button>
            </form>
        </div>

        <div class="card" id="log">
            <div class="row-between">
                <h2><?= t('mailin.log_title') ?></h2>
                <form method="post" action="/op/config/email/<?= (int) $item['id'] ?>/fetch" class="inline">
                    <?= \Bhelpdesk\Csrf::field() ?>
                    <button class="btn" type="submit" <?= !$item['pop_enabled'] ? 'disabled' : '' ?>><?= t('mailin.fetch_now') ?></button>
                </form>
            </div>
            <p class="muted hint"><?= t('mailin.log_hint') ?></p>
            <?php if (!$log): ?>
                <p class="muted"><?= t('mailin.log_empty') ?></p>
            <?php else: ?>
            <div class="table-wrap">
                <table class="table mail-log">
                    <thead><tr><th><?= t('mailin.col_date') ?></th><th></th><th><?= t('mailin.col_address') ?></th><th><?= t('mailin.col_subject') ?></th><th><?= t('mailin.col_result') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($log as $row): ?>
                        <tr>
                            <td class="nowrap"><?= e(fmt_date((string) $row['created_at'])) ?></td>
                            <td title="<?= t($row['direction'] === 'in' ? 'mailin.dir_in' : 'mailin.dir_out') ?>"><?= $row['direction'] === 'in' ? '↓' : '↑' ?></td>
                            <td><?= e((string) $row['address']) ?></td>
                            <td><?= e((string) $row['subject']) ?></td>
                            <td>
                                <span class="mail-status mail-<?= e($row['status']) ?>"><?= t('mailin.st_' . $row['status']) ?></span>
                                <?php if ($row['number']): ?> <a href="/op/ticket/<?= (int) $row['ticket_id'] ?>">#<?= e($row['number']) ?></a><?php endif; ?>
                                <?php if ($row['note']): ?><br><small class="muted"><?= e($row['note']) ?></small><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card narrow-left danger-zone">
            <h2><?= t('mi.delete') ?></h2>
            <form method="post" action="/op/config/email/<?= (int) $item['id'] ?>/delete" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn btn-danger" type="submit" data-confirm="<?= t('mi.confirm_delete', ['email' => $item['email']]) ?>"><?= t('mi.delete') ?></button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
