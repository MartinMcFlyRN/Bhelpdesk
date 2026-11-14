<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Bhelpdesk\{AccessLinks, Attachments, Captcha, CustomerAuth, Customers, Lang, Router, Session, Throttle, Tickets, View};

Session::start('BHD_CUST', '/customer/');

$r = new Router('/customer', 'customer');

/**
 * Ticket visible to the current visitor, or null. Two ways in:
 *  - signed-in customer who is main or additional customer of the ticket;
 *  - a direct link opened in this session ($_SESSION['links'][number]),
 *    re-validated on every request (expiry, customer still on the ticket).
 * The returned row has viewer_id (customer) set.
 */
$canSee = static function (string $number): ?array {
    $ticket = Tickets::findByNumber($number);
    if (!$ticket) {
        return null;
    }
    $me = CustomerAuth::user();
    if ($me && Tickets::customerHasAccess($ticket, (int) $me['id'])) {
        return $ticket + ['viewer_id' => (int) $me['id']];
    }
    $link = $_SESSION['links'][$number] ?? null;
    if (is_array($link) && AccessLinks::stillValid((string) $link['h'])) {
        return $ticket + ['viewer_id' => (int) $link['c']];
    }
    return null;
};

/** Where to go when a ticket cannot be shown: expired-link page or sign-in */
$denied = static function (string $number): never {
    if (isset($_SESSION['links'][$number])) {
        // still in the table = expired by time; gone = revoked (customer removed from the ticket)
        $revoked = !\Bhelpdesk\Db::value('SELECT 1 FROM ticket_links WHERE token_hash = ?', [(string) $_SESSION['links'][$number]['h']]);
        unset($_SESSION['links'][$number]);
        http_response_code($revoked ? 404 : 410);
        View::render('customer', 'link_expired', ['title' => __($revoked ? 'link.invalid_title' : 'link.expired_title'), 'invalid' => $revoked]);
        exit;
    }
    // signed in but not a customer of this ticket: do not reveal that it exists
    if (CustomerAuth::user()) {
        http_response_code(404);
        View::render('customer', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        exit;
    }
    flash('error', 'login.needed');
    redirect('/customer/login?next=' . rawurlencode('/customer/ticket/' . $number));
};

/** Safe redirect target after login (only pages of this area) */
$nextUrl = static function (?string $next): string {
    $next = (string) $next;
    return preg_match('#^/customer/[A-Za-z0-9/_-]*$#', $next) ? $next : '/customer/tickets';
};

$r->get('/', static function (): void {
    View::render('customer', 'home', ['title' => __('home.title'), 'me' => CustomerAuth::user()]);
});

// ---------- Images inserted by operators with the editor (public, unguessable names) ----------
$r->get('/media/{file}', static function (array $p): void {
    \Bhelpdesk\Media::send((string) $p['file']);
});

// ---------- Captcha image ----------
$r->get('/captcha', static function (): void {
    Captcha::output();
});

// =====================================================================
//  Sign in, first access and password reset
// =====================================================================
$r->get('/login', static function () use ($nextUrl): void {
    if (CustomerAuth::user()) {
        redirect($nextUrl($_GET['next'] ?? null));
    }
    View::render('customer', 'login', ['title' => __('login.customer_title'), 'error' => null, 'info' => null, 'next' => (string) ($_GET['next'] ?? '')]);
});

$r->post('/login', static function () use ($nextUrl): void {
    $ip = client_ip();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $next = (string) ($_POST['next'] ?? '');
    $view = static fn (?string $error, ?string $info = null, int $code = 200) => [http_response_code($code), View::render('customer', 'login', ['title' => __('login.customer_title'), 'error' => $error, 'info' => $info, 'next' => $next])];

    if (Throttle::tooMany('customer_login_fail', $ip)) {
        $view(__('login.too_many'), null, 429);
        return;
    }
    $res = CustomerAuth::attempt($email, (string) ($_POST['password'] ?? ''), $ip);
    if ($res === 'ok') {
        redirect($nextUrl($next));
    }
    if ($res === 'no_password') {
        // first access: the account exists but has no password yet -> email a link to create it
        if (Throttle::tooMany('password_mail', $ip)) {
            $view(__('login.too_many'), null, 429);
            return;
        }
        Throttle::hit('password_mail', $ip);
        CustomerAuth::sendPasswordLink(Customers::findByEmail($email));
        $view(null, __('login.first_access_sent', ['email' => $email]));
        return;
    }
    $view(__('login.customer_invalid'), null, 401);
});

$r->post('/logout', static function (): void {
    CustomerAuth::logout();
    redirect('/customer/');
});

// "Forgot your password?" / "First access?"
$r->get('/password', static function (): void {
    View::render('customer', 'password_request', ['title' => __('pw.request_title'), 'sent' => false, 'error' => null]);
});

$r->post('/password', static function (): void {
    $ip = client_ip();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        View::render('customer', 'password_request', ['title' => __('pw.request_title'), 'sent' => false, 'error' => __('val.email_invalid')]);
        return;
    }
    if (Throttle::tooMany('password_mail', $ip)) {
        http_response_code(429);
        View::render('customer', 'password_request', ['title' => __('pw.request_title'), 'sent' => false, 'error' => __('login.too_many')]);
        return;
    }
    Throttle::hit('password_mail', $ip);
    $c = Customers::findByEmail($email);
    if ($c) {
        CustomerAuth::sendPasswordLink($c);
    }
    // same answer whether the email exists or not
    View::render('customer', 'password_request', ['title' => __('pw.request_title'), 'sent' => true, 'error' => null]);
});

