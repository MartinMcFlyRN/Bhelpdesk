<!doctype html>
<html lang="<?= e(\Bhelpdesk\Lang::current()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? '') ?> · <?= e(config('app_name', 'Bhelpdesk')) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/app.js" defer></script>
</head>
<body class="area-customer">
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="/customer/"><?= e(config('app_name', 'Bhelpdesk')) ?></a>
        <nav>
            <a href="/customer/new"><?= t('nav.open_ticket') ?></a>
            <a href="/customer/status"><?= t('nav.check_status') ?></a>
            <?php require BHD_ROOT . '/templates/lang_switch.php'; ?>
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
