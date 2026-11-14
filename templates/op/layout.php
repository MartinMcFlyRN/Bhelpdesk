<?php
$uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$navActive = static fn (string $prefix): string => str_starts_with($uri, $prefix) ? ' class="active"' : '';
?>
<!doctype html>
<html lang="<?= e(\Bhelpdesk\Lang::current()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? '') ?> · <?= e(config('app_name', 'Bhelpdesk')) ?> <?= t('app.operators_area') ?></title>
    <link rel="stylesheet" href="/assets/vendor/quill-2.0.3/quill.snow.css">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/vendor/quill-2.0.3/quill.js" defer></script>
    <script src="/assets/editor.js" defer></script>
    <script src="/assets/app.js" defer></script>
</head>
<body class="area-op">
<header class="topbar">
    <div class="wrap wide">
        <div class="topbar-left">
            <a class="brand" href="/op/"><?= e(config('app_name', 'Bhelpdesk')) ?> <small><?= e(mb_strtolower(__('app.operators_area'))) ?></small></a>
            <?php if (!empty($op)): ?>
            <nav class="main-nav">
                <a href="/op/"<?= ($uri === '/op/' || str_starts_with($uri, '/op/ticket')) && ($_GET['assigned'] ?? '') !== 'me' ? ' class="active"' : '' ?>><?= t('nav.tickets') ?></a>
                <a href="/op/?assigned=me"<?= $uri === '/op/' && ($_GET['assigned'] ?? '') === 'me' ? ' class="active"' : '' ?>><?= t('nav.my_tickets') ?></a>
                <a href="/op/customers"<?= $navActive('/op/customers') ?>><?= t('nav.customers') ?></a>
            </nav>
            <?php endif; ?>
        </div>
        <div class="topbar-right">
            <?php require BHD_ROOT . '/templates/lang_switch.php'; ?>
            <?php if (!empty($op)): ?>
            <details class="user-menu">
                <summary aria-label="<?= t('menu.account') ?>">
                    <span class="avatar" aria-hidden="true"><?= e(initials($op['name'])) ?></span>
                    <span class="user-name"><?= e($op['name']) ?></span>
                    <span class="caret" aria-hidden="true">▾</span>
                </summary>
                <div class="menu" role="menu">
                    <div class="menu-head">
                        <strong><?= e($op['name']) ?></strong>
                        <small><?= e($op['username']) ?> · <?= t('role.' . $op['role']) ?></small>
                    </div>
                    <?php if ($op['role'] === 'admin'): ?>
                        <a role="menuitem" href="/op/config"<?= $navActive('/op/config') ?>><?= t('menu.config') ?></a>
                    <?php endif; ?>
                    <a role="menuitem" href="/op/profile"<?= $navActive('/op/profile') ?>><?= t('menu.profile') ?></a>
                    <form method="post" action="/op/logout">
                        <?= \Bhelpdesk\Csrf::field() ?>
                        <button type="submit" role="menuitem" class="menu-logout"><?= t('menu.logout') ?></button>
                    </form>
                </div>
            </details>
            <?php endif; ?>
        </div>
    </div>
</header>
<main class="wrap wide">
    <?php require BHD_ROOT . '/templates/flashes.php'; ?>
    <?= $content ?>
</main>
</body>
</html>
