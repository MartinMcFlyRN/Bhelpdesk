<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Bhelpdesk\{AccessLinks, Attachments, Auth, Canned, CustomerAuth, Customers, Departments, IpGuard, Lang, Operators, Router, Session, Settings, Throttle, Tickets, View};

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
        'messages'     => $messages,
        'attachments'  => Attachments::forMessages(array_column($messages, 'id')),
        'participants' => Tickets::participants((int) $t['id']),
        'canned'       => array_map(
            static fn (array $c): array => $c + ['rendered' => Canned::render($c['body'], Canned::context($t, $op))],
            Canned::forTicket($t)
        ),
        'signature'    => Canned::signature($t, $op),
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

// ---------- Ticket customers: add / remove additional customers, change main customer ----------
$r->post('/ticket/{id}/customers', static function (array $p) use ($loadTicket): void {
    $op = Auth::require();
    $t = $loadTicket($p);
    $res = Customers::resolve((string) ($_POST['email'] ?? ''), (string) ($_POST['name'] ?? ''));
    if ($res['error']) {
        flash('error', 'tc.error', ['error' => $res['error']]);
    } elseif (Tickets::addParticipant($t, $op, (int) $res['id'])) {
        flash('success', 'tc.added');
    } else {
        flash('error', 'tc.already');
    }
    redirect('/op/ticket/' . $t['id']);
});

$r->post('/ticket/{id}/customers/{cid}/remove', static function (array $p) use ($loadTicket): void {
    $op = Auth::require();
    $t = $loadTicket($p);
    if (Tickets::removeParticipant($t, $op, (int) $p['cid'])) {
        flash('success', 'tc.removed');
    }
    redirect('/op/ticket/' . $t['id']);
});

// New direct link for a customer of the ticket (to send by hand until email notifications exist)
$r->post('/ticket/{id}/customers/{cid}/link', static function (array $p) use ($loadTicket): void {
    Auth::require();
    $t = $loadTicket($p);
    $cid = (int) $p['cid'];
    if (!Tickets::customerHasAccess($t, $cid)) {
        flash('error', 'error.not_found.message');
    } else {
        flash('link', 'tc.link_created', ['url' => AccessLinks::issue((int) $t['id'], $cid), 'days' => AccessLinks::days()]);
    }
    redirect('/op/ticket/' . $t['id']);
});

