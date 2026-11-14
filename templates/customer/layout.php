<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? '') ?> · <?= e(config('app_name', 'Bhelpdesk')) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="area-customer">
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="/customer/"><?= e(config('app_name', 'Bhelpdesk')) ?></a>
        <nav>
            <a href="/customer/new">Apri ticket</a>
            <a href="/customer/status">Verifica stato</a>
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
