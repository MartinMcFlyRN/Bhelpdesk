<?php
use Bhelpdesk\{Lang, Settings};

$sections = [];
foreach (Settings::FIELDS as $key => $def) {
    $sections[$def['section']][$key] = $def;
}
$name = static fn (string $key): string => 's[' . $key . ']';
$fileVal = static function (array $file, string $key) {
    $v = $file;
    foreach (explode('.', $key) as $p) {
        if (!is_array($v) || !array_key_exists($p, $v)) {
            return null;
        }
        $v = $v[$p];
    }
    return $v;
};
?>
<div class="cfg-layout">
    <?php require BHD_ROOT . '/templates/op/config/_menu.php'; ?>
    <div class="cfg-content">
        <h1><?= t('cfg.menu.general') ?></h1>

        <?php if ($errors): ?>
            <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="/op/config/general" class="form">
            <input type="hidden" name="format" value="text">
            <?= \Bhelpdesk\Csrf::field() ?>

            <?php foreach ($sections as $section => $fields): ?>
            <fieldset class="card">
                <legend><?= t('set.section.' . $section) ?></legend>

                <?php foreach ($fields as $key => $def): $v = $values[$key] ?? null; $help = 'set.' . $key . '.help'; $hasHelp = __($help) !== $help; ?>
                    <?php switch ($def['type']):
                        case 'bool': ?>
                        <label class="check">
                            <input type="hidden" name="<?= e($name($key)) ?>" value="0">
                            <input type="checkbox" name="<?= e($name($key)) ?>" value="1" <?= $v ? 'checked' : '' ?>>
                            <?= t('set.' . $key) ?>
                        </label>
                        <?php break;

                        case 'timezone': ?>
                        <label><?= t('set.' . $key) ?>
                            <select name="<?= e($name($key)) ?>">
                                <?php foreach (\DateTimeZone::listIdentifiers() as $tz): ?>
                                    <option value="<?= e($tz) ?>" <?= $tz === $v ? 'selected' : '' ?>><?= e($tz) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php break;

                        case 'language': ?>
                        <label><?= t('set.' . $key) ?>
                            <select name="<?= e($name($key)) ?>">
                                <?php foreach ($languages as $code => $lname): ?>
                                    <option value="<?= e($code) ?>" <?= $code === $v ? 'selected' : '' ?>><?= e($lname) ?> (<?= e($code) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php break;

                        case 'languages': $sel = (array) $v; ?>
                        <div class="field">
                            <span class="label"><?= t('set.' . $key) ?></span>
                            <div class="checks">
                                <?php foreach ($languages as $code => $lname): ?>
                                    <label class="check">
                                        <input type="checkbox" name="<?= e($name($key)) ?>[]" value="<?= e($code) ?>"
                                            <?= $code === Lang::BASE ? 'checked disabled' : (in_array($code, $sel, true) || !$sel ? 'checked' : '') ?>>
                                        <?= e($lname) ?> (<?= e($code) ?>)
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php break;

                        case 'pair': [$max, $win] = array_pad((array) $v, 2, ''); ?>
                        <div class="field">
                            <span class="label"><?= t('set.' . $key) ?></span>
                            <div class="row-inline left">
                                <label class="inline-num"><?= t('set.throttle.max') ?>
                                    <input type="number" name="<?= e($name($key)) ?>[0]" value="<?= e($max) ?>" min="1" max="1000" required>
                                </label>
                                <label class="inline-num"><?= t('set.throttle.window') ?>
                                    <input type="number" name="<?= e($name($key)) ?>[1]" value="<?= e($win) ?>" min="60" max="86400" required>
                                </label>
                            </div>
                        </div>
                        <?php break;

                        case 'html': ?>
                        <label><?= t('set.' . $key) ?>
                            <textarea name="<?= e($name($key)) ?>" rows="5" data-editor="full"><?= e((string) $v) ?></textarea>
                        </label>
                        <?php break;

                        case 'choice': ?>
                        <label><?= t('set.' . $key) ?>
                            <select name="<?= e($name($key)) ?>">
                                <?php foreach ($def['options'] as $ok => $css): ?>
                                    <option value="<?= e($ok) ?>" <?= $ok === $v ? 'selected' : '' ?>><?= e(trim(explode(',', $css)[0], '"')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <?php break;

                        case 'int': ?>
                        <label class="inline-num"><?= t('set.' . $key) ?>
                            <input type="number" name="<?= e($name($key)) ?>" value="<?= e($v) ?>" min="<?= (int) $def['min'] ?>" max="<?= (int) $def['max'] ?>" required>
                        </label>
                        <?php break;

                        default: ?>
                        <label><?= t('set.' . $key) ?>
                            <input type="<?= in_array($def['type'], ['url', 'email'], true) ? $def['type'] : 'text' ?>" name="<?= e($name($key)) ?>" value="<?= e($v) ?>" required maxlength="<?= (int) ($def['max'] ?? 255) ?>">
                        </label>
                    <?php endswitch; ?>
                    <?php if ($hasHelp): ?><small class="muted hint field-help"><?= t($help) ?></small><?php endif; ?>
                <?php endforeach; ?>

                <?php if ($section === 'attachments'): ?>
                    <p class="muted hint"><?= t('set.php_limits', ['upload' => $php['upload_raw'], 'post' => $php['post_raw'], 'files' => $php['files']]) ?></p>
                    <?php if (Settings::attachmentLimitsExceedPhp()): ?>
                        <div class="alert alert-warning"><?= t('set.warn_php') ?></div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if ($section === 'security'): ?>
                    <p class="muted hint"><?= t('set.throttle.help') ?></p>
                <?php endif; ?>
            </fieldset>
            <?php endforeach; ?>

            <button class="btn btn-primary" type="submit"><?= t('cfg.save') ?></button>
        </form>

        <section class="card slack-cfg" id="slack">
            <h2><?= t('slack.title') ?></h2>
            <p class="muted hint"><?= t('slack.help') ?></p>
            <?php if ($slackErrors): ?>
                <div class="alert alert-error"><ul><?php foreach ($slackErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
            <?php endif; ?>
            <?php if (!$slack): ?>
                <p class="muted"><?= t('slack.no_departments') ?></p>
            <?php else: ?>
            <form method="post" action="/op/config/slack" id="slack-form">
                <?= \Bhelpdesk\Csrf::field() ?>
            </form>
            <table class="table slack-table">
                <thead><tr><th><?= t('slack.col_department') ?></th><th><?= t('slack.col_webhook') ?></th><th><?= t('slack.col_send') ?></th><th><?= t('slack.col_state') ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($slack as $id => $sd): $post = (array) ($slackPost[$id] ?? []); $f = 'slack[' . $id . ']'; ?>
                    <tr class="<?= $sd['active'] ? '' : 'disabled' ?>">
                        <td><strong><?= e($sd['name']) ?></strong></td>
                        <td>
                            <input form="slack-form" type="url" name="<?= $f ?>[webhook]" value="<?= e((string) ($post['webhook'] ?? '')) ?>" autocomplete="off"
                                   placeholder="<?= $sd['configured'] ? t('slack.ph_keep') : 'https://hooks.slack.com/services/…' ?>">
                            <?php if ($sd['configured']): ?>
                                <label class="check small"><input form="slack-form" type="checkbox" name="<?= $f ?>[remove]" value="1"> <?= t('slack.remove') ?></label>
                            <?php endif; ?>
                        </td>
                        <td class="nowrap">
                            <label class="check small"><input form="slack-form" type="checkbox" name="<?= $f ?>[new]" value="1" <?= ($post ? !empty($post['new']) : $sd['notify_new']) ? 'checked' : '' ?>> <?= t('slack.send_new') ?></label>
                            <label class="check small"><input form="slack-form" type="checkbox" name="<?= $f ?>[reply]" value="1" <?= ($post ? !empty($post['reply']) : $sd['notify_reply']) ? 'checked' : '' ?>> <?= t('slack.send_reply') ?></label>
                        </td>
                        <td class="small">
                            <?php if (!$sd['configured']): ?>
                                <span class="muted"><?= t('slack.state_off') ?></span>
                            <?php elseif ($sd['last_at'] === null): ?>
                                <span class="badge"><?= t('slack.state_configured') ?></span>
                            <?php elseif ($sd['last_error'] === null): ?>
                                <span class="badge badge-ok">✓ <?= e(fmt_date($sd['last_at'])) ?></span>
                            <?php else: ?>
                                <span class="badge badge-err" title="<?= e($sd['last_error']) ?>">✕ <?= e(fmt_date($sd['last_at'])) ?></span>
                                <div class="slack-error"><?= e($sd['last_error']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="right">
                            <?php if ($sd['configured']): ?>
                            <form method="post" action="/op/config/slack/<?= (int) $id ?>/test" class="inline">
                                <?= \Bhelpdesk\Csrf::field() ?>
                                <button class="btn btn-small" type="submit"><?= t('slack.test') ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <button form="slack-form" class="btn btn-primary" type="submit"><?= t('slack.save') ?></button>
            <?php endif; ?>
        </section>

        <section class="card readonly">
            <h2><?= t('set.readonly.title') ?></h2>
            <p class="muted hint"><?= t('set.readonly.help') ?></p>
            <dl class="meta">
                <dt><?= t('set.readonly.op_allowed_ips') ?></dt>
                <dd class="mono-list"><?= ($l = (array) $fileVal($file, 'op_allowed_ips')) ? e(implode(', ', $l)) : t('set.readonly.none') ?></dd>
                <dt><?= t('set.readonly.trusted_proxies') ?></dt>
                <dd class="mono-list"><?= ($l = (array) $fileVal($file, 'trusted_proxies')) ? e(implode(', ', $l)) : t('set.readonly.none') ?></dd>
                <dt><?= t('set.readonly.cookie_secure') ?></dt>
                <dd><?= $fileVal($file, 'cookie_secure') === false ? t('set.readonly.no') : t('set.readonly.yes') ?></dd>
                <dt><?= t('set.readonly.mail_driver') ?></dt>
                <dd class="mono-list"><?= e((string) config('mail.driver', 'log')) ?><?= config('mail.driver', 'log') === 'log' ? ' — ' . t('set.readonly.mail_log') : '' ?></dd>
                <dt><?= t('set.readonly.storage') ?></dt>
                <dd class="mono-list"><?= e(\Bhelpdesk\Attachments::storageDir()) ?></dd>
                <dt><?= t('set.readonly.db') ?></dt>
                <dd class="mono-list"><?= e(($fileVal($file, 'db.name') ?? '?') . ' @ ' . ($fileVal($file, 'db.socket') ?: ($fileVal($file, 'db.host') ?? '?'))) ?></dd>
                <dt>PHP</dt>
                <dd class="mono-list"><?= e(PHP_VERSION) ?> · GD <?= function_exists('gd_info') ? t('set.readonly.yes') : t('set.readonly.no') ?></dd>
            </dl>
        </section>
    </div>
</div>
