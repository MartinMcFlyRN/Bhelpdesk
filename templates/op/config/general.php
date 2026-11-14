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

                        case 'int': ?>
                        <label class="inline-num"><?= t('set.' . $key) ?>
                            <input type="number" name="<?= e($name($key)) ?>" value="<?= e($v) ?>" min="<?= (int) $def['min'] ?>" max="<?= (int) $def['max'] ?>" required>
                        </label>
                        <?php break;

                        default: ?>
                        <label><?= t('set.' . $key) ?>
                            <input type="<?= $def['type'] === 'url' ? 'url' : 'text' ?>" name="<?= e($name($key)) ?>" value="<?= e($v) ?>" required maxlength="<?= (int) ($def['max'] ?? 255) ?>">
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
