<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? '') ?> · <?= e(config('app_name', 'Bhelpdesk')) ?> Operatori</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body class="area-op">
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="/op/"><?= e(config('app_name', 'Bhelpdesk')) ?> <small>operatori</small></a>
        <?php if (!empty($op)): ?>
        <nav>
            <a href="/op/">Ticket</a>
            <a href="/op/?assigned=me">I miei</a>
            <?php if ($op['role'] === 'admin'): ?><a href="/op/operators">Operatori</a><?php endif; ?>
            <span class="muted"><?= e($op['name']) ?></span>
            <form method="post" action="/op/logout" class="inline">
                <?= \Bhelpdesk\Csrf::field() ?>
                <button class="btn btn-link" type="submit">Esci</button>
            </form>
        </nav>
        <?php endif; ?>
    </div>
</header>
<main class="wrap wide">
    <?php require BHD_ROOT . '/templates/flashes.php'; ?>
    <?= $content ?>
</main>
</body>
</html>
