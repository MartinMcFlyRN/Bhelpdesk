<div class="card narrow">
    <h1><?= t('lookup.title') ?></h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/customer/status" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <label><?= t('lookup.number') ?>
            <input type="text" name="number" required inputmode="numeric" value="<?= e(old('number')) ?>" placeholder="<?= t('lookup.placeholder') ?>">
        </label>
        <label><?= t('lookup.email') ?>
            <input type="email" name="email" required value="<?= e(old('email')) ?>">
        </label>
        <button class="btn btn-primary" type="submit"><?= t('lookup.submit') ?></button>
    </form>
</div>