$r->post('/ticket/{id}/customer', static function (array $p) use ($loadTicket): void {
    $op = Auth::require();
    $t = $loadTicket($p);
    $res = Customers::resolve((string) ($_POST['email'] ?? ''), (string) ($_POST['name'] ?? ''));
    if ($res['error']) {
        flash('error', 'tc.error', ['error' => $res['error']]);
    } elseif (Tickets::changeCustomer($t, $op, (int) $res['id'], !empty($_POST['keep_previous']))) {
        flash('success', 'tc.changed');
    } else {
        flash('error', 'tc.same');
    }
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
//  Profile (every operator)
// =====================================================================
$profilePage = static function (array $form, array $errors): void {
    $op = Auth::user();
    View::render('op', 'profile', [
        'title'  => __('profile.title'),
        'op'     => $op,
        'me'     => Operators::find((int) $op['id']),
        'form'   => $form,
        'errors' => $errors,
    ]);
};

$r->get('/profile', static function () use ($profilePage): void {
    $op = Auth::require();
    $profilePage(['name' => $op['name'], 'email' => $op['email']], []);
});

$r->post('/profile', static function () use ($profilePage): void {
    $op = Auth::require();
    $res = Operators::validateProfile($_POST, $op);
    if ($res['errors']) {
        http_response_code(422);
        $profilePage($res['values'], $res['errors']);
        return;
    }
    Operators::updateProfile((int) $op['id'], $res['values']);
    if ($res['values']['new_password'] !== '') {
        Session::regenerate();
    }
    flash('success', 'profile.saved');
    redirect('/op/profile');
});

// =====================================================================
//  Customers (every operator)
// =====================================================================
$r->get('/customers', static function (): void {
    $op = Auth::require();
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $res = Customers::search($q, $page);
    View::render('op', 'customers/list', [
        'title' => __('cust.title'), 'op' => $op, 'q' => $q, 'page' => $page,
        'rows' => $res['rows'], 'total' => $res['total'], 'perPage' => Customers::PER_PAGE,
    ]);
});

$customerForm = static function (?array $customer, array $values, array $errors): void {
    View::render('op', 'customers/edit', [
        'title'     => $customer ? $customer['name'] : __('cust.new'),
        'op'        => Auth::user(),
        'customer'  => $customer,
        'values'    => $values,
        'errors'    => $errors,
        'tickets'   => $customer ? Customers::tickets((int) $customer['id']) : [],
        'languages' => Lang::available(true),
    ]);
};

// JSON for the customer autocomplete in the ticket page
$r->get('/customers/lookup', static function (): void {
    Auth::require();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(Customers::lookup(trim((string) ($_GET['q'] ?? ''))), JSON_UNESCAPED_UNICODE);
});

$r->get('/customers/new', static function () use ($customerForm): void {
    Auth::require();
    $customerForm(null, ['name' => '', 'company' => '', 'email' => '', 'phone' => '', 'language' => (string) config('default_language', 'en'), 'notes' => ''], []);
});

$r->post('/customers', static function () use ($customerForm): void {
    Auth::require();
    $res = Customers::validate($_POST);
    if ($res['errors']) {
        http_response_code(422);
        $customerForm(null, $res['values'], $res['errors']);
        return;
    }
    $id = Customers::create($res['values']);
    flash('success', 'cust.created');
    redirect('/op/customers/' . $id);
});

$loadCustomer = static function (array $p): array {
    $c = Customers::find((int) $p['id']);
    if (!$c) {
        http_response_code(404);
        View::render('op', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        exit;
    }
    return $c;
};

$r->get('/customers/{id}', static function (array $p) use ($customerForm, $loadCustomer): void {
    Auth::require();
    $c = $loadCustomer($p);
    $customerForm($c, $c, []);
});

// Email the customer a link to create (first access) or reset the password
$r->post('/customers/{id}/password-link', static function (array $p) use ($loadCustomer): void {
    Auth::require();
    $c = $loadCustomer($p);
    $ok = CustomerAuth::sendPasswordLink($c);
    flash($ok ? 'success' : 'error', $ok ? 'cust.pw_link_sent' : 'cust.pw_link_failed', ['email' => $c['email']]);
    redirect('/op/customers/' . $c['id']);
});

$r->post('/customers/{id}', static function (array $p) use ($customerForm, $loadCustomer): void {
    Auth::require();
    $c = $loadCustomer($p);
    $res = Customers::validate($_POST, (int) $c['id']);
    if ($res['errors']) {
        http_response_code(422);
        $customerForm($c, $res['values'], $res['errors']);
        return;
    }
    Customers::update((int) $c['id'], $res['values']);
    flash('success', 'cust.saved');
    redirect('/op/customers/' . $c['id']);
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

// ---------- Canned responses ----------
$cannedPage = static function (?array $item, array $values, array $errors) use ($cfgView): void {
    $cfgView($item === null && !isset($values['_edit']) ? 'canned' : 'canned_edit', __('cfg.menu.canned'), [
        'items'       => Canned::all(),
        'item'        => $item,
        'values'      => $values,
        'errors'      => $errors,
        'departments' => Departments::all(),
    ]);
};

$r->get('/config/canned', static function () use ($cannedPage): void {
    Auth::requireAdmin();
    $cannedPage(null, ['title' => '', 'body' => '', 'department_id' => null, 'active' => 1], []);
});

$r->post('/config/canned', static function () use ($cannedPage): void {
    $op = Auth::requireAdmin();
    $res = Canned::validate($_POST + ['active' => 1]);
    if ($res['errors']) {
        http_response_code(422);
        $cannedPage(null, $res['values'], $res['errors']);
        return;
    }
    Canned::create($res['values'], (int) $op['id']);
    flash('success', 'canned.created');
    redirect('/op/config/canned');
});

$loadCanned = static function (array $p): array {
    $c = Canned::find((int) $p['id']);
    if (!$c) {
        http_response_code(404);
        View::render('op', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        exit;
    }
    return $c;
};

$r->get('/config/canned/{id}', static function (array $p) use ($cannedPage, $loadCanned): void {
    Auth::requireAdmin();
    $c = $loadCanned($p);
    $cannedPage($c, $c, []);
});

$r->post('/config/canned/{id}', static function (array $p) use ($cannedPage, $loadCanned): void {
    $op = Auth::requireAdmin();
    $c = $loadCanned($p);
    $res = Canned::validate($_POST, (int) $c['id']);
    if ($res['errors']) {
        http_response_code(422);
        $cannedPage($c, $res['values'], $res['errors']);
        return;
    }
    Canned::update((int) $c['id'], $res['values'], (int) $op['id']);
    flash('success', 'canned.saved');
    redirect('/op/config/canned');
});

$r->post('/config/canned/{id}/delete', static function (array $p) use ($loadCanned): void {
    Auth::requireAdmin();
    $c = $loadCanned($p);
    Canned::delete((int) $c['id']);
    flash('success', 'canned.deleted');
    redirect('/op/config/canned');
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
