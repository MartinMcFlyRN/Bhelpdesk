<?php
use Bhelpdesk\{Csrf, NetTools};
$placeholders = ['mtr' => '8.8.8.8 / www.example.com', 'ping' => '8.8.8.8 / www.example.com', 'whois' => 'example.com', 'ipinfo' => '8.8.8.8 / 2001:4860:4860::8888 / www.example.com', 'dns' => 'example.com / _dmarc.example.com / 8.8.8.8 (PTR)'];
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
    <form method="post" action="/op/tools/<?= e($tool) ?>" class="tools-form" id="tools-form" data-tools-form>
        <?= Csrf::field() ?>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= e($placeholders[$tool]) ?>" required maxlength="255" autofocus autocomplete="off" spellcheck="false">
        <?php if ($tool === 'dns'): ?>
        <select name="type" aria-label="<?= t('tools.dns_type') ?>">
            <?php foreach (NetTools::DNS_TYPES as $ty): ?>
                <option value="<?= $ty ?>" <?= $ty === $dnsType ? 'selected' : '' ?>><?= $ty === 'ALL' ? t('tools.dns_all') : $ty ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <button class="btn btn-primary" type="submit" data-busy-label="<?= t('tools.running') ?>"><?= t('tools.run') ?></button>
    </form>
    <?php if ($tool === 'mtr'): ?><p class="muted small"><?= t('tools.mtr_wait') ?></p><?php endif; ?>
    <?php if ($tool === 'dns'): $presetSel = $dnsCustom === '' ? $dnsServer : null; ?>
    <div class="tools-dns-server">
        <label><?= t('tools.dns_server') ?>
            <select name="server" form="tools-form">
                <option value=""<?= $presetSel === '' ? ' selected' : '' ?>><?= t('tools.dns_server_default') ?></option>
                <option value="auth"<?= $presetSel === 'auth' ? ' selected' : '' ?>><?= t('tools.dns_server_auth') ?></option>
                <?php foreach (NetTools::DNS_SERVERS as $ip => $label): ?>
                    <option value="<?= e($ip) ?>"<?= $presetSel === $ip ? ' selected' : '' ?>><?= e($label . ' (' . $ip . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= t('tools.dns_server_custom') ?>
            <input type="text" name="server_custom" form="tools-form" value="<?= e($dnsCustom) ?>" placeholder="ns1.example.com / 192.0.2.53" maxlength="255" autocomplete="off" spellcheck="false">
        </label>
    </div>
    <?php endif; ?>
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

<?php if ($dns): ?>
<section class="card tools-result">
    <div class="tools-result-head">
        <code><?= e($q) ?></code>
        <span class="muted small"><?= t('tools.dns_asked') ?> <strong><?= $dns['server'] !== '' ? e($dns['server']) : t('tools.dns_server_default') ?></strong></span>
    </div>
    <?php foreach ($dns['runs'] as $run): ?>
    <div class="dns-run">
        <h3><?= e($run['type']) ?>
            <?php if ($run['status'] !== null): ?><span class="badge <?= $run['status'] === 'NOERROR' ? 'badge-ok' : 'badge-err' ?>"><?= e($run['status']) ?></span><?php endif; ?>
        </h3>
        <?php if ($run['result']['error']): ?><div class="alert alert-error"><?= e($run['result']['error']) ?></div><?php endif; ?>
        <?php if ($run['answers']): ?>
        <table class="table dns-table">
            <thead><tr><th><?= t('tools.dns_col_name') ?></th><th>TTL</th><th><?= t('tools.dns_col_type') ?></th><th><?= t('tools.dns_col_value') ?></th></tr></thead>
            <tbody>
            <?php foreach ($run['answers'] as $a): ?>
                <tr><td><?= e($a['name']) ?></td><td><?= (int) $a['ttl'] ?></td><td><span class="badge"><?= e($a['type']) ?></span></td><td class="mono"><?= e($a['data']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php elseif (!$run['result']['error']): ?>
            <p class="muted"><?= t('tools.dns_no_records') ?></p>
        <?php endif; ?>
        <details class="tools-raw"><summary><?= t('tools.dns_raw') ?></summary><pre class="tools-output"><?= e($run['result']['output'] !== '' ? $run['result']['output'] : __('tools.no_output')) ?></pre></details>
    </div>
    <?php endforeach; ?>
</section>
<?php endif; ?>