$r->get('/password/{token}', static function (array $p): void {
    $c = CustomerAuth::customerForToken((string) $p['token']);
    if (!$c) {
        http_response_code(410);
        View::render('customer', 'link_expired', ['title' => __('pw.link_invalid_title'), 'invalid' => true, 'password' => true]);
        return;
    }
    View::render('customer', 'password_set', ['title' => __(empty($c['password_hash']) ? 'pw.create_title' : 'pw.reset_title'), 'customer' => $c, 'token' => $p['token'], 'error' => null]);
});

$r->post('/password/{token}', static function (array $p): void {
    $c = CustomerAuth::customerForToken((string) $p['token']);
    if (!$c) {
        http_response_code(410);
        View::render('customer', 'link_expired', ['title' => __('pw.link_invalid_title'), 'invalid' => true, 'password' => true]);
        return;
    }
    $error = CustomerAuth::validatePassword((string) ($_POST['password'] ?? ''), (string) ($_POST['confirm'] ?? ''));
    if ($error) {
        http_response_code(422);
        View::render('customer', 'password_set', ['title' => __(empty($c['password_hash']) ? 'pw.create_title' : 'pw.reset_title'), 'customer' => $c, 'token' => $p['token'], 'error' => $error]);
        return;
    }
    CustomerAuth::setPassword($c, (string) $_POST['password'], client_ip());
    flash('success', 'pw.saved');
    redirect('/customer/tickets');
});

// =====================================================================
//  My tickets (signed-in customers)
// =====================================================================
$r->get('/tickets', static function (): void {
    $me = CustomerAuth::user();
    if (!$me) {
        redirect('/customer/login?next=' . rawurlencode('/customer/tickets'));
    }
    View::render('customer', 'tickets', ['title' => __('mytickets.title'), 'me' => $me, 'tickets' => Customers::tickets((int) $me['id'])]);
});

// =====================================================================
//  Open a ticket
// =====================================================================
$r->get('/new', static function (): void {
    View::render('customer', 'new', ['title' => __('new.title'), 'departments' => Tickets::departments(), 'errors' => [], 'me' => CustomerAuth::user()]);
});

$r->post('/new', static function (): void {
    $ip = client_ip();
    $me = CustomerAuth::user();
    $in = [
        // a signed-in customer always opens tickets with their own account
        'name'       => $me ? (string) $me['name'] : trim((string) ($_POST['name'] ?? '')),
        'email'      => $me ? (string) $me['email'] : mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
        'phone'      => trim((string) preg_replace('/\s+/', ' ', (string) ($_POST['phone'] ?? ''))),
        'department' => (int) ($_POST['department'] ?? 0),
        'subject'    => trim((string) ($_POST['subject'] ?? '')),
        'message'    => trim((string) ($_POST['message'] ?? '')),
    ];

    $errors = [];
    // anti-bot honeypot: "website" is hidden, humans leave it empty
    if (!empty($_POST['website'])) {
        $errors[] = __('val.invalid_request');
    }
    if ($in['name'] === '' || mb_strlen($in['name']) > 100) {
        $errors[] = __('val.name_required');
    }
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || strlen($in['email']) > 190) {
        $errors[] = __('val.email_invalid');
    }
    // phone is optional: digits, spaces and + ( ) - . / only, at least 6 digits
    if (!Customers::phoneOk($in['phone'])) {
        $errors[] = __('val.phone_invalid');
    }
    if ($in['subject'] === '' || mb_strlen($in['subject']) > 255) {
        $errors[] = __('val.subject_required');
    }
    if (mb_strlen($in['message']) < 10) {
        $errors[] = __('val.message_min');
    }
    // captcha is single-use: checked (and consumed) on every submit; not needed when signed in
    if (!$me && Captcha::enabled() && !Captcha::verify((string) ($_POST['captcha'] ?? ''))) {
        $errors[] = __('val.captcha');
    }

    $upload = ['files' => [], 'errors' => []];
    if (Attachments::enabled()) {
        $upload = Attachments::fromUpload($_FILES['attachments'] ?? null);
        array_push($errors, ...$upload['errors']);
    }

    $deps = array_map('intval', array_column(Tickets::departments(), 'id'));
    $depId = in_array($in['department'], $deps, true) ? $in['department'] : null;

    if (!$errors && Throttle::tooMany('ticket_create', $ip)) {
        $errors[] = __('val.too_many_tickets');
    }

    if ($errors) {
        http_response_code(422);
        View::render('customer', 'new', ['title' => __('new.title'), 'departments' => Tickets::departments(), 'errors' => $errors, 'me' => $me]);
        return;
    }

    Throttle::hit('ticket_create', $ip);
    $t = Tickets::create($in['name'], $in['email'], $depId, $in['subject'], $in['message'], $ip, Lang::current(), $upload['files'], $in['phone'] !== '' ? $in['phone'] : null);
    // whoever just opened the ticket can see it in this session through its link
    $_SESSION['links'][$t['number']] = ['h' => hash('sha256', basename($t['link'])), 'c' => $t['customer_id']];
    $_SESSION['just_created'] = $t;
    redirect('/customer/created');
});

