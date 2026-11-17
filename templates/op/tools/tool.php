<?php
use Bhelpdesk\{Csrf, NetTools};
$placeholders = ['mtr' => '8.8.8.8 / www.example.com', 'ping' => '8.8.8.8 / www.example.com', 'whois' => 'example.com', 'ipinfo' => '8.8.8.8 / 2001:4860:4860::8888 / www.example.com'];
$labels = ['inetnum' => 'inetnum', 'inet6num' => 'inet6num', 'netrange' => 'NetRange', 'cidr' => 'CIDR', 'netname' => 'netname', 'descr' => 'descr', 'org-name' => 'org-name', 'orgname' => 'OrgName', 'organization' => 'Organization', 'country' => 'country', 'abuse-mailbox' => 'abuse-mailbox', 'orgabuseemail' => 'OrgAbuseEmail', 'origin' => 'origin', 'originas' => 'OriginAS'];
?>
<div class="page-head">
    <h1><?= t('tools.' . $tool) ?></h1>
</div>

<nav class="tools-tabs">
    <?php foreach (NetTools::TOOLS as $tl): ?>
        <a href="/op/tools/<?= $tl ?>"<?= $tl === $tool ? ' class="active"' : '' ?>><?= t('tools.' . $tl) ?></a>
    <?php endforeach; ?>
</nav>

<section class="card tools-card">
    <p class="muted hint"><?= t('tools.help_' . $tool) ?></p>
    <form method="post" action="/op/tools/<?= e($tool) ?>" class="tools-form" data-tools-form>
        <?= Csrf::field() ?>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e($placeholders[$tool]) ?>" required maxlength="255" autofocus autocomplete="off" spellcheck="false">
        <button class="btn btn-primary" type="submit" data-busy-label="<?= t('tools.running') ?>"><?= t('tools.run') ?></button>
    </form>
    <?php if ($tool === 'mtr'): ?><p class="muted small"><?= t('tools.mtr_wait') ?></p><?php endif; ?>
</section>

<?php if ($error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($result): ?>
<section class="card tools-result">
    <div class="tools-result-head">
        <code><?= e($tool) ?> <?= e($target) ?></code>
        <span class="muted small"><?= t('tools.took', ['s' => $result['seconds']]) ?></span>
    </div>
    <?php if ($result['error']): ?><div class="alert alert-error"><?= e($result['error']) ?></div><?php endif; ?>
    <pre class="tools-output"><?= e($result['output'] !== '' ? $result['output'] : __('tools.no_output')) ?></pre>
</section>
<?php endif; ?>

<?php if ($info): ?>
    <?php foreach ($info as $i): ?>
    <section class="card tools-result">
        <div class="tools-result-head"><code><?= e($i['ip']) ?></code> <span><span class="badge">IPv<?= (int) $i['version'] ?></span><?= $i['private'] ? ' <span class="badge">' . t('tools.ip_private') . '</span>' : '' ?></span></div>
        <table class="kv">
            <tr><th><?= t('tools.ip_reverse') ?></th><td><?= $i['ptr'] ? e($i['ptr']) : '<span class="muted">' . t('tools.none') . '</span>' ?></td></tr>
            <tr><th>AS</th><td><?= $i['asn'] ? '<strong>AS' . e($i['asn']) . '</strong>' . ($i['as_name'] ? ' · ' . e($i['as_name']) : '') : '<span class="muted">' . t('tools.ip_no_as') . '</span>' ?></td></tr>
            <?php if ($i['asn']): ?>
            <tr><th><?= t('tools.ip_prefix') ?></th><td><?= e((string) $i['prefix']) ?></td></tr>
            <tr><th><?= t('tools.ip_country') ?></th><td><?= e(NetTools::countryName($i['country'])) ?></td></tr>
            <tr><th><?= t('tools.ip_registry') ?></th><td><?= e((string) $i['registry']) ?><?= $i['allocated'] ? ' · ' . t('tools.ip_allocated') . ' ' . e((string) $i['allocated']) : '' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($i['whois_fields'] as $k => $vals): ?>
            <tr><th><?= e($labels[$k] ?? $k) ?></th><td><?= e(implode(' · ', $vals)) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <?php if ($i['whois'] !== ''): ?>
        <details class="tools-raw"><summary><?= t('tools.ip_whois_full') ?></summary><pre class="tools-output"><?= e($i['whois']) ?></pre></details>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
<?php endif; ?>
