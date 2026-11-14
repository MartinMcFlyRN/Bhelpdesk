<?php
/**
 * Department checkboxes for an operator.
 * Expects: $departments (all), $selDeps (selected ids), $role (current role)
 */
?>
<div class="field dep-pick" data-role-field>
    <span class="label"><?= t('ops.departments') ?></span>
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
