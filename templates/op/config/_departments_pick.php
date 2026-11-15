<?php
/**
 * Departments an operator works on (administrators and agents; not billing).
 * Expects: $departments (all), $selDeps (selected ids), $role (current role), $allDeps (bool)
 */
$allDeps = !empty($allDeps);
?>
<div class="field dep-pick<?= $allDeps ? ' is-all' : '' ?>" data-role-field>
    <span class="label"><?= t('ops.departments') ?></span>
    <label class="check dep-all"><input type="checkbox" name="all_departments" value="1" data-all-deps <?= $allDeps ? 'checked' : '' ?>> <strong><?= t('ops.all_departments_check') ?></strong></label>
    <div class="checks">
        <?php foreach ($departments as $d): ?>
            <label class="check">
                <input type="checkbox" name="departments[]" value="<?= (int) $d['id'] ?>" <?= in_array((int) $d['id'], array_map('intval', $selDeps), true) ? 'checked' : '' ?>>
                <?= e($d['name']) ?><?= $d['active'] ? '' : ' <span class="muted">(' . t('dep.inactive') . ')</span>' ?>
            </label>
        <?php endforeach; ?>
    </div>
    <small class="muted hint"><?= t('ops.departments_help') ?></small>
</div>
