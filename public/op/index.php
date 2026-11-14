<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Bhelpdesk\{AccessLinks, Alerts, Attachments, Auth, Canned, CustomerAuth, Customers, Db, Departments, EmailTemplates, Html, IpGuard, Lang, MailFetcher, MailIdentities, Mailer, Media, Notifier, Operators, Router, Session, Settings, Throttle, Tickets, View};

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
    $res = Tickets::search($filters, $op, $page);
    View::render('op', 'dashboard', [
        'title'       => __('dash.title'),
        'op'          => $op,
        'filters'     => $filters,
        'rows'        => $res['rows'],
        'total'       => $res['total'],
        'page'        => $page,
        'perPage'     => 25,
        'counts'      => Tickets::countsByStatus($op),
        'allDepartments' => Tickets::departments(false),
        // department filter: agents only see their own departments
        'departments' => $op['role'] === 'admin'
            ? Tickets::departments(false)
            : array_values(array_filter(Tickets::departments(false), static fn ($d) => in_array((int) $d['id'], Operators::departmentIds((int) $op['id']), true))),
    ]);
});

// =====================================================================
//  Bulk actions from the ticket list: move / merge / delete
//  Only tickets the operator can see are touched.
// =====================================================================
$r->post('/tickets/bulk', static function (): void {
    $op = Auth::require();
    $back = (string) ($_POST['back'] ?? '/op/');
    if (!preg_match('#^/op/(\?[^\r\n]*)?$#', $back)) {
        $back = '/op/';
    }
    $tickets = [];
    foreach (array_unique(array_map('intval', (array) ($_POST['ids'] ?? []))) as $id) {
        $t = Tickets::find($id);
        if ($t && $t['merged_into'] === null && Tickets::canSee($t, $op)) {
            $tickets[(int) $t['id']] = $t;
        }
    }
    if (!$tickets) {
        flash('error', 'bulk.none');
        redirect($back);
    }
    $ids = array_keys($tickets);

    switch ((string) ($_POST['action'] ?? '')) {
        case 'move':
            $dep = (int) ($_POST['department'] ?? 0);
            $name = (string) Db::value('SELECT name FROM departments WHERE id = ?', [$dep]);
            if ($name === '') {
                flash('error', 'bulk.choose_department');
                break;
            }
            $n = Tickets::moveMany($ids, $dep, $op);
            flash('success', 'bulk.moved', ['n' => $n, 'department' => $name]);
            break;

        case 'status':
            $status = (string) ($_POST['status'] ?? '');
            if (!in_array($status, Tickets::STATUSES, true)) {
                flash('error', 'bulk.choose_status');
                break;
            }
            $n = Tickets::statusMany($ids, $status, $op);
            flash('success', 'bulk.status_done', ['n' => $n, 'status' => status_label($status)]);
            break;

        case 'merge':
            $target = (int) ($_POST['target'] ?? 0);
            if (count($ids) < 2) {
                flash('error', 'bulk.merge_two');
                break;
            }
            if (!isset($tickets[$target])) {
                flash('error', 'bulk.merge_target');
                break;
            }
            $n = Tickets::merge($target, $ids, $op);
            flash('success', 'bulk.merged', ['n' => $n, 'number' => $tickets[$target]['number']]);
            redirect('/op/ticket/' . $target);

        case 'delete':
            if (($_POST['confirm'] ?? '') !== '1') {
                flash('error', 'bulk.confirm_needed');
                break;
            }
            $n = Tickets::deleteMany($ids);
            flash('success', 'bulk.deleted', ['n' => $n]);
            break;

        default:
            flash('error', 'bulk.none');
    }
    redirect($back);
});

// =====================================================================
//  New ticket opened by an operator (phone call, email, walk-in...)
// =====================================================================

/** Departments the operator may open tickets in: all active ones for admins, their own for agents */
$allowedDepartments = static function (array $op): array {
    $all = Tickets::departments(true);
    if ($op['role'] === 'admin') {
        return $all;
    }
    $mine = Operators::departmentIds((int) $op['id']);
    return array_values(array_filter($all, static fn ($d) => in_array((int) $d['id'], $mine, true)));
};

$newTicketPage = static function (array $op, array $values, array $errors) use ($allowedDepartments): void {
    View::render('op', 'ticket_new', [
        'title'       => __('nt.title'),
        'op'          => $op,
        'values'      => $values,
        'errors'      => $errors,
        'departments' => $allowedDepartments($op),
    ]);
};