$r->get('/created', static function (): void {
    $t = $_SESSION['just_created'] ?? null;
    unset($_SESSION['just_created']);
    if (!$t) {
        redirect('/customer/');
    }
    View::render('customer', 'created', [
        'title'  => __('created.title'),
        'number' => $t['number'],
        'link'   => $t['link'],
        'days'   => AccessLinks::days(),
        'me'     => CustomerAuth::user(),
    ]);
});

// =====================================================================
//  Direct ticket link (shown on creation, will be sent by email)
// =====================================================================
$r->get('/t/{token}', static function (array $p): void {
    $res = AccessLinks::check((string) $p['token']);
    if ($res['status'] !== 'ok') {
        http_response_code($res['status'] === 'expired' ? 410 : 404);
        View::render('customer', 'link_expired', [
            'title'   => __($res['status'] === 'expired' ? 'link.expired_title' : 'link.invalid_title'),
            'invalid' => $res['status'] === 'invalid',
        ]);
        return;
    }
    $hash = hash('sha256', (string) $p['token']);
    AccessLinks::touch($hash);
    Session::regenerate();
    $_SESSION['links'][$res['ticket']['number']] = ['h' => $hash, 'c' => (int) $res['link']['customer_id']];
    redirect('/customer/ticket/' . rawurlencode($res['ticket']['number']));
});

// =====================================================================
//  Ticket view, attachments, reply
// =====================================================================
$r->get('/ticket/{number}', static function (array $p) use ($canSee, $denied): void {
    $number = (string) $p['number'];
    // links of previous versions: /customer/ticket/NUMBER?t=TOKEN
    if (isset($_GET['t'])) {
        redirect('/customer/t/' . rawurlencode((string) $_GET['t']));
    }
    $ticket = $canSee($number);
    if (!$ticket) {
        $denied($number);
    }
    $messages = Tickets::messages((int) $ticket['id'], false);
    // operator names never reach the customer page when hidden
    if (config('hide_operator_name', false)) {
        foreach ($messages as &$m) {
            $m['operator_name'] = null;
            $m['operator_id'] = null;
        }
        unset($m);
    }
    View::render('customer', 'ticket', [
        'title'        => __('ticket.title', ['number' => $ticket['number']]),
        'ticket'       => $ticket,
        'messages'     => $messages,
        'attachments'  => Attachments::forMessages(array_column($messages, 'id')),
        'participants' => Tickets::participants((int) $ticket['id']),
        'me'           => CustomerAuth::user(),
    ]);
});

// ---------- Attachment download (only files of a visible ticket, never internal) ----------
$r->get('/ticket/{number}/file/{id}', static function (array $p) use ($canSee): void {
    $number = (string) $p['number'];
    $ticket = $canSee($number);
    $file = $ticket ? Attachments::find((int) $p['id']) : null;
    if (!$file || (int) $file['ticket_id'] !== (int) $ticket['id'] || $file['is_internal']) {
        http_response_code(404);
        View::render('customer', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        return;
    }
    Attachments::send($file, isset($_GET['download']));
});

$r->post('/ticket/{number}/reply', static function (array $p) use ($canSee, $denied): void {
    $number = (string) $p['number'];
    $ticket = $canSee($number);
    if (!$ticket) {
        $denied($number);
    }
    if ($ticket['status'] === 'closed' && !config('allow_reopen', true)) {
        flash('error', 'ticket.closed_no_reply');
        redirect('/customer/ticket/' . $number);
    }
    $body = trim((string) ($_POST['message'] ?? ''));
    if ($body === '') {
        flash('error', 'ticket.empty_message');
    } else {
        Tickets::customerReply($ticket, $body, client_ip(), (int) $ticket['viewer_id']);
        flash('success', 'ticket.reply_sent');
    }
    redirect('/customer/ticket/' . $number);
});

// old URLs of previous versions
$r->get('/status', static function (): void {
    header('Location: /customer/login', true, 301);
});

$r->dispatch();
