<div class="card narrow">
    <h1>Accesso operatori</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/op/login" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <label>Username
            <input type="text" name="username" required autocomplete="username" autofocus value="<?= e(old('username')) ?>">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary" type="submit">Accedi</button>
    </form>
</div>