$r->get('/tickets/new', static function () use ($newTicketPage, $allowedDepartments): void {
    $op = Auth::require();
    $deps = $allowedDepartments($op);
    // preselect a customer when coming from the customer page (?customer=ID)
    $c = isset($_GET['customer']) ? Customers::find((int) $_GET['customer']) : null;
    $newTicketPage($op, [
        'email' => $c['email'] ?? '', 'name' => $c['name'] ?? '', 'cc' => [],
        'department' => count($deps) === 1 ? (int) $deps[0]['id'] : 0,
        'subject' => '', 'priority' => 'normal', 'request' => '', 'reply' => '', 'status' => 'answered', 'assign_me' => 1,
    ], []);
});

$r->post('/tickets/new', static function () use ($newTicketPage, $allowedDepartments): void {
    $op = Auth::require();
    $ip = client_ip();

    // cc rows: cc_email[] / cc_name[]
    $cc = [];
    foreach ((array) ($_POST['cc_email'] ?? []) as $i => $em) {
        $em = mb_strtolower(trim((string) $em));
        if ($em !== '') {
            $cc[] = ['email' => $em, 'name' => trim((string) (((array) ($_POST['cc_name'] ?? []))[$i] ?? ''))];
        }
    }
    $replyFormat = ($_POST['format'] ?? '') === 'html' ? 'html' : 'text';
    $reply = $replyFormat === 'html' ? Html::sanitize((string) ($_POST['reply'] ?? '')) : trim((string) ($_POST['reply'] ?? ''));
    $v = [
        'email'      => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
        'name'       => trim((string) ($_POST['name'] ?? '')),
        'cc'         => $cc,
        'department' => (int) ($_POST['department'] ?? 0),
        'subject'    => trim((string) ($_POST['subject'] ?? '')),
        'priority'   => in_array($_POST['priority'] ?? '', Tickets::PRIORITIES, true) ? $_POST['priority'] : 'normal',
        'request'    => trim((string) ($_POST['request'] ?? '')),
        'reply'      => $replyFormat === 'html' ? $reply : Html::fromText($reply),
        'status'     => in_array($_POST['status'] ?? '', Tickets::STATUSES, true) ? $_POST['status'] : 'answered',
        'assign_me'  => !empty($_POST['assign_me']) ? 1 : 0,
    ];
    $hasReply = $replyFormat === 'html' ? !Html::isEmpty($reply) : $reply !== '';

    $errors = [];
    // main customer: existing one (by email) or a new one (email + name)
    $main = Customers::findByEmail($v['email']);
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = __('nt.err_customer_email');
    } elseif (!$main && ($v['name'] === '' || mb_strlen($v['name']) > 100)) {
        $errors[] = __('val.customer_new_needs_name');
    }
    foreach ($cc as $row) {
        if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('nt.err_cc_email', ['email' => $row['email']]);
        } elseif (!Customers::findByEmail($row['email']) && $row['name'] === '') {
            $errors[] = __('nt.err_cc_name', ['email' => $row['email']]);
        }
    }
    if (!in_array($v['department'], array_map('intval', array_column($allowedDepartments($op), 'id')), true)) {
        $errors[] = __('nt.err_department');
    }
    if ($v['subject'] === '' || mb_strlen($v['subject']) > 255) {
        $errors[] = __('val.subject_required');
    }
    if ($v['request'] === '') {
        $errors[] = __('nt.err_request');
    }
    $reqFiles = Attachments::fromUpload($_FILES['request_files'] ?? null, 'operator');
    $repFiles = Attachments::fromUpload($_FILES['attachments'] ?? null, 'operator');
    array_push($errors, ...$reqFiles['errors'], ...$repFiles['errors']);
    if ($errors) {
        http_response_code(422);
        $newTicketPage($op, $v, array_values(array_unique($errors)));
        return;
    }

    // customers: the main one is created by Tickets::create if new; cc customers here
    $mainName = $main ? (string) $main['name'] : $v['name'];
    $lang = (string) ($main['language'] ?? config('default_language', 'en'));
    $t = Tickets::create($mainName, $v['email'], $v['department'], $v['subject'], $v['request'], $ip, $lang, $reqFiles['files']);
    $ticket = Tickets::findByNumber($t['number']);
    $tid = (int) $ticket['id'];

    Db::exec('UPDATE tickets SET priority = ? WHERE id = ?', [$v['priority'], $tid]);
    Tickets::logOpened($tid, $op);
    Alerts::newTicket($tid, (int) $op['id'], (string) $op['name']);
    foreach ($cc as $row) {
        $res = Customers::resolve($row['email'], $row['name']);
        if ($res['id']) {
            Tickets::addParticipant($ticket, $op, (int) $res['id']);
        }
    }
    if ($v['assign_me']) {
        Tickets::update(Tickets::find($tid), $op, ['assigned_to' => (int) $op['id']]);
    }
    // email to the customers: the reply when there is one, otherwise the confirmation
    if ($hasReply || $repFiles['files']) {
        $mid = Tickets::operatorReply(Tickets::find($tid), $op, $hasReply ? $reply : '', false, $v['status'], $ip, $replyFormat, $repFiles['files']);
        $mailErrors = Notifier::operatorReply($tid, $mid);
    } else {
        $mailErrors = Notifier::ticketOpened($tid, array_merge([(int) $ticket['customer_id']], array_map('intval', array_column(Tickets::participants($tid), 'id'))));
    }
    foreach ($mailErrors as $err) {
        flash('error', 'mail.not_sent', ['error' => $err]);
    }
    // direct link for the main customer, to send by hand until email notifications exist
    flash('link', 'nt.created', ['number' => $t['number'], 'url' => $t['link'], 'days' => AccessLinks::days()]);
    redirect('/op/ticket/' . $tid);
});

