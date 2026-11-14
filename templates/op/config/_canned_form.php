<form method="post" action="<?= e($formAction) ?>" class="form">
    <?= \Bhelpdesk\Csrf::field() ?>
    <div class="row-2">
        <label><?= t('canned.title_col') ?>
            <input type="text" name="title" required maxlength="150" value="<?= e($values['title'] ?? '') ?>" placeholder="<?= t('canned.title_placeholder') ?>">
        </label>
        <label><?= t('canned.department') ?>
            <select name="department_id">
                <option value="0"><?= t('canned.all_departments') ?></option>
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= (int) ($values['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <label><?= t('canned.body') ?>
        <textarea name="body" rows="8" required maxlength="20000"><?= e($values['body'] ?? '') ?></textarea>
    </label>
    <?php require BHD_ROOT . '/templates/op/config/_placeholders.php'; ?>
    <?php if (!empty($item)): ?>
        <label class="check"><input type="checkbox" name="active" value="1" <?= !empty($values['active']) ? 'checked' : '' ?>> <?= t('canned.active') ?></label>
    <?php endif; ?>
    <button class="btn btn-primary" type="submit"><?= empty($item) ? t('canned.create') : t('cfg.save_short') ?></button>
</form>
