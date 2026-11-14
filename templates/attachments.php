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
        <?php $ext = strtoupper(pathinfo((string) $f['original_name'], PATHINFO_EXTENSION)) ?: 'FILE'; ?>
        <li class="<?= $isImg ? 'is-image' : 'is-file file-' . e(strtolower($ext)) ?>">
            <?php if ($isImg): ?>
                <a href="<?= e($url) ?>" target="_blank" rel="noopener"><img src="<?= e($url) ?>" alt="<?= e($f['original_name']) ?>" loading="lazy"></a>
            <?php else: ?>
                <span class="file-icon" aria-hidden="true"><?= e(mb_substr($ext, 0, 4)) ?></span>
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
