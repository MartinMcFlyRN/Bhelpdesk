<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Bhelpdesk\{Router, Session, Throttle, Tickets, View};

Session::start('BHD_CUST', '/customer/');

$r = new Router('/customer', 'customer');

/** Il cliente ha diritto a vedere il ticket? (dopo verifica numero+email o link con token) */
$canSee = static fn (string $number): bool => !empty($_SESSION['tickets'][$number]);

$r->get('/', static function (): void {
    View::render('customer', 'home', ['title' => 'Assistenza']);
});

// ---------- Apertura ticket ----------
$r->get('/new', static function (): void {
    View::render('customer', 'new', ['title' => 'Apri un ticket', 'departments' => Tickets::departments(), 'errors' => []]);
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
    // honeypot anti-bot: il campo "website" è nascosto, un umano non lo compila
    if (!empty($_POST['website'])) {
        $errors[] = 'Richiesta non valida.';
    }
    if ($in['name'] === '' || mb_strlen($in['name']) > 100) {
        $errors[] = 'Inserisci il tuo nome.';
    }
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL) || strlen($in['email']) > 190) {
        $errors[] = 'Indirizzo email non valido.';
    }
    if ($in['subject'] === '' || mb_strlen($in['subject']) > 255) {
        $errors[] = 'Inserisci un oggetto (max 255 caratteri).';
    }
    if (mb_strlen($in['message']) < 10) {
        $errors[] = 'Descrivi il problema (almeno 10 caratteri).';
    }
    $deps = array_column(Tickets::departments(), 'id');
    $depId = in_array($in['department'], array_map('intval', $deps), true) ? $in['department'] : null;

    if (!$errors && Throttle::tooMany('ticket_create', $ip)) {
        $errors[] = 'Hai aperto troppi ticket in poco tempo. Riprova più tardi.';
    }

    if ($errors) {
        http_response_code(422);
        View::render('customer', 'new', ['title' => 'Apri un ticket', 'departments' => Tickets::departments(), 'errors' => $errors]);
        return;
    }

    Throttle::hit('ticket_create', $ip);
    $t = Tickets::create($in['name'], $in['email'], $depId, $in['subject'], $in['message'], $ip);
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
    View::render('customer', 'created', ['title' => 'Ticket aperto', 'number' => $t['number'], 'link' => $link]);
});

// ---------- Verifica stato ----------
$r->get('/status', static function (): void {
    View::render('customer', 'status', ['title' => 'Verifica stato ticket', 'error' => null]);
});

$r->post('/status', static function (): void {
    $ip = client_ip();
    $number = preg_replace('/\D/', '', (string) ($_POST['number'] ?? ''));
    $email  = mb_strtolower(trim((string) ($_POST['email'] ?? '')));

    if (Throttle::tooMany('status_lookup', $ip)) {
        http_response_code(429);
        View::render('customer', 'status', ['title' => 'Verifica stato ticket', 'error' => 'Troppi tentativi. Riprova tra qualche minuto.']);
        return;
    }

    $ticket = ($number !== '' && $email !== '') ? Tickets::findForCustomer($number, $email) : null;
    if (!$ticket) {
        Throttle::hit('status_lookup', $ip);
        http_response_code(422);
        View::render('customer', 'status', ['title' => 'Verifica stato ticket', 'error' => 'Nessun ticket trovato con questi dati.']);
        return;
    }

    session_regenerate_id(true);
    $_SESSION['tickets'][$ticket['number']] = true;
    redirect('/customer/ticket/' . $ticket['number']);
});

// ---------- Dettaglio ticket ----------
$r->get('/ticket/{number}', static function (array $p) use ($canSee): void {
    $number = (string) $p['number'];

    // accesso tramite link con token (ricevuto all'apertura / via email)
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
        flash('error', 'Per vedere il ticket inserisci numero ed email.');
        redirect('/customer/status');
    }
    View::render('customer', 'ticket', [
        'title'    => 'Ticket #' . $ticket['number'],
        'ticket'   => $ticket,
        'messages' => Tickets::messages((int) $ticket['id'], false),
    ]);
});

$r->post('/ticket/{number}/reply', static function (array $p) use ($canSee): void {
    $number = (string) $p['number'];
    $ticket = $canSee($number) ? Tickets::findByNumber($number) : null;
    if (!$ticket) {
        redirect('/customer/status');
    }
    if ($ticket['status'] === 'closed' && !config('allow_reopen', true)) {
        flash('error', 'Il ticket è chiuso: aprine uno nuovo.');
        redirect('/customer/ticket/' . $number);
    }
    $body = trim((string) ($_POST['message'] ?? ''));
    if ($body === '') {
        flash('error', 'Il messaggio è vuoto.');
    } else {
        Tickets::customerReply($ticket, $body, client_ip());
        flash('success', 'Risposta inviata.');
    }
    redirect('/customer/ticket/' . $number);
});

$r->post('/logout', static function (): void {
    unset($_SESSION['tickets']);
    redirect('/customer/');
});

$r->dispatch();
