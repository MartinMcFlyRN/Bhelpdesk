<div class="card narrow">
    <h1>Apri un ticket</h1>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/customer/new" class="form">
        <?= \Bhelpdesk\Csrf::field() ?>
        <div class="hp" aria-hidden="true"><label>Sito web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="row-2">
            <label>Nome e cognome
                <input type="text" name="name" required maxlength="100" value="<?= e(old('name')) ?>">
            </label>
            <label>Email
                <input type="email" name="email" required maxlength="190" value="<?= e(old('email')) ?>">
            </label>
        </div>
        <?php if ($departments): ?>
        <label>Reparto
            <select name="department">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= old('department') == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label>Oggetto
            <input type="text" name="subject" required maxlength="255" value="<?= e(old('subject')) ?>">
        </label>
        <label>Descrizione del problema
            <textarea name="message" rows="8" required minlength="10"><?= e(old('message')) ?></textarea>
        </label>
        <button class="btn btn-primary" type="submit">Invia richiesta</button>
    </form>
</div>
