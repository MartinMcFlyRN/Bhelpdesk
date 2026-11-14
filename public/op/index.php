<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Bhelpdesk\{Attachments, Auth, Departments, IpGuard, Lang, Operators, Router, Session, Settings, Throttle, Tickets, View};

// ---------- Second-level IP filter (the first one is in nginx/Apache) ----------
$allowed = (array) config('op_allowed_ips', []);
if ($allowed && !IpGuard::matchesAny(client_ip(), $allowed)) {
    http_response_code(403);
    exit(__('error.ip_denied'));
}

Session::start('BHD_OP', '/op/');
header('Cache-Control: no-store');

$r = new Router('/op', 'op');

// ---------- Login / logout ----------
$r->get('/login', static function (): void {
    if (Auth::user()) {
        redirect('/op/');
    }
    View::render('op', 'login', ['title' => __('login.title'), 'error' => null]);
});

$r->post('/login', static function (): void {
    $ip = client_ip();
    if (Throttle::tooMany('op_login_fail', $ip)) {
        http_response_code(429);
        View::render('op', 'login', ['title' => __('login.title'), 'error' => __('login.too_many')]);
        return;
    }
    $ok = Auth::attempt(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''), $ip);
    if (!$ok) {
        http_response_code(401);
        View::render('op', 'login', ['title' => __('login.title'), 'error' => __('login.invalid')]);
        return;
    }
    redirect('/op/');
});

$r->post('/logout', static function (): void {
    Auth::logout();
    redirect('/op/login');
});

// ---------- Dashboard ----------
$r->get('/', static function (): void {
    $op = Auth::require();
    $filters = [
        'status'     => (string) ($_GET['status'] ?? ''),
        'assigned'   => (string) ($_GET['assigned'] ?? ''),
        'department' => (int) ($_GET['department'] ?? 0),
        'q'          => trim((string) ($_GET['q'] ?? '')),
    ];
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $res = Tickets::search($filters, (int) $op['id'], $page);
    View::render('op', 'dashboard', [
        'title'       => __('dash.title'),
        'op'          => $op,
        'filters'     => $filters,
        'rows'        => $res['rows'],
        'total'       => $res['total'],
        'page'        => $page,
        'perPage'     => 25,
        'counts'      => Tickets::countsByStatus(),
        'departments' => Tickets::departments(false),
    ]);
});

// ---------- Ticket ----------
$loadTicket = static function (array $p): array {
    $t = Tickets::find((int) $p['id']);
    if (!$t) {
        http_response_code(404);
        View::render('op', 'error', ['title' => __('error.ticket_not_found.title'), 'message' => __('error.ticket_not_found.message')]);
        exit;
    }
    return $t;
};

$r->get('/ticket/{id}', static function (array $p) use ($loadTicket): void {
    $op = Auth::require();
    $t = $loadTicket($p);
    $messages = Tickets::messages((int) $t['id'], true);
    View::render('op', 'ticket', [
        'title'       => '#' . $t['number'] . ' ' . $t['subject'],
        'op'          => $op,
        'ticket'      => $t,
        'messages'    => $messages,
        'attachments' => Attachments::forMessages(array_column($messages, 'id')),
        'departments' => Tickets::departments(false),
        'operators'   => Tickets::operators(),
    ]);
});

$r->post('/ticket/{id}/reply', static function (array $p) use ($loadTicket): void {
    $op = Auth::require();
    $t = $loadTicket($p);
    $body = trim((string) ($_POST['message'] ?? ''));
    $internal = !empty($_POST['internal']);
    $default = $internal ? $t['status'] : 'answered';
    if ($body === '') {
        flash('error', 'ticket.empty_message');
    } else {
        Tickets::operatorReply($t, $op, $body, $internal, (string) ($_POST['status'] ?? $default), client_ip());
        if (!$internal && !$t['assigned_to']) {
            Tickets::update(Tickets::find((int) $t['id']), $op, ['assigned_to' => $op['id']]);
        }
        flash('success', $internal ? 'opticket.note_added' : 'opticket.reply_sent');
    }
    redirect('/op/ticket/' . $t['id']);
});

