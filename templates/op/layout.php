<!doctype html>
<html lang="<?= e(\Bhelpdesk\Lang::current()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? '') ?> · <?= e(config('app_name', 'Bhelpdesk')) ?> <?= t('app.operators_area') ?></title>
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/app.js" defer></script>
</head>
<body class="area-op">
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="/op/"><?= e(config('app_name', 'Bhelpdesk')) ?> <small><?= e(mb_strtolower(__('app.operators_area'))) ?></small></a>
        <nav>
            <?php if (!empty($op)): ?>
            <a href="/op/"><?= t('nav.tickets') ?></a>
            <a href="/op/?assigned=me"><?= t('nav.my_tickets') ?></a>
            <?php if ($op['role'] === 'admin'): ?><a href="/op/config"<?= str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/op/config') ? ' class="active"' : '' ?>><?= t('nav.config') ?></a><?php endif; ?>
            <span class="muted"><?= e($op['name']) ?></span>
            <form method="post" action="/op/logout" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn btn-link" type="submit"><?= t('nav.logout') ?></button>
            </form>
            <?php endif; ?>
            <?php require BHD_ROOT . '/templates/lang_switch.php'; ?>
        </nav>
    </div>
</header>
<main class="wrap wide">
    <?php require BHD_ROOT . '/templates/flashes.php'; ?>
    <?= $content ?>
</main>
</body>
</html>