// ---------- Ticket ----------
// Tickets outside the operator's departments answer "not found", as if they did not exist
$loadTicket = static function (array $p): array {
    $t = Tickets::find((int) $p['id']);
    // merged ticket: continue on the ticket it was merged into
    if ($t && $t['merged_into'] !== null) {
        flash('success', 'bulk.was_merged', ['number' => $t['number']]);
        redirect('/op/ticket/' . (int) $t['merged_into']);
    }
    if (!$t || !Tickets::canSee($t, Auth::require())) {
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
        // text kept after a failed submit (e.g. a rejected attachment)
        'drafts'       => (static function () use ($t): array {
            $d = ['reply' => $_SESSION['drafts'][$t['id'] . ':reply'] ?? '', 'note' => $_SESSION['drafts'][$t['id'] . ':note'] ?? ''];
            unset($_SESSION['drafts'][$t['id'] . ':reply'], $_SESSION['drafts'][$t['id'] . ':note']);
            return $d;
        })(),
        'departments' => Tickets::departments(false),
        'operators'   => Tickets::operators($t['department_id'] !== null ? (int) $t['department_id'] : null),
    ]);
});

$r->post('/ticket/{id}/reply', static function (array $p) use ($loadTicket): void {
    $op = Auth::require();
    $t = $loadTicket($p);
    $internal = !empty($_POST['internal']);
    $default = $internal ? $t['status'] : 'answered';
    // the editor posts HTML (format=html); without JavaScript the plain textarea posts text
    $format = ($_POST['format'] ?? '') === 'html' ? 'html' : 'text';
    $body = $format === 'html' ? Html::sanitize((string) ($_POST['message'] ?? '')) : trim((string) ($_POST['message'] ?? ''));
    $empty = $format === 'html' ? Html::isEmpty($body) : $body === '';
    $upload = Attachments::fromUpload($_FILES['attachments'] ?? null, 'operator');
    $draftKey = $t['id'] . ($internal ? ':note' : ':reply');

    if ($upload['errors'] || ($empty && !$upload['files'])) {
        foreach ($upload['errors'] ?: [__('ticket.empty_message')] as $err) {
            flash('error', 'tc.error', ['error' => $err]);
        }
        // keep what was written: the editor is refilled on the next page
        $_SESSION['drafts'][$draftKey] = $format === 'html' ? $body : Html::fromText($body);
        redirect('/op/ticket/' . $t['id'] . ($internal ? '#note' : '#reply'));
    }
    $mid = Tickets::operatorReply($t, $op, $empty ? '' : $body, $internal, (string) ($_POST['status'] ?? $default), client_ip(), $format, $upload['files']);
    if (!$internal) {
        foreach (Notifier::operatorReply((int) $t['id'], $mid) as $err) {
            flash('error', 'mail.not_sent', ['error' => $err]);
        }
    }
    if (!$internal && !$t['assigned_to']) {
        Tickets::update(Tickets::find((int) $t['id']), $op, ['assigned_to' => $op['id']]);
    }
    if ($internal) {
        flash('success', 'opticket.note_added');
        redirect('/op/ticket/' . $t['id']);
    }
    // after a reply to the customer, back to the ticket list
    flash('success', 'opticket.reply_sent_to', ['number' => $t['number']]);
    redirect('/op/');
});

