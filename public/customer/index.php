<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Bhelpdesk\{Attachments, Captcha, Lang, Router, Session, Throttle, Tickets, View};

Session::start('BHD_CUST', '/customer/');

$r = new Router('/customer', 'customer');

/** May the customer see this ticket? (after number+email check or token link) */
$canSee = static fn (string $number): bool => !empty($_SESSION['tickets'][$number]);

$r->get('/', static function (): void {
    View::render('customer', 'home', ['title' => __('home.title')]);
});

// ---------- Captcha image ----------
$r->get('/captcha', static function (): void {
    Captcha::output();
});

// ---------- Open a ticket ----------
$r->get('/new', static function (): void {
    View::render('customer', 'new', ['title' => __('new.title'), 'departments' => Tickets::departments(), 'errors' => []]);
});

$r->post('/new', static function (): void {
    $ip = client_ip();
    $in = [
        'name'       => trim((string) ($_POST['name'] ?? '')),
        'email'      => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
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
    if ($in['subject'] === '' || mb_strlen($in['subject']) > 255) {
        $errors[] = __('val.subject_required');
    }
    if (mb_strlen($in['message']) < 10) {
        $errors[] = __('val.message_min');
    }
    // captcha is single-use: checked (and consumed) on every submit
    if (Captcha::enabled() && !Captcha::verify((string) ($_POST['captcha'] ?? ''))) {
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
        View::render('customer', 'new', ['title' => __('new.title'), 'departments' => Tickets::departments(), 'errors' => $errors]);
        return;
    }

    Throttle::hit('ticket_create', $ip);
    $t = Tickets::create($in['name'], $in['email'], $depId, $in['subject'], $in['message'], $ip, Lang::current(), $upload['files']);
    $_SESSION['tickets'][$t['number']] = true;
    $_SESSION['just_created'] = $t;
    redirect('/customer/created');
});

$r->get('/created', static function (): void {
    $t = $_SESSION['just_created'] ?? null;
    unset($_SESSION['just_created']);
    if (!$t) {
        redirect('/customer/');
    }
    $link = rtrim((string) config('base_url'), '/') . '/customer/ticket/' . $t['number'] . '?t=' . $t['token'];
    View::render('customer', 'created', ['title' => __('created.title'), 'number' => $t['number'], 'link' => $link]);
});

// ---------- Status lookup ----------
$r->get('/status', static function (): void {
    View::render('customer', 'status', ['title' => __('lookup.title'), 'error' => null]);
});

$r->post('/status', static function (): void {
    $ip = client_ip();
    $number = preg_replace('/\D/', '', (string) ($_POST['number'] ?? ''));
    $email  = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

    if (Throttle::tooMany('status_lookup', $ip)) {
        http_response_code(429);
        View::render('customer', 'status', ['title' => __('lookup.title'), 'error' => __('lookup.too_many')]);
        return;
    }

    $ticket = ($number !== '' && $email !== '') ? Tickets::findForCustomer($number, $email) : null;
    if (!$ticket) {
        Throttle::hit('status_lookup', $ip);
        http_response_code(422);
        View::render('customer', 'status', ['title' => __('lookup.title'), 'error' => __('lookup.not_found')]);
        return;
    }

    session_regenerate_id(true);
    $_SESSION['tickets'][$ticket['number']] = true;
    redirect('/customer/ticket/' . $ticket['number']);
});

// ---------- Ticket view ----------
$r->get('/ticket/{number}', static function (array $p) use ($canSee): void {
    $number = (string) $p['number'];

    // access through the token link (shown on creation / sent by email)
    if (isset($_GET['t'])) {
        $ticket = Tickets::findByNumber($number);
        if ($ticket && hash_equals($ticket['access_token'], (string) $_GET['t'])) {
            session_regenerate_id(true);
            $_SESSION['tickets'][$number] = true;
        }
        redirect('/customer/ticket/' . rawurlencode($number));
    }

    $ticket = $canSee($number) ? Tickets::findByNumber($number) : null;
    if (!$ticket) {
        flash('error', 'lookup.need_login');
        redirect('/customer/status');
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
        'title'       => __('ticket.title', ['number' => $ticket['number']]),
        'ticket'      => $ticket,
        'messages'    => $messages,
        'attachments' => Attachments::forMessages(array_column($messages, 'id')),
    ]);
});

// ---------- Attachment download (only files of the customer's own ticket, never internal) ----------
$r->get('/ticket/{number}/file/{id}', static function (array $p) use ($canSee): void {
    $number = (string) $p['number'];
    $ticket = $canSee($number) ? Tickets::findByNumber($number) : null;
    $file = $ticket ? Attachments::find((int) $p['id']) : null;
    if (!$file || (int) $file['ticket_id'] !== (int) $ticket['id'] || $file['is_internal']) {
        http_response_code(404);
        View::render('customer', 'error', ['title' => __('error.not_found.title'), 'message' => __('error.not_found.message')]);
        return;
    }
    Attachments::send($file, isset($_GET['download']));
});

$r->post('/ticket/{number}/reply', static function (array $p) use ($canSee): void {
    $number = (string) $p['number'];
    $ticket = $canSee($number) ? Tickets::findByNumber($number) : null;
    if (!$ticket) {
        redirect('/customer/status');
    }
    if ($ticket['status'] === 'closed' && !config('allow_reopen', true)) {
        flash('error', 'ticket.closed_no_reply');
        redirect('/customer/ticket/' . $number);
    }
    $body = trim((string) ($_POST['message'] ?? ''));
    if ($body === '') {
        flash('error', 'ticket.empty_message');
    } else {
        Tickets::customerReply($ticket, $body, client_ip());
        flash('success', 'ticket.reply_sent');
    }
    redirect('/customer/ticket/' . $number);
});

$r->post('/logout', static function (): void {
    unset($_SESSION['tickets']);
    redirect('/customer/');
});

$r->dispatch();
