<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Runtime settings editable from /op/config/general.
 *
 * Values are stored in the `settings` table and override config/config.php.
 * Only the keys listed in FIELDS can be changed from the web; everything
 * security-sensitive (database, allowed IPs, proxies, cookies, storage path)
 * stays in config.php.
 */
final class Settings
{
    /**
     * key => definition. Sections group the fields on the page.
     * Types: string, email, url, timezone, language, languages, bool, int, pair (max attempts + window seconds)
     */
    public const FIELDS = [
        'app_name'                 => ['section' => 'general',     'type' => 'string', 'max' => 100],
        'base_url'                 => ['section' => 'general',     'type' => 'url'],
        'timezone'                 => ['section' => 'general',     'type' => 'timezone'],
        'mail.from'                => ['section' => 'mail',        'type' => 'email'],
        'mail.from_name'           => ['section' => 'mail',        'type' => 'string', 'max' => 100],
        'default_language'         => ['section' => 'languages',   'type' => 'language'],
        'enabled_languages'        => ['section' => 'languages',   'type' => 'languages'],
        'allow_reopen'             => ['section' => 'tickets',     'type' => 'bool'],
        'hide_operator_name'       => ['section' => 'tickets',     'type' => 'bool'],
        'customer_link_days'       => ['section' => 'tickets',     'type' => 'int', 'min' => 1, 'max' => 365],
        'captcha.enabled'          => ['section' => 'tickets',     'type' => 'bool'],
        'attachments.enabled'      => ['section' => 'attachments', 'type' => 'bool'],
        'attachments.max_files'    => ['section' => 'attachments', 'type' => 'int', 'min' => 1, 'max' => 20],
        'attachments.max_size_mb'  => ['section' => 'attachments', 'type' => 'int', 'min' => 1, 'max' => 100],
        'attachments.max_total_mb' => ['section' => 'attachments', 'type' => 'int', 'min' => 1, 'max' => 500],
        'op_session_idle'          => ['section' => 'security',    'type' => 'int', 'min' => 300, 'max' => 86400],
        'throttle.op_login_fail'   => ['section' => 'security',    'type' => 'pair'],
        'throttle.ticket_create'   => ['section' => 'security',    'type' => 'pair'],
        'throttle.customer_login_fail' => ['section' => 'security', 'type' => 'pair'],
        'throttle.password_mail'   => ['section' => 'security',    'type' => 'pair'],
    ];

    /** Fallback values when a key is missing from both DB and config.php */
    public const DEFAULTS = [
        'app_name'                 => 'Bhelpdesk',
        'base_url'                 => '',
        'timezone'                 => 'Europe/Rome',
        'mail.from'                => 'helpdesk@localhost',
        'mail.from_name'           => 'Helpdesk',
        'default_language'         => 'en',
        'enabled_languages'        => [],
        'allow_reopen'             => true,
        'hide_operator_name'       => false,
        'customer_link_days'       => 30,
        'captcha.enabled'          => true,
        'attachments.enabled'      => true,
        'attachments.max_files'    => 5,
        'attachments.max_size_mb'  => 5,
        'attachments.max_total_mb' => 20,
        'op_session_idle'          => 1800,
        'throttle.op_login_fail'   => [5, 900],
        'throttle.ticket_create'   => [5, 3600],
        'throttle.customer_login_fail' => [10, 900],
        'throttle.password_mail'   => [5, 3600],
    ];

