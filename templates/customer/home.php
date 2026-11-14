<section class="hero">
    <h1><?= t('home.heading') ?></h1>
    <p><?= t('home.intro') ?></p>
</section>
<div class="grid-2">
    <a class="card card-link" href="/customer/new">
        <h2><?= t('home.new.title') ?></h2>
        <p><?= t('home.new.text') ?></p>
    </a>
    <?php if (!empty($me)): ?>
    <a class="card card-link" href="/customer/tickets">
        <h2><?= t('home.mine.title') ?></h2>
        <p><?= t('home.mine.text_logged', ['name' => $me['name']]) ?></p>
    </a>
    <?php else: ?>
    <a class="card card-link" href="/customer/login">
        <h2><?= t('home.mine.title') ?></h2>
        <p><?= t('home.mine.text') ?></p>
    </a>
    <?php endif; ?>
</div>
