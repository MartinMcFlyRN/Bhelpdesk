<?php
/** Operator attachments field. Expects: $fileId */
use Bhelpdesk\Attachments;
?>
<label class="op-files"><?= t('opticket.attach') ?> <span class="optional"><?= t('new.optional') ?></span>
    <input type="file" name="attachments[]" multiple
           accept="<?= e(Attachments::acceptAttr('operator')) ?>"
           data-max-files="<?= Attachments::maxFiles('operator') ?>"
           data-max-bytes="<?= Attachments::maxSizeMb('operator') * 1048576 ?>"
           data-msg-count="<?= t('val.too_many_files', ['max' => Attachments::maxFiles('operator')]) ?>"
           data-list="<?= e($fileId) ?>">
    <small class="muted hint"><?= t('opticket.attach_hint', ['types' => Attachments::extList('operator'), 'count' => Attachments::maxFiles('operator'), 'size' => Attachments::maxSizeMb('operator')]) ?></small>
</label>
<ul id="<?= e($fileId) ?>" class="file-list"></ul>
