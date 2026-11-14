<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Bhelpdesk\{Auth, Db, IpGuard, Router, Session, Throttle, Tickets, View};

// ---------- 2° livello di filtro IP (il primo è in nginx) ----------
$allowed = (array) config('op_allowed_ips', []);
if ($allowed && !IpGuard::matchesAny(client_ip(), $allowed)) {
    http_response_code(403);
    exit('Accesso non consentito.');
}

Session::start('BHD_OP', '/op/');
header('Cache-Control: no-store');

$r = new Router('/op', 'op');

// ---------- Login / logout ----------
$r->get('/login', static function (): void {
    if (Auth::user()) {
        redirect('/op/');
    }
    View::render('op', 'login', ['title' => 'Accesso operatori', 'error' => null]);
});

$r->post('/login', static function (): void {
    $ip = client_ip();
    if (Throttle::tooMany('op_login_fail', $ip)) {
        http_response_code(429);
        View::render('op', 'login', ['title' => 'Accesso operatori', 'error' => 'Troppi tentativi falliti. Riprova più tardi.']);
        return;
    }
    $ok = Auth::attempt(trim((string) ($_POST['username'] ?? '')), (string) ($_POST['password'] ?? ''), $ip);
    if (!$ok) {
        http_response_code(401);
        View::render('op', 'login', ['title' => 'Accesso operatori', 'error' => 'Credenziali non valide.']);
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
        'title'       => 'Ticket',
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
        View::render('op', 'error', ['title' => 'Ticket non trovato', 'message' => 'Il ticket richiesto non esiste.']);
        exit;
    }
    return $t;
};

$r->get('/ticket/{id}', static function (array $p) use ($loadTicket): void {
    $op = Auth::require();
    $t = $loadTicket($p);
    View::render('op', 'ticket', [
        'title'       => '#' . $t['number'] . ' ' . $t['subject'],
        'op'          => $op,
        'ticket'      => $t,
        'messages'    => Tickets::messages((int) $t['id'], true),
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
        flash('error', 'Il messaggio è vuoto.');
    } else {
        Tickets::operatorReply($t, $op, $body, $internal, (string) ($_POST['status'] ?? $default), client_ip());
        if (!$internal && !$t['assigned_to']) {
            Tickets::update(Tickets::find((int) $t['id']), $op, ['assigned_to' => $op['id']]);
        }
        flash('success', $internal ? 'Nota interna aggiunta.' : 'Risposta inviata.');
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
    flash('success', 'Ticket aggiornato.');
    redirect('/op/ticket/' . $t['id']);
});

// ---------- Gestione operatori (solo admin) ----------
$r->get('/operators', static function (): void {
    $op = Auth::requireAdmin();
    View::render('op', 'operators', [
        'title'     => 'Operatori',
        'op'        => $op,
        'operators' => Db::all('SELECT id, username, name, email, role, active, last_login_at, last_login_ip FROM operators ORDER BY name'),
        'errors'    => [],
    ]);
});

$r->post('/operators', static function (): void {
    $op = Auth::requireAdmin();
    $in = [
        'username' => trim((string) ($_POST['username'] ?? '')),
        'name'     => trim((string) ($_POST['name'] ?? '')),
        'email'    => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
        'password' => (string) ($_POST['password'] ?? ''),
        'role'     => ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'agent',
    ];
    $errors = [];
    if (!preg_match('/^[a-z0-9._-]{3,50}$/i', $in['username'])) {
        $errors[] = 'Username: 3-50 caratteri tra lettere, numeri, . _ -';
    }
    if ($in['name'] === '') {
        $errors[] = 'Nome obbligatorio.';
    }
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email non valida.';
    }
    if (strlen($in['password']) < 12) {
        $errors[] = 'La password deve avere almeno 12 caratteri.';
    }
    if (!$errors && Db::value('SELECT 1 FROM operators WHERE username = ? OR email = ?', [$in['username'], $in['email']])) {
        $errors[] = 'Username o email già in uso.';
    }
    if ($errors) {
        http_response_code(422);
        View::render('op', 'operators', [
            'title'     => 'Operatori',
            'op'        => $op,
            'operators' => Db::all('SELECT id, username, name, email, role, active, last_login_at, last_login_ip FROM operators ORDER BY name'),
            'errors'    => $errors,
        ]);
        return;
    }
    Db::insert(
        'INSERT INTO operators (username, name, email, password_hash, role) VALUES (?, ?, ?, ?, ?)',
        [$in['username'], $in['name'], $in['email'], password_hash($in['password'], PASSWORD_DEFAULT), $in['role']]
    );
    flash('success', 'Operatore creato.');
    redirect('/op/operators');
});

$r->post('/operators/{id}/toggle', static function (array $p): void {
    $op = Auth::requireAdmin();
    $id = (int) $p['id'];
    if ($id === (int) $op['id']) {
        flash('error', 'Non puoi disattivare te stesso.');
    } else {
        Db::exec('UPDATE operators SET active = 1 - active WHERE id = ?', [$id]);
        flash('success', 'Stato operatore aggiornato.');
    }
    redirect('/op/operators');
});

$r->dispatch();
