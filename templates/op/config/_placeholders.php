<details class="placeholders">
    <summary><?= t('canned.placeholders') ?></summary>
    <p class="muted hint"><?= t('canned.placeholders_hint') ?></p>
    <ul>
        <?php foreach (\Bhelpdesk\Canned::PLACEHOLDERS as $ph): ?>
            <li><code><?= e($ph) ?></code> <span class="muted"><?= t('ph.' . trim($ph, '{}')) ?></span></li>
        <?php endforeach; ?>
    </ul>
</details>
