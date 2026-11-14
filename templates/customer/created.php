<div class="card narrow">
    <h1><?= t('created.title') ?></h1>
    <p><?= t('created.intro') ?></p>
    <p class="ticket-number">#<?= e($number) ?></p>
    <p><?= t('created.keep') ?></p>
    <p><input type="text" readonly class="mono" value="<?= e($link) ?>"></p>
    <p><a class="btn btn-primary" href="/customer/ticket/<?= e($number) ?>"><?= t('created.go') ?></a></p>
</div>