    /**
     * Merge DB values into the loaded configuration. Called once from bootstrap.
     * If the table does not exist yet (migration not applied) or the DB is down,
     * config.php values are used unchanged.
     */
    public static function apply(): void
    {
        try {
            $rows = Db::all('SELECT name, value FROM settings');
        } catch (\Throwable) {
            return;
        }
        foreach ($rows as $r) {
            if (!isset(self::FIELDS[$r['name']])) {
                continue;
            }
            $v = json_decode((string) $r['value'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                self::setConfig($r['name'], $v);
            }
        }
    }

    private static function setConfig(string $key, mixed $value): void
    {
        $ref = &$GLOBALS['bhd_config'];
        foreach (explode('.', $key) as $part) {
            if (!isset($ref[$part]) || !is_array($ref[$part])) {
                $ref[$part] = $ref[$part] ?? [];
            }
            $ref = &$ref[$part];
        }
        $ref = $value;
    }

    /** Current effective value (DB > config.php > default) */
    public static function current(string $key): mixed
    {
        return config($key, self::DEFAULTS[$key] ?? null);
    }

    /**
     * Validate the submitted form (array keyed like FIELDS).
     * @return array{values: array<string, mixed>, errors: list<string>}
     */
    public static function validate(array $in): array
    {
        $values = [];
        $errors = [];
        $langs = array_keys(Lang::available(true));

        foreach (self::FIELDS as $key => $def) {
            $label = __('set.' . $key);
            $raw = $in[$key] ?? null;
            switch ($def['type']) {
                case 'string':
                    $v = trim((string) $raw);
                    if ($v === '' || mb_strlen($v) > ($def['max'] ?? 255)) {
                        $errors[] = __('val.set_required', ['field' => $label]);
                    }
                    break;
                case 'email':
                    $v = mb_strtolower(trim((string) $raw));
                    if (!filter_var($v, FILTER_VALIDATE_EMAIL) || strlen($v) > 190) {
                        $errors[] = __('val.set_email', ['field' => $label]);
                    }
                    break;
                case 'url':
                    $v = rtrim(trim((string) $raw), '/');
                    if (!preg_match('#^https?://[^\s/$.?\#].[^\s]*$#i', $v) || !filter_var($v, FILTER_VALIDATE_URL)) {
                        $errors[] = __('val.set_url');
                    }
                    break;
                case 'timezone':
                    $v = (string) $raw;
                    if (!in_array($v, \DateTimeZone::listIdentifiers(), true)) {
                        $errors[] = __('val.set_timezone');
                    }
                    break;
                case 'language':
                    $v = (string) $raw;
                    if (!in_array($v, $langs, true)) {
                        $errors[] = __('val.set_language');
                    }
                    break;
                case 'languages':
                    // English is always on. [] = every language file, ['en'] = English only
                    $others = array_values(array_diff(array_intersect($langs, array_map('strval', (array) $raw)), [Lang::BASE]));
                    $all = array_values(array_diff($langs, [Lang::BASE]));
                    $v = !$others ? [Lang::BASE] : (count($others) === count($all) ? [] : $others);
                    break;
                case 'bool':
                    $v = !empty($raw);
                    break;
                case 'int':
                    $v = filter_var($raw, FILTER_VALIDATE_INT);
                    if ($v === false || $v < $def['min'] || $v > $def['max']) {
                        $errors[] = __('val.set_int', ['field' => $label, 'min' => $def['min'], 'max' => $def['max']]);
                        $v = self::current($key);
                    }
                    break;
                case 'pair':
                    $max = filter_var(((array) $raw)[0] ?? null, FILTER_VALIDATE_INT);
                    $win = filter_var(((array) $raw)[1] ?? null, FILTER_VALIDATE_INT);
                    if ($max === false || $max < 1 || $max > 1000 || $win === false || $win < 60 || $win > 86400) {
                        $errors[] = __('val.set_pair', ['field' => $label]);
                    }
                    $v = [(int) $max, (int) $win];
                    break;
                default:
                    continue 2;
            }
            $values[$key] = $v;
        }

        if (isset($values['default_language'], $values['enabled_languages'])
            && $values['enabled_languages']
            && $values['default_language'] !== Lang::BASE
            && !in_array($values['default_language'], $values['enabled_languages'], true)) {
            $errors[] = __('val.set_default_not_enabled');
        }
        if (($values['attachments.max_total_mb'] ?? 0) < ($values['attachments.max_size_mb'] ?? 0)) {
            $errors[] = __('val.set_total_lt_file');
        }
        return ['values' => $values, 'errors' => $errors];
    }

    public static function save(array $values, int $operatorId): void
    {
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($values as $key => $v) {
                if (!isset(self::FIELDS[$key])) {
                    continue;
                }
                Db::exec(
                    'INSERT INTO settings (name, value, updated_by) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by), updated_at = NOW()',
                    [$key, json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $operatorId]
                );
            }
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    /** PHP upload limits of this server, in bytes */
    public static function phpLimits(): array
    {
        $toBytes = static function (string $v): int {
            $v = trim($v);
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) {
                'g' => $n * 1073741824,
                'm' => $n * 1048576,
                'k' => $n * 1024,
                default => $n,
            };
        };
        return [
            'upload' => $toBytes((string) ini_get('upload_max_filesize')),
            'post'   => $toBytes((string) ini_get('post_max_size')),
            'files'  => (int) ini_get('max_file_uploads'),
            'upload_raw' => (string) ini_get('upload_max_filesize'),
            'post_raw'   => (string) ini_get('post_max_size'),
        ];
    }

    /** True when the attachment limits are higher than what PHP accepts */
    public static function attachmentLimitsExceedPhp(): bool
    {
        $php = self::phpLimits();
        return (int) self::current('attachments.max_size_mb') * 1048576 > $php['upload']
            || (int) self::current('attachments.max_total_mb') * 1048576 >= $php['post']
            || (int) self::current('attachments.max_files') > $php['files'];
    }
}
