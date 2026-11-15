<?php
$uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$navActive = static fn (string $prefix): string => str_starts_with($uri, $prefix) ? ' class="active"' : '';
// tasks to do now for this operator: counter in the menu and box under the menu
$isBilling = ($op['role'] ?? '') === 'billing';   // billing operators: only the Billing section
$todoTasks = !empty($op) && !$isBilling ? \Bhelpdesk\Tasks::activeFor($op) : [];
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
            <a class="brand" href="<?= $isBilling ? '/op/billing/invoices' : '/op/' ?>"><?= e(config('app_name', 'Bhelpdesk')) ?> <small><?= e(mb_strtolower(__('app.operators_area'))) ?></small></a>
            <?php if (!empty($op)): ?>
            <nav class="main-nav">
                <?php if (!$isBilling): ?>
                <a href="/op/"<?= ($uri === '/op/' || str_starts_with($uri, '/op/ticket')) && ($_GET['assigned'] ?? '') !== 'me' ? ' class="active"' : '' ?>><?= t('nav.tickets') ?></a>
                <a href="/op/?assigned=me"<?= $uri === '/op/' && ($_GET['assigned'] ?? '') === 'me' ? ' class="active"' : '' ?>><?= t('nav.my_tickets') ?></a>
                <details class="nav-menu<?= str_starts_with($uri, '/op/tasks') ? ' active' : '' ?>">
                    <summary><?= t('nav.tasks') ?><?php if ($todoTasks): ?> <span class="nav-count"><?= count($todoTasks) ?></span><?php endif; ?> <span class="caret" aria-hidden="true">▾</span></summary>
                    <div class="menu" role="menu">
                        <a role="menuitem" href="/op/tasks/new"<?= $navActive('/op/tasks/new') ?>>+ <?= t('tasks.new') ?></a>
                        <a role="menuitem" href="/op/tasks"<?= $uri === '/op/tasks' ? ' class="active"' : '' ?>><?= t('tasks.all_title') ?></a>
                        <a role="menuitem" href="/op/tasks/mine"<?= $navActive('/op/tasks/mine') ?>><?= t('tasks.mine_title') ?></a>
                    </div>
                </details>
                <a href="/op/customers"<?= $navActive('/op/customers') ?>><?= t('nav.customers') ?></a>
                <?php endif; ?>
                <details class="nav-menu<?= str_starts_with($uri, '/op/billing') ? ' active' : '' ?>">
                    <summary><?= t('nav.billing') ?> <span class="caret" aria-hidden="true">▾</span></summary>
                    <div class="menu" role="menu">
                        <a role="menuitem" href="/op/billing/invoices"<?= $navActive('/op/billing/invoices') ?>><?= t('nav.invoices') ?></a>
                        <a role="menuitem" href="/op/billing/quotes"<?= $navActive('/op/billing/quotes') ?>><?= t('nav.quotes') ?></a>
                    </div>
                </details>
                <?php if (!$isBilling): ?>
                <a href="/op/oncall"<?= $navActive('/op/oncall') ?>><?= t('nav.oncall') ?></a>
                <?php endif; ?>
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
    <?php if ($todoTasks): ?>
    <section class="task-banner" aria-label="<?= t('tasks.banner_title') ?>">
        <div class="task-banner-head">
            <strong>⚠ <?= t('tasks.banner_title') ?> (<?= count($todoTasks) ?>)</strong>
            <a href="/op/tasks/mine?state=todo"><?= t('tasks.banner_all') ?></a>
        </div>
        <ul>
            <?php foreach (array_slice($todoTasks, 0, 6) as $k): $kState = \Bhelpdesk\Tasks::state($k); ?>
                <li class="task-<?= e($kState) ?>">
                    <a href="/op/tasks/<?= (int) $k['id'] ?>"><?= e($k['title']) ?></a>
                    <small><?= !empty($k['ticket_number']) ? '🎫 #' . e($k['ticket_number']) . ' · ' : '' ?><?= e(\Bhelpdesk\Tasks::assigneeLabel($k)) ?> · <?= e(\Bhelpdesk\Tasks::dueLabel($k)) ?><?= $kState === 'late' ? ' · <b>' . t('tasks.state_late') . '</b>' : '' ?></small>
                </li>
            <?php endforeach; ?>
            <?php if (count($todoTasks) > 6): ?><li class="more"><a href="/op/tasks/mine?state=todo"><?= t('tasks.banner_more', ['n' => count($todoTasks) - 6]) ?></a></li><?php endif; ?>
        </ul>
    </section>
    <?php endif; ?>
    <?php require BHD_ROOT . '/templates/flashes.php'; ?>
    <?= $content ?>
</main>
<?php $footer = site_footer('operators'); if ($footer !== ''): ?>
<footer class="wrap wide footer site-footer"><div class="rich"><?= $footer ?></div></footer>
<?php endif; ?>
</body>
</html>
