<?php use Bhelpdesk\{Attachments, Captcha}; ?>
<div class="card narrow">
    <h1><?= t('new.title') ?></h1>
    <?php if ($errors): ?>
        <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" action="/customer/new" class="form" enctype="multipart/form-data">
        <?= \Bhelpdesk\Csrf::field() ?>
        <div class="hp" aria-hidden="true"><label><?= t('new.honeypot') ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="row-2">
            <label><?= t('new.name') ?>
                <input type="text" name="name" required maxlength="100" value="<?= e(old('name')) ?>">
            </label>
            <label><?= t('new.email') ?>
                <input type="email" name="email" required maxlength="190" value="<?= e(old('email')) ?>">
            </label>
        </div>
        <?php if ($departments): ?>
        <label><?= t('new.department') ?>
            <select name="department">
                <?php foreach ($departments as $d): ?>
                    <option value="<?= (int) $d['id'] ?>" <?= old('department') == $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>
        <label><?= t('new.subject') ?>
            <input type="text" name="subject" required maxlength="255" value="<?= e(old('subject')) ?>">
        </label>
        <label><?= t('new.message') ?>
            <textarea name="message" rows="8" required minlength="10"><?= e(old('message')) ?></textarea>
        </label>

        <?php if (Attachments::enabled()): ?>
        <label><?= t('new.attachments') ?>
            <input type="file" name="attachments[]" multiple
                   accept="<?= e(Attachments::acceptAttr()) ?>"
                   data-max-files="<?= Attachments::maxFiles() ?>"
                   data-max-bytes="<?= Attachments::maxSizeMb() * 1048576 ?>"
                   data-msg-count="<?= t('val.too_many_files', ['max' => Attachments::maxFiles()]) ?>"
                   data-list="attachment-list">
            <small class="muted hint"><?= t('new.attachments_hint', ['count' => Attachments::maxFiles(), 'size' => Attachments::maxSizeMb()]) ?></small>
        </label>
        <ul id="attachment-list" class="file-list"></ul>
        <?php if ($errors): ?><p class="muted hint"><?= t('new.attachments_reselect') ?></p><?php endif; ?>
        <?php endif; ?>

        <?php if (Captcha::enabled()): ?>
        <div class="captcha">
            <span class="label"><?= t('captcha.label') ?></span>
            <div class="captcha-row">
                <img id="captcha-img" src="/customer/captcha?<?= time() ?>" width="180" height="60" alt="<?= t('captcha.alt') ?>">
                <button type="button" class="btn btn-small" data-captcha-refresh="captcha-img" hidden><?= t('captcha.refresh') ?></button>
            </div>
            <label><span class="sr-only"><?= t('captcha.label') ?></span>
                <input type="text" name="captcha" required maxlength="10" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="<?= t('captcha.hint') ?>">
            </label>
        </div>
        <?php endif; ?>

        <button class="btn btn-primary" type="submit"><?= t('new.submit') ?></button>
    </form>
</div>
