<div class="card narrow">
    <h1><?= t('new.title') ?></h1>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/customer/new" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <div class="hp" aria-hidden="true"><label><?= t('new.honeypot') ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="row-2">
            <label><?= t('new.name') ?>
                <input type="text" name="name" required maxlength="100" value="<?= e(old('name')) ?>">
            </label>
            <label><?= t('new.email') ?>
                <input type="email" name="email" required maxlength="190" value="<?= e(old('email')) ?>">
            </label>
        </div>
        <?php if ($departments): ?>
        <label><?= t('new.department') ?>
            <select name="department">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= old('department') == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label><?= t('new.subject') ?>
            <input type="text" name="subject" required maxlength="255" value="<?= e(old('subject')) ?>">
        </label>
        <label><?= t('new.message') ?>
            <textarea name="message" rows="8" required minlength="10"><?= e(old('message')) ?></textarea>
        </label>
        <button class="btn btn-primary" type="submit"><?= t('new.submit') ?></button>
    </form>
</div>
