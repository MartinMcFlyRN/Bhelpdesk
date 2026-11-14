<div class="card narrow">
    <h1>Ticket aperto</h1>
    <p>La tua richiesta è stata registrata con il numero:</p>
    <p class="ticket-number">#<?= e($number) ?></p>
    <p>Conserva il numero: con la tua email potrai verificare lo stato in qualsiasi momento.
       In alternativa salva questo link di accesso diretto (non condividerlo):</p>
    <p><input type="text" readonly class="mono" value="<?= e($link) ?>"></p>
    <p><a class="btn btn-primary" href="/customer/ticket/<?= e($number) ?>">Vai al ticket</a></p>
</div>
