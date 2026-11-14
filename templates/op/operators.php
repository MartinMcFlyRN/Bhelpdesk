<h1>Operatori</h1>
<table class="table">
    <thead><tr><th>Username</th><th>Nome</th><th>Email</th><th>Ruolo</th><th>Ultimo accesso</th><th>Stato</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($operators as $o): ?>
        <tr class="<?= $o['active'] ? '' : 'disabled' ?>">
            <td><?= e($o['username']) ?></td>
            <td><?= e($o['name']) ?></td>
            <td><?= e($o['email']) ?></td>
            <td><?= $o['role'] === 'admin' ? 'Amministratore' : 'Operatore' ?></td>
            <td><?= fmt_date($o['last_login_at']) ?><?= $o['last_login_ip'] ? '<br><small class="muted">' . e($o['last_login_ip']) . '</small>' : '' ?></td>
            <td><?= $o['active'] ? 'Attivo' : 'Disattivato' ?></td>
            <td>
                <?php if ((int) $o['id'] !== (int) $op['id']): ?>
                <form method="post" action="/op/operators/<?= (int) $o['id'] ?>/toggle" class="inline">
                    <?= \Bhelpdesk\Csrf::field() ?>
                    <button class="btn btn-small" type="submit"><?= $o['active'] ? 'Disattiva' : 'Riattiva' ?></button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div class="card narrow">
    <h2>Nuovo operatore</h2>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/op/operators" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <div class="row-2">
            <label>Username <input type="text" name="username" required value="<?= e(old('username')) ?>"></label>
            <label>Nome <input type="text" name="name" required value="<?= e(old('name')) ?>"></label>
        </div>
        <div class="row-2">
            <label>Email <input type="email" name="email" required value="<?= e(old('email')) ?>"></label>
            <label>Ruolo
                <select name="role">
                    <option value="agent">Operatore</option>
                    <option value="admin" <?= old('role') === 'admin' ? 'selected' : '' ?>>Amministratore</option>
                </select>
            </label>
        </div>
        <label>Password (min. 12 caratteri) <input type="password" name="password" required minlength="12" autocomplete="new-password"></label>
        <button class="btn btn-primary" type="submit">Crea operatore</button>
    </form>
</div>
