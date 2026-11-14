<?php $me = \Bhelpdesk\CustomerAuth::user(); ?>
<!doctype html>
<html lang="<?= e(\Bhelpdesk\Lang::current()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title><?= e($title ?? '') ?> · <?= e(config('app_name', 'Bhelpdesk')) ?></title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/app.js" defer></script>
</head>
<body class="area-customer">
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="/customer/"><?= e(config('app_name', 'Bhelpdesk')) ?></a>
        <nav>
            <a href="/customer/new"><?= t('nav.open_ticket') ?></a>
            <?php if ($me): ?>
                <a href="/customer/tickets"><?= t('nav.my_tickets_customer') ?></a>
            <?php endif; ?>
            <?php require BHD_ROOT . '/templates/lang_switch.php'; ?>
            <?php if ($me): ?>
            <details class="user-menu">
                <summary aria-label="<?= t('menu.account') ?>">
                    <span class="avatar avatar-customer" aria-hidden="true"><?= e(initials($me['name'])) ?></span>
                    <span class="user-name"><?= e($me['name']) ?></span>
                    <span class="caret" aria-hidden="true">▾</span>
                </summary>
                <div class="menu" role="menu">
                    <div class="menu-head">
                        <strong><?= e($me['name']) ?></strong>
                        <small><?= e($me['email']) ?></small>
                    </div>
                    <a role="menuitem" href="/customer/tickets"><?= t('nav.my_tickets_customer') ?></a>
                    <a role="menuitem" href="/customer/password"><?= t('pw.change_menu') ?></a>
                    <form method="post" action="/customer/logout">
                        <?= \Bhelpdesk\Csrf::field() ?>
                        <button type="submit" role="menuitem" class="menu-logout"><?= t('menu.logout') ?></button>
                    </form>
                </div>
            </details>
            <?php else: ?>
                <a class="btn btn-small btn-login" href="/customer/login"><?= t('nav.login') ?></a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="wrap">
    <?php require BHD_ROOT . '/templates/flashes.php'; ?>
    <?= $content ?>
</main>
<footer class="wrap footer"><?= e(config('app_name', 'Bhelpdesk')) ?></footer>
</body>
</html>
