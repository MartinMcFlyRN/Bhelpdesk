<?php
/**
 * Attachment list under a message.
 * Expects: $files (list of attachment rows), $fileUrl (callable: int id => string url)
 */
use Bhelpdesk\Attachments;

if (!empty($files)): ?>
<div class="attachments">
    <strong class="attachments-title"><?= t('attach.title') ?></strong>
    <ul>
    <?php foreach ($files as $f): $url = $fileUrl((int) $f['id']); $isImg = str_starts_with($f['mime'], 'image/'); ?>
        <li class="<?= $isImg ? 'is-image' : 'is-pdf' ?>">
            <?php if ($isImg): ?>
                <a href="<?= e($url) ?>" target="_blank" rel="noopener"><img src="<?= e($url) ?>" alt="<?= e($f['original_name']) ?>" loading="lazy"></a>
            <?php else: ?>
                <span class="file-icon" aria-hidden="true">PDF</span>
            <?php endif; ?>
            <span class="file-meta">
                <a href="<?= e($url) ?>?download=1"><?= e($f['original_name']) ?></a>
                <small class="muted"><?= e(Attachments::humanSize((int) $f['size'])) ?> · <?= t('attach.download') ?></small>
            </span>
        </li>
    <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