$r->post('/ticket/{id}/update', static function (array $p) use ($loadTicket): void {
    $op = Auth::require();
    $t = $loadTicket($p);
    Tickets::update($t, $op, [
        'status'        => (string) ($_POST['status'] ?? $t['status']),
        'priority'      => (string) ($_POST['priority'] ?? $t['priority']),
        'department_id' => (int) ($_POST['department_id'] ?? 0),
        'assigned_to'   => (int) ($_POST['assigned_to'] ?? 0),
    ]);
    flash('success', 'opticket.saved');
    redirect('/op/ticket/' . $t['id']);
});

// ---------- Attachment download ----------
$r->get('/file/{id}', static function (array $p): void {
    Auth::require();
    $file = Attachments::find((int) $p['id']);
    if (!$file) {
        http_response_code(404);
        View::render('op', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        return;
    }
    Attachments::send($file, isset($_GET['download']));
});

// =====================================================================
//  Configuration (/op/config) - administrators only
//  Inherits the /op IP filter of the web server and of the application.
// =====================================================================

$cfgView = static function (string $page, string $title, array $vars = []): void {
    View::render('op', 'config/' . $page, $vars + [
        'title'   => $title . ' · ' . __('cfg.title'),
        'op'      => Auth::user(),
        'cfgPage' => $page,
    ]);
};

$r->get('/config', static function (): void {
    Auth::requireAdmin();
    redirect('/op/config/general');
});

// ---------- General ----------
$generalPage = static function (array $values, array $errors) use ($cfgView): void {
    $cfgView('general', __('cfg.menu.general'), [
        'values'    => $values,
        'errors'    => $errors,
        'languages' => Lang::available(true),
        'php'       => Settings::phpLimits(),
        'file'      => $GLOBALS['bhd_config_file'] ?? [],
    ]);
};

$r->get('/config/general', static function () use ($generalPage): void {
    Auth::requireAdmin();
    $values = [];
    foreach (array_keys(Settings::FIELDS) as $k) {
        $values[$k] = Settings::current($k);
    }
    $generalPage($values, []);
});

$r->post('/config/general', static function () use ($generalPage): void {
    $op = Auth::requireAdmin();
    $res = Settings::validate((array) ($_POST['s'] ?? []));
    if ($res['errors']) {
        http_response_code(422);
        $generalPage($res['values'], $res['errors']);
        return;
    }
    Settings::save($res['values'], (int) $op['id']);
    flash('success', 'cfg.saved');
    redirect('/op/config/general');
});

// ---------- Departments ----------
$r->get('/config/departments', static function () use ($cfgView): void {
    Auth::requireAdmin();
    $cfgView('departments', __('cfg.menu.departments'), ['departments' => Departments::all(), 'errors' => []]);
});

$r->post('/config/departments', static function () use ($cfgView): void {
    Auth::requireAdmin();
    $res = Departments::validate($_POST + ['active' => 1]);
    if ($res['errors']) {
        http_response_code(422);
        $cfgView('departments', __('cfg.menu.departments'), ['departments' => Departments::all(), 'errors' => $res['errors']]);
        return;
    }
    Departments::create($res['values']);
    flash('success', 'dep.created');
    redirect('/op/config/departments');
});

$loadDep = static function (array $p): array {
    $d = Departments::find((int) $p['id']);
    if (!$d) {
        http_response_code(404);
        View::render('op', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        exit;
    }
    return $d;
};

$r->get('/config/departments/{id}', static function (array $p) use ($cfgView, $loadDep): void {
    Auth::requireAdmin();
    $d = $loadDep($p);
    $cfgView('department_edit', __('dep.edit'), ['dep' => $d, 'errors' => []]);
});

$r->post('/config/departments/{id}', static function (array $p) use ($cfgView, $loadDep): void {
    Auth::requireAdmin();
    $d = $loadDep($p);
    $res = Departments::validate($_POST, (int) $d['id']);
    if ($res['errors']) {
        http_response_code(422);
        $cfgView('department_edit', __('dep.edit'), ['dep' => array_merge($d, $res['values']), 'errors' => $res['errors']]);
        return;
    }
    Departments::update((int) $d['id'], $res['values']);
    flash('success', 'dep.saved');
    redirect('/op/config/departments');
});

$r->post('/config/departments/{id}/delete', static function (array $p) use ($loadDep): void {
    Auth::requireAdmin();
    $d = $loadDep($p);
    if (Departments::delete((int) $d['id'])) {
        flash('success', 'dep.deleted');
        redirect('/op/config/departments');
    }
    flash('error', 'dep.in_use');
    redirect('/op/config/departments/' . $d['id']);
});

// ---------- Operators ----------
$r->get('/config/operators', static function () use ($cfgView): void {
    Auth::requireAdmin();
    $cfgView('operators', __('cfg.menu.operators'), ['operators' => Operators::all(), 'errors' => []]);
});

$r->post('/config/operators', static function () use ($cfgView): void {
    Auth::requireAdmin();
    $res = Operators::validate($_POST);
    if ($res['errors']) {
        http_response_code(422);
        $cfgView('operators', __('cfg.menu.operators'), ['operators' => Operators::all(), 'errors' => $res['errors']]);
        return;
    }
    Operators::create($res['values']);
    flash('success', 'ops.created');
    redirect('/op/config/operators');
});

$loadOp = static function (array $p): array {
    $o = Operators::find((int) $p['id']);
    if (!$o) {
        http_response_code(404);
        View::render('op', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        exit;
    }
    return $o;
};

$r->get('/config/operators/{id}', static function (array $p) use ($cfgView, $loadOp): void {
    Auth::requireAdmin();
    $cfgView('operator_edit', __('ops.edit'), ['target' => $loadOp($p), 'errors' => []]);
});

$r->post('/config/operators/{id}', static function (array $p) use ($cfgView, $loadOp): void {
    $me = Auth::requireAdmin();
    $o = $loadOp($p);
    $res = Operators::validate($_POST, $o);
    $v = $res['values'];
    $errors = $res['errors'];

    $isSelf = (int) $o['id'] === (int) $me['id'];
    if ($isSelf && $v['role'] !== 'admin') {
        $errors[] = __('ops.self_role');
    }
    if ($isSelf && !$v['active']) {
        $errors[] = __('ops.self_disable');
    }
    $losesAdmin = $o['role'] === 'admin' && $o['active'] && ($v['role'] !== 'admin' || !$v['active']);
    if ($losesAdmin && Operators::otherActiveAdmins((int) $o['id']) === 0) {
        $errors[] = __('ops.last_admin');
    }

    if ($errors) {
        http_response_code(422);
        $cfgView('operator_edit', __('ops.edit'), ['target' => array_merge($o, $v), 'errors' => array_values(array_unique($errors))]);
        return;
    }
    Operators::update((int) $o['id'], $v);
    flash('success', 'ops.saved');
    redirect('/op/config/operators');
});

$r->post('/config/operators/{id}/toggle', static function (array $p) use ($loadOp): void {
    $me = Auth::requireAdmin();
    $o = $loadOp($p);
    if ((int) $o['id'] === (int) $me['id']) {
        flash('error', 'ops.self_disable');
    } elseif ($o['active'] && $o['role'] === 'admin' && Operators::otherActiveAdmins((int) $o['id']) === 0) {
        flash('error', 'ops.last_admin');
    } else {
        Operators::setActive((int) $o['id'], !$o['active']);
        flash('success', 'ops.state_updated');
    }
    redirect('/op/config/operators');
});

// old URL of the operators page
$r->get('/operators', static function (): void {
    header('Location: /op/config/operators', true, 301);
});

$r->dispatch();
