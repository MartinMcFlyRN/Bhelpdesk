<div class="card narrow">
    <h1>Verifica stato ticket</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/customer/status" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <label>Numero ticket
            <input type="text" name="number" required inputmode="numeric" value="<?= e(old('number')) ?>" placeholder="es. 482913">
        </label>
        <label>Email usata all'apertura
            <input type="email" name="email" required value="<?= e(old('email')) ?>">
        </label>
        <button class="btn btn-primary" type="submit">Verifica</button>
    </form>
</div>
