<?php
use Bhelpdesk\Tickets;

$ccRows = $values['cc'] ?: [['email' => '', 'name' => '']];
?>
<p><a href="/op/">← <?= t('nt.back') ?></a></p>
<h1><?= t('nt.title') ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-error"><ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if (!$departments): ?>
    <div class="alert alert-warning"><?= t('nt.no_departments') ?></div>
<?php else: ?>
<form method="post" action="/op/tickets/new" class="form new-ticket" enctype="multipart/form-data">
    <?= \Bhelpdesk\Csrf::field() ?>
    <input type="hidden" name="format" value="text">

    <div class="layout-side">
        <div>
            <!-- 1. customer -->
            <section class="card">
                <h2><span class="step">1</span> <?= t('nt.customer') ?></h2>
                <div class="row-2">
                    <label><?= t('tc.email_or_search') ?>
                        <input type="email" name="email" required autocomplete="off" list="nt-main-list" value="<?= e($values['email']) ?>"
                               data-customer-lookup="nt-main-list" data-name-field="nt-main-name">
                    </label>
                    <label><?= t('cust.name') ?> <span class="optional"><?= t('tc.name_hint') ?></span>
                        <input type="text" name="name" id="nt-main-name" maxlength="100" value="<?= e($values['name']) ?>">
                    </label>
                </div>
                <datalist id="nt-main-list"></datalist>

                <div class="field">
                    <span class="label"><?= t('nt.cc') ?> <span class="optional"><?= t('new.optional') ?></span></span>
                    <div class="cc-rows" id="cc-rows">
                        <?php foreach ($ccRows as $i => $row): ?>
                        <div class="row-2 cc-row">
                            <input type="email" name="cc_email[]" autocomplete="off" placeholder="<?= t('nt.cc_email') ?>" value="<?= e($row['email']) ?>"
                                   list="nt-cc-list-<?= $i ?>" data-customer-lookup="nt-cc-list-<?= $i ?>" data-name-field="nt-cc-name-<?= $i ?>">
                            <input type="text" name="cc_name[]" id="nt-cc-name-<?= $i ?>" maxlength="100" placeholder="<?= t('nt.cc_name') ?>" value="<?= e($row['name']) ?>">
                            <datalist id="nt-cc-list-<?= $i ?>"></datalist>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-small" data-add-cc="cc-rows" hidden>+ <?= t('nt.cc_add') ?></button>
                    <small class="muted hint"><?= t('nt.cc_hint') ?></small>
                </div>
            </section>

            <!-- 2. customer request -->
            <section class="card">
                <h2><span class="step">2</span> <?= t('nt.request') ?></h2>
                <label><?= t('new.subject') ?>
                    <input type="text" name="subject" required maxlength="255" value="<?= e($values['subject']) ?>">
                </label>
                <label><?= t('nt.request_text') ?>
                    <textarea name="request" rows="7" required placeholder="<?= t('nt.request_placeholder') ?>"><?= e($values['request']) ?></textarea>
                </label>
                <label class="op-files"><?= t('nt.request_files') ?> <span class="optional"><?= t('new.optional') ?></span>
                    <input type="file" name="request_files[]" multiple accept="<?= e(\Bhelpdesk\Attachments::acceptAttr('operator')) ?>"
                           data-max-files="<?= \Bhelpdesk\Attachments::maxFiles('operator') ?>"
                           data-max-bytes="<?= \Bhelpdesk\Attachments::maxSizeMb('operator') * 1048576 ?>"
                           data-msg-count="<?= t('val.too_many_files', ['max' => \Bhelpdesk\Attachments::maxFiles('operator')]) ?>"
                           data-list="nt-request-files">
                </label>
                <ul id="nt-request-files" class="file-list"></ul>
            </section>

            <!-- 3. optional first reply -->
            <section class="card">
                <h2><span class="step">3</span> <?= t('nt.reply') ?> <span class="optional"><?= t('new.optional') ?></span></h2>
                <p class="muted hint"><?= t('nt.reply_hint') ?></p>
                <textarea name="reply" rows="6" data-editor="full"><?= e($values['reply']) ?></textarea>
                <?php $fileId = 'nt-reply-files'; require BHD_ROOT . '/templates/op/_op_files.php'; ?>
                <label class="inline-num"><?= t('opticket.status_after') ?>
                    <select name="status">
                        <?php foreach (Tickets::STATUSES as $k): ?>
                            <option value="<?= e($k) ?>" <?= $k === $values['status'] ? 'selected' : '' ?>><?= e(status_label($k)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </section>
        </div>

        <aside class="card side-sticky">
            <h2><?= t('opticket.manage') ?></h2>
            <label><?= t('col.department') ?>
                <select name="department" required>
                    <?php if (count($departments) > 1): ?><option value=""><?= t('nt.choose') ?></option><?php endif; ?>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?= (int) $d['id'] ?>" <?= (int) $values['department'] === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= t('col.priority') ?>
                <select name="priority">
                    <?php foreach (Tickets::PRIORITIES as $k): ?>
                        <option value="<?= e($k) ?>" <?= $k === $values['priority'] ? 'selected' : '' ?>><?= e(priority_label($k)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="check"><input type="checkbox" name="assign_me" value="1" <?= $values['assign_me'] ? 'checked' : '' ?>> <?= t('nt.assign_me') ?></label>
            <button class="btn btn-primary btn-block" type="submit"><?= t('nt.create') ?></button>
            <p class="muted hint"><?= t('nt.link_hint') ?></p>
        </aside>
    </div>
</form>
<?php endif; ?>
