<p><a href="<?= e($back) ?>">← <?= t('src.back') ?></a></p>
<div class="page-head">
    <h1><?= t('src.title') ?></h1>
    <a class="btn" href="?download=1"><?= t('src.download') ?></a>
</div>

<div class="card">
    <dl class="meta src-headers">
        <?php foreach (['from' => 'From', 'to' => 'To', 'cc' => 'Cc', 'reply-to' => 'Reply-To', 'date' => 'Date', 'subject' => 'Subject', 'message-id' => 'Message-ID'] as $h => $label): ?>
            <?php if ($mime->has($h)): ?><dt><?= e($label) ?></dt><dd><?= e($mime->header($h)) ?></dd><?php endif; ?>
        <?php endforeach; ?>
        <dt><?= t('src.received') ?></dt><dd><?= e(fmt_date((string) $row['created_at'])) ?> · <?= e(\Bhelpdesk\Attachments::humanSize($size)) ?><?= $row['note'] ? ' · ' . e((string) $row['note']) : '' ?></dd>
    </dl>
</div>

<?php if ($truncated): ?><p class="alert alert-error"><?= t('src.truncated') ?></p><?php endif; ?>
<pre class="eml-source"><?= e($source) ?></pre>
