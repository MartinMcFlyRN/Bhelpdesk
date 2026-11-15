<?php
use Bhelpdesk\Csrf;
$opSelect = static function (?int $selected, string $emptyLabel) use ($operators): string {
    $h = '<option value="0">' . e($emptyLabel) . '</option>';
    foreach ($operators as $o) {
        $h .= '<option value="' . (int) $o['id'] . '"' . ((int) $o['id'] === $selected ? ' selected' : '') . '>' . e($o['name']) . '</option>';
    }
    return $h;
};
?>
<div class="page-head">
    <h1><?= t('oncall.title') ?></h1>
</div>

<div class="oncall-nav">
    <a class="btn" href="/op/oncall?month=<?= e($prev) ?>">‹ <?= t('oncall.month_' . (int) substr($prev, 5, 2)) ?></a>
    <h2><?= t('oncall.month_' . $monthNum) ?> <?= (int) $year ?></h2>
    <a class="btn" href="/op/oncall?month=<?= e($next) ?>"><?= t('oncall.month_' . (int) substr($next, 5, 2)) ?> ›</a>
    <?php if ($month !== date('Y-m')): ?><a class="btn btn-small" href="/op/oncall"><?= t('oncall.today') ?></a><?php endif; ?>
</div>

<p class="muted oncall-help"><?= t($canEdit ? 'oncall.help_edit' : 'oncall.help_view') ?></p>

<table class="oncall-grid">
    <thead>
    <tr>
        <th class="oncall-week-col"><?= t('oncall.week') ?></th>
        <?php for ($i = 1; $i <= 7; $i++): ?><th><?= t('oncall.dow_' . $i) ?></th><?php endfor; ?>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($grid as $week): ?>
    <tr>
        <td class="oncall-week-col">
            <span class="oncall-week-num"><?= t('oncall.week_short') ?> <?= (int) $week['number'] ?></span>
            <?php if ($canEdit): ?>
            <form method="post" action="/op/oncall/week">
                <?= Csrf::field() ?>
                <input type="hidden" name="month" value="<?= e($month) ?>">
                <input type="hidden" name="date" value="<?= e($week['monday']) ?>">
                <select name="operator_id" data-autosubmit aria-label="<?= t('oncall.week_oncall') ?>"><?= $opSelect($week['oncall']['id'] ?? null, __('oncall.nobody')) ?></select>
                <noscript><button class="btn btn-small" type="submit"><?= t('cfg.save_short') ?></button></noscript>
            </form>
            <?php else: ?>
            <strong><?= $week['oncall'] ? e($week['oncall']['name']) : '<span class="muted">' . t('oncall.nobody') . '</span>' ?></strong>
            <?php endif; ?>
        </td>
        <?php foreach ($week['days'] as $d): $oc = $d['oncall']; ?>
        <td class="oncall-day<?= $d['in_month'] ? '' : ' is-out' ?><?= $d['today'] ? ' is-today' : '' ?><?= $oc && $oc['override'] ? ' is-override' : '' ?>">
            <span class="oncall-num"><?= (int) $d['day'] ?></span>
            <?php if ($d['in_month']): ?>
                <?php if ($oc): ?>
                    <span class="oncall-name"<?= $oc['override'] ? ' title="' . t('oncall.override_title') . '"' : '' ?>><?= e($oc['name']) ?></span>
                <?php endif; ?>
                <?php if ($canEdit): ?>
                <details class="oncall-edit">
                    <summary title="<?= t('oncall.day_change') ?>">✎</summary>
                    <form method="post" action="/op/oncall/day">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="month" value="<?= e($month) ?>">
                        <input type="hidden" name="date" value="<?= e($d['date']) ?>">
                        <select name="operator_id" data-autosubmit aria-label="<?= t('oncall.day_change') ?>"><?= $opSelect($oc && $oc['override'] ? $oc['id'] : null, __('oncall.as_week')) ?></select>
                        <noscript><button class="btn btn-small" type="submit"><?= t('cfg.save_short') ?></button></noscript>
                    </form>
                </details>
                <?php endif; ?>
            <?php endif; ?>
        </td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="oncall-legend muted"><span class="oncall-swatch"></span> <?= t('oncall.legend_override') ?></p>
