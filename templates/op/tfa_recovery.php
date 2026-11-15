<div class="card narrow tfa-card">
    <h1><?= t('tfa.recovery_title') ?></h1>
    <p><?= t('tfa.recovery_intro') ?></p>
    <ul class="tfa-codes">
        <?php foreach ($codes as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?>
    </ul>
    <p class="alert alert-error"><?= t('tfa.recovery_warning') ?></p>
    <p><button type="button" class="btn" data-print><?= t('tfa.print') ?></button>
       <a class="btn btn-primary" href="/op/"><?= t('tfa.continue') ?></a></p>
</div>