// ---------- Images inserted with the editor (signatures, replies, canned responses) ----------
$r->post('/media', static function (): void {
    $op = Auth::require();
    $res = Media::upload($_FILES['image'] ?? null, (int) $op['id']);
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($res['error'] ? 422 : 200);
    echo json_encode($res, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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
    // moved to a department the operator does not handle: back to the list
    if (!Tickets::canSee(Tickets::find((int) $t['id']), $op)) {
        flash('success', 'opticket.transferred');
        redirect('/op/');
    }
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
    $op = Auth::require();
    $file = Attachments::find((int) $p['id']);
    $ticket = $file ? Tickets::find((int) $file['ticket_id']) : null;
    if (!$file || !$ticket || !Tickets::canSee($ticket, $op)) {
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
    $me = Operators::find((int) $op['id']);
    $profilePage([
        'name' => $me['name'], 'email' => $me['email'],
        'alert_new' => (bool) $me['alert_new'], 'alert_reply' => (bool) $me['alert_reply'], 'alert_assign' => (bool) $me['alert_assign'],
    ], []);
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
    $res = Customers::search($q, $page, $op);
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
        'tickets'   => $customer ? Customers::tickets((int) $customer['id'], Auth::user()) : [],
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
    $cfgView('department_edit', __('dep.edit'), ['dep' => $d, 'errors' => [], 'identities' => MailIdentities::options()]);
});

$r->post('/config/departments/{id}', static function (array $p) use ($cfgView, $loadDep): void {
    Auth::requireAdmin();
    $d = $loadDep($p);
    $res = Departments::validate($_POST, (int) $d['id']);
    if ($res['errors']) {
        http_response_code(422);
        $cfgView('department_edit', __('dep.edit'), ['dep' => array_merge($d, $res['values']), 'errors' => $res['errors'], 'identities' => MailIdentities::options()]);
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

// ---------- E-mail identities (SMTP + POP3) ----------
$identityPage = static function (?array $item, array $values, array $errors) use ($cfgView): void {
    $cfgView('email_edit', __('cfg.menu.email'), [
        'item'        => $item,
        'values'      => $values,
        'errors'      => $errors,
        'departments' => Departments::all(),
        'test'        => $item ? ($_SESSION['mail_test'][$item['id']] ?? null) : null,
        // last messages received and sent with this identity
        'log'         => $item ? Db::all(
            'SELECT m.*, t.number FROM mail_messages m LEFT JOIN tickets t ON t.id = m.ticket_id WHERE m.identity_id = ? ORDER BY m.id DESC LIMIT 30',
            [(int) $item['id']]
        ) : [],
    ]);
    if ($item) {
        unset($_SESSION['mail_test'][$item['id']]);
    }
};

$r->get('/config/email', static function () use ($cfgView): void {
    Auth::requireAdmin();
    $cfgView('email', __('cfg.menu.email'), [
        'items'   => MailIdentities::all(),
        'options' => MailIdentities::options(),
        'default' => (int) config('mail.default_identity', 0),
    ]);
});

$r->post('/config/email/default', static function (): void {
    Auth::requireAdmin();
    MailIdentities::setDefault((int) ($_POST['default_identity'] ?? 0) ?: null);
    flash('success', 'mi.default_saved');
    redirect('/op/config/email');
});

$r->get('/config/email/new', static function () use ($identityPage): void {
    Auth::requireAdmin();
    $identityPage(null, [
        'name' => (string) config('app_name', 'Bhelpdesk'), 'email' => '', 'department_id' => 0, 'active' => 1,
        'smtp_host' => '', 'smtp_port' => 587, 'smtp_security' => 'starttls', 'smtp_user' => '', 'smtp_verify' => 1,
        'pop_enabled' => 0, 'pop_host' => '', 'pop_port' => 995, 'pop_security' => 'ssl', 'pop_user' => '', 'pop_verify' => 1,
        'pop_keep' => 'days', 'pop_keep_days' => 30,
    ], []);
});

$r->post('/config/email', static function () use ($identityPage): void {
    Auth::requireAdmin();
    $res = MailIdentities::validate($_POST);
    if ($res['errors']) {
        http_response_code(422);
        $identityPage(null, $res['values'], $res['errors']);
        return;
    }
    $id = MailIdentities::save($res['values']);
    flash('success', 'mi.created');
    redirect('/op/config/email/' . $id);
});

$loadIdentity = static function (array $p): array {
    $i = MailIdentities::find((int) $p['id']);
    if (!$i) {
        http_response_code(404);
        View::render('op', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        exit;
    }
    return $i;
};

$r->get('/config/email/{id}', static function (array $p) use ($identityPage, $loadIdentity): void {
    Auth::requireAdmin();
    $i = $loadIdentity($p);
    $identityPage($i, $i, []);
});

$r->post('/config/email/{id}', static function (array $p) use ($identityPage, $loadIdentity): void {
    Auth::requireAdmin();
    $i = $loadIdentity($p);
    $res = MailIdentities::validate($_POST, $i);
    if ($res['errors']) {
        http_response_code(422);
        $identityPage($i, $res['values'] + ['smtp_pass' => $i['smtp_pass'], 'pop_pass' => $i['pop_pass']], $res['errors']);
        return;
    }
    MailIdentities::save($res['values'], (int) $i['id']);
    flash('success', 'mi.saved');
    redirect('/op/config/email/' . $i['id']);
});

// Download the mailbox now (same as the cron job)
$r->post('/config/email/{id}/fetch', static function (array $p) use ($loadIdentity): void {
    Auth::requireAdmin();
    $i = $loadIdentity($p);
    $lock = MailFetcher::lock();
    if (!$lock) {
        flash('error', 'mailin.busy');
    } else {
        @set_time_limit(300);
        $r = MailFetcher::run((int) $i['id'])[0] ?? null;
        if (!$r) {
            flash('error', 'mi.no_pop');
        } else {
            flash($r['ok'] ? 'success' : 'error', 'mailin.result', ['result' => $r['message']]);
        }
    }
    redirect('/op/config/email/' . $i['id'] . '#log');
});

$r->post('/config/email/{id}/delete', static function (array $p) use ($loadIdentity): void {
    Auth::requireAdmin();
    $i = $loadIdentity($p);
    MailIdentities::delete((int) $i['id']);
    flash('success', 'mi.deleted');
    redirect('/op/config/email');
});

// Connection tests: SMTP sends a test message, POP3 logs in and counts the messages
$r->post('/config/email/{id}/test', static function (array $p) use ($loadIdentity): void {
    $op = Auth::requireAdmin();
    $i = $loadIdentity($p);
    $what = ($_POST['what'] ?? '') === 'pop' ? 'pop' : 'smtp';
    $result = ['what' => $what, 'ok' => false, 'message' => '', 'log' => []];
    try {
        if ($what === 'smtp') {
            if ($i['smtp_host'] === '') {
                throw new \RuntimeException(__('mi.no_smtp'));
            }
            $to = filter_var($_POST['to'] ?? '', FILTER_VALIDATE_EMAIL) ?: (string) $op['email'];
            $smtp = MailIdentities::smtp($i);
            $raw = Mailer::raw(
                Mailer::headers($i['email'], $i['name'], $to, __('mi.test_subject', ['app' => (string) config('app_name', 'Bhelpdesk')])),
                Mailer::encodeBody(__('mi.test_body', ['identity' => $i['name'] . ' <' . $i['email'] . '>', 'who' => $op['name'], 'date' => fmt_date(date('Y-m-d H:i:s'))]))
            );
            try {
                $smtp->send($i['email'], [$to], $raw);
            } finally {
                $result['log'] = $smtp->log();
            }
            $result['ok'] = true;
            $result['message'] = __('mi.test_smtp_ok', ['to' => $to]);
        } else {
            if (!$i['pop_enabled'] || $i['pop_host'] === '') {
                throw new \RuntimeException(__('mi.no_pop'));
            }
            $pop = MailIdentities::pop3($i);
            try {
                $pop->connect();
                $st = $pop->stat();
            } finally {
                $pop->quit();
                $result['log'] = $pop->log();
            }
            $result['ok'] = true;
            $result['message'] = __('mi.test_pop_ok', ['n' => $st['count'], 'size' => \Bhelpdesk\Attachments::humanSize($st['size'])]);
            MailIdentities::markPop((int) $i['id'], true, $result['message']);
        }
    } catch (\Throwable $ex) {
        $result['message'] = $ex->getMessage();
        if ($what === 'pop') {
            MailIdentities::markPop((int) $i['id'], false, $ex->getMessage());
        }
    }
    $_SESSION['mail_test'][$i['id']] = $result;
    redirect('/op/config/email/' . $i['id'] . '#test');
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

// ---------- Templates of the automatic emails ----------
$loadTemplate = static function (array $p): string {
    $code = (string) $p['code'];
    if (!isset(EmailTemplates::CODES[$code])) {
        http_response_code(404);
        View::render('op', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        exit;
    }
    return $code;
};

$templatePage = static function (string $code, array $values, array $errors) use ($cfgView): void {
    $cfgView('template_edit', __('tpl.' . $code), [
        'code'     => $code,
        'tpl'      => EmailTemplates::get($code),
        'values'   => $values,
        'errors'   => $errors,
        'vars'     => EmailTemplates::CODES[$code][1],
        'defaults' => EmailTemplates::defaults($code),
    ]);
};

$r->get('/config/templates', static function () use ($cfgView): void {
    Auth::requireAdmin();
    $items = [];
    foreach (EmailTemplates::CODES as $code => [$group]) {
        $items[$group][$code] = EmailTemplates::get($code);
    }
    $cfgView('templates', __('cfg.menu.templates'), ['items' => $items]);
});

$r->get('/config/templates/{code}', static function (array $p) use ($templatePage, $loadTemplate): void {
    Auth::requireAdmin();
    $code = $loadTemplate($p);
    $t = EmailTemplates::get($code);
    $templatePage($code, ['subject' => $t['subject'], 'body' => $t['body']], []);
});

$r->post('/config/templates/{code}', static function (array $p) use ($templatePage, $loadTemplate): void {
    $op = Auth::requireAdmin();
    $code = $loadTemplate($p);
    $res = EmailTemplates::validate($_POST);
    if ($res['errors']) {
        http_response_code(422);
        $templatePage($code, $res['values'], $res['errors']);
        return;
    }
    EmailTemplates::save($code, $res['values'], (int) $op['id']);
    flash('success', 'tpl.saved');
    redirect('/op/config/templates/' . $code);
});

$r->post('/config/templates/{code}/reset', static function (array $p) use ($loadTemplate): void {
    Auth::requireAdmin();
    $code = $loadTemplate($p);
    EmailTemplates::reset($code);
    flash('success', 'tpl.reset_done');
    redirect('/op/config/templates/' . $code);
});

// test: the saved template with example data, sent to the administrator
$r->post('/config/templates/{code}/test', static function (array $p) use ($loadTemplate): void {
    $op = Auth::requireAdmin();
    $code = $loadTemplate($p);
    $mail = Notifier::defaultLanguage(static fn (): array => EmailTemplates::render($code, EmailTemplates::sample($code, $op)));
    $ok = Mailer::send((string) $op['email'], '[TEST] ' . $mail['subject'], $mail['text'], null, ['html' => $mail['html']]);
    if ($ok) {
        flash('success', 'tpl.test_sent', ['email' => $op['email']]);
    } else {
        flash('error', 'mail.not_sent', ['error' => $op['email'] . ': ' . Mailer::lastError()]);
    }
    redirect('/op/config/templates/' . $code);
});

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
    $cfgView('operators', __('cfg.menu.operators'), ['operators' => Operators::all(), 'errors' => [], 'departments' => Departments::all(), 'depNames' => Operators::departmentNames(), 'selDeps' => []]);
});

$r->post('/config/operators', static function () use ($cfgView): void {
    Auth::requireAdmin();
    $res = Operators::validate($_POST);
    if ($res['errors']) {
        http_response_code(422);
        $cfgView('operators', __('cfg.menu.operators'), ['operators' => Operators::all(), 'errors' => $res['errors'], 'departments' => Departments::all(), 'depNames' => Operators::departmentNames(), 'selDeps' => $res['values']['departments']]);
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
    $o = $loadOp($p);
    $cfgView('operator_edit', __('ops.edit'), ['target' => $o, 'errors' => [], 'departments' => Departments::all(), 'selDeps' => Operators::departmentIds((int) $o['id'])]);
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
        $cfgView('operator_edit', __('ops.edit'), ['target' => array_merge($o, $v), 'errors' => array_values(array_unique($errors)), 'departments' => Departments::all(), 'selDeps' => $v['departments']]);
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
