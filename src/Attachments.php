<?php
declare(strict_types=1);

namespace Bhelpdesk;

use RuntimeException;

/**
 * Ticket attachments.
 *
 * Two profiles:
 *  - 'customer': images (JPG, PNG, GIF, WEBP) and PDF;
 *  - 'operator': also Word, Excel, ZIP, TAR, GZ/TGZ, BZ2.
 * For every file the extension must be allowed, the type detected from the
 * content (finfo) must be one expected for that extension, and the file must
 * start with the right signature (magic bytes / getimagesize).
 * Files are stored outside public/ with a random name and no extension,
 * and are only served through PHP after a permission check.
 */
final class Attachments
{
    /**
     * extension => [MIME types finfo may report, MIME type stored/served, content check]
     */
    private const EXT = [
        'jpg'  => [['image/jpeg'], 'image/jpeg', 'image'],
        'jpeg' => [['image/jpeg'], 'image/jpeg', 'image'],
        'png'  => [['image/png'], 'image/png', 'image'],
        'gif'  => [['image/gif'], 'image/gif', 'image'],
        'webp' => [['image/webp'], 'image/webp', 'image'],
        'pdf'  => [['application/pdf'], 'application/pdf', 'pdf'],
        'doc'  => [['application/msword', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office', 'application/octet-stream'], 'application/msword', 'ole'],
        'xls'  => [['application/vnd.ms-excel', 'application/CDFV2', 'application/x-ole-storage', 'application/vnd.ms-office', 'application/octet-stream'], 'application/vnd.ms-excel', 'ole'],
        'docx' => [['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'], 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'zip'],
        'xlsx' => [['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'], 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'zip'],
        'zip'  => [['application/zip', 'application/x-zip-compressed'], 'application/zip', 'zip'],
        'tar'  => [['application/x-tar'], 'application/x-tar', 'tar'],
        'gz'   => [['application/gzip', 'application/x-gzip'], 'application/gzip', 'gzip'],
        'tgz'  => [['application/gzip', 'application/x-gzip'], 'application/gzip', 'gzip'],
        'bz2'  => [['application/x-bzip2'], 'application/x-bzip2', 'bzip2'],
        'tbz2' => [['application/x-bzip2'], 'application/x-bzip2', 'bzip2'],
    ];

    private const PROFILES = [
        'customer' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'],
        'operator' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'tar', 'gz', 'tgz', 'bz2', 'tbz2'],
    ];

    /** MIME types that may be served (used by send()) */
    public const TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf',
        'application/msword', 'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip', 'application/x-tar', 'application/gzip', 'application/x-bzip2',
    ];

    public static function enabled(): bool
    {
        return (bool) config('attachments.enabled', true);
    }

    private static function key(string $profile): string
    {
        return $profile === 'operator' ? 'attachments.op_' : 'attachments.';
    }

    public static function maxFiles(string $profile = 'customer'): int
    {
        return max(1, (int) config(self::key($profile) . 'max_files', $profile === 'operator' ? 10 : 5));
    }

    public static function maxSizeMb(string $profile = 'customer'): int
    {
        return max(1, (int) config(self::key($profile) . 'max_size_mb', $profile === 'operator' ? 20 : 5));
    }

    public static function maxTotalMb(string $profile = 'customer'): int
    {
        return max(1, (int) config(self::key($profile) . 'max_total_mb', $profile === 'operator' ? 50 : 20));
    }

    /** Value for the <input accept="..."> attribute */
    public static function acceptAttr(string $profile = 'customer'): string
    {
        $out = [];
        foreach (self::PROFILES[$profile] as $ext) {
            $out[] = '.' . $ext;
        }
        return implode(',', $out);
    }

    /** Human list of the allowed extensions, e.g. "JPG, PNG, PDF, DOCX" */
    public static function extList(string $profile = 'customer'): string
    {
        $list = array_diff(self::PROFILES[$profile], ['jpeg', 'tgz', 'tbz2']);
        return strtoupper(implode(', ', $list));
    }

    public static function storageDir(): string
    {
        return rtrim((string) (config('storage_path') ?: BHD_ROOT . '/storage'), '/') . '/attachments';
    }

    /**
     * Validate an uploaded field (e.g. $_FILES['attachments']).
     * @return array{files: list<array{tmp: string, name: string, size: int, mime: string}>, errors: list<string>}
     */
    public static function fromUpload(?array $field, string $profile = 'customer'): array
    {
        $files = [];
        $errors = [];
        if (!$field || !isset($field['name'])) {
            return ['files' => [], 'errors' => []];
        }

        // normalise single / multiple upload structure
        $names = (array) $field['name'];
        $count = 0;
        $total = 0;
        $maxMb = self::maxSizeMb($profile);
        $maxBytes = $maxMb * 1048576;
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $typeError = $profile === 'operator' ? 'val.file_type_op' : 'val.file_type';

        foreach (array_keys($names) as $i) {
            $err  = (int) ((array) $field['error'])[$i];
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $name = self::cleanName((string) $names[$i]);
            $tmp  = (string) ((array) $field['tmp_name'])[$i];
            $size = (int) ((array) $field['size'])[$i];
            $count++;

            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE || $size > $maxBytes) {
                $errors[] = __('val.file_too_big', ['name' => $name, 'size' => $maxMb]);
                continue;
            }
            if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                $errors[] = __('val.upload_failed', ['name' => $name]);
                continue;
            }

            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $rule = in_array($ext, self::PROFILES[$profile], true) ? self::EXT[$ext] : null;
            $detected = (string) $finfo->file($tmp);
            if (!$rule || !in_array($detected, $rule[0], true) || !self::contentOk($tmp, $rule[2], $rule[1])) {
                $errors[] = __($typeError, ['name' => $name, 'types' => self::extList($profile)]);
                continue;
            }

            $total += $size;
            $files[] = ['tmp' => $tmp, 'name' => $name, 'size' => $size, 'mime' => $rule[1]];
        }

        if ($count > self::maxFiles($profile)) {
            array_unshift($errors, __('val.too_many_files', ['max' => self::maxFiles($profile)]));
        }
        if ($total > self::maxTotalMb($profile) * 1048576) {
            $errors[] = __('val.total_too_big', ['size' => self::maxTotalMb($profile)]);
        }
        return ['files' => $errors ? [] : $files, 'errors' => $errors];
    }

    /**
     * Validate files taken from an email: same checks as uploads (extension,
     * detected type, signature, size). Accepted files are written to a temporary
     * file in storage and returned with 'local' => true for store().
     * @param list<array{name: string, data: string}> $items
     * @return array{files: list<array>, rejected: list<string>} rejected = "name (reason)"
     */
    public static function fromData(array $items, string $profile = 'operator'): array
    {
        $files = [];
        $rejected = [];
        $maxBytes = self::maxSizeMb($profile) * 1048576;
        $maxTotal = self::maxTotalMb($profile) * 1048576;
        $total = 0;
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $tmpDir = self::storageDir() . '/tmp';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0750, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Cannot create ' . $tmpDir);
        }
        foreach ($items as $it) {
            $name = self::cleanName((string) $it['name']);
            $size = strlen((string) $it['data']);
            if (count($files) >= self::maxFiles($profile)) {
                $rejected[] = $name . ' (' . __('mailin.rej_count', ['max' => self::maxFiles($profile)]) . ')';
                continue;
            }
            if ($size === 0 || $size > $maxBytes || $total + $size > $maxTotal) {
                $rejected[] = $name . ' (' . Attachments::humanSize($size) . ')';
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $rule = in_array($ext, self::PROFILES[$profile], true) ? self::EXT[$ext] : null;
            $tmp = $tmpDir . '/' . bin2hex(random_bytes(12));
            file_put_contents($tmp, (string) $it['data']);
            $detected = (string) $finfo->file($tmp);
            if (!$rule || !in_array($detected, $rule[0], true) || !self::contentOk($tmp, $rule[2], $rule[1])) {
                @unlink($tmp);
                $rejected[] = $name . ' (' . __('mailin.rej_type') . ')';
                continue;
            }
            $total += $size;
            $files[] = ['tmp' => $tmp, 'name' => $name, 'size' => $size, 'mime' => $rule[1], 'local' => true];
        }
        return ['files' => $files, 'rejected' => $rejected];
    }

    /** Remove temporary files left by fromData() when they are not stored */
    public static function discard(array $files): void
    {
        foreach ($files as $f) {
            if (!empty($f['local']) && is_file($f['tmp'])) {
                @unlink($f['tmp']);
            }
        }
    }

    /** Absolute path of a stored attachment */
    public static function path(array $a): string
    {
        return self::storageDir() . '/' . $a['stored_name'];
    }

    /** File signature check for each kind of file */
    private static function contentOk(string $path, string $kind, string $mime): bool
    {
        if ($kind === 'image') {
            $info = @getimagesize($path);
            return is_array($info) && $info[0] > 0 && $info[1] > 0 && ($info['mime'] ?? '') === $mime;
        }
        $fh = fopen($path, 'rb');
        if (!$fh) {
            return false;
        }
        $head = (string) fread($fh, 512);
        fclose($fh);
        return match ($kind) {
            'pdf'   => str_starts_with($head, '%PDF-'),
            'ole'   => str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"),
            'zip'   => str_starts_with($head, "PK\x03\x04") || str_starts_with($head, "PK\x05\x06"),
            'tar'   => substr($head, 257, 5) === 'ustar',
            'gzip'  => str_starts_with($head, "\x1F\x8B"),
            'bzip2' => str_starts_with($head, 'BZh'),
            default => false,
        };
    }

    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"<>:|?*]/u', '', $name);
        $name = trim($name, " .");
        if (mb_strlen($name) > 150) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 140) . ($ext !== '' ? '.' . $ext : '');
        }
        return $name !== '' ? $name : 'file';
    }

    /**
     * Move validated files into storage and record them.
     * Must be called inside the ticket transaction; on failure the files
     * already moved are deleted and the exception is rethrown.
     */
    public static function store(int $ticketId, int $messageId, array $files): void
    {
        $moved = [];
        try {
            foreach ($files as $f) {
                $rel = date('Y/m') . '/' . bin2hex(random_bytes(16));
                $abs = self::storageDir() . '/' . $rel;
                $dir = dirname($abs);
                if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
                    throw new RuntimeException('Cannot create attachment directory ' . $dir);
                }
                if (!(!empty($f['local']) ? rename($f['tmp'], $abs) : move_uploaded_file($f['tmp'], $abs))) {
                    throw new RuntimeException('Cannot store uploaded file in ' . $dir);
                }
                $moved[] = $abs;
                @chmod($abs, 0640);
                Db::insert(
                    'INSERT INTO attachments (ticket_id, message_id, original_name, stored_name, mime, size, sha256) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$ticketId, $messageId, $f['name'], $rel, $f['mime'], $f['size'], hash_file('sha256', $abs)]
                );
            }
        } catch (\Throwable $ex) {
            foreach ($moved as $p) {
                @unlink($p);
            }
            throw $ex;
        }
    }

    /** @return array<int, list<array>> message_id => attachments */
    public static function forMessages(array $messageIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $messageIds)));
        if (!$ids) {
            return [];
        }
        $rows = Db::all(
            'SELECT id, message_id, original_name, mime, size FROM attachments WHERE message_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id',
            $ids
        );
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['message_id']][] = $r;
        }
        return $out;
    }

    public static function find(int $id): ?array
    {
        return Db::one(
            'SELECT a.*, m.is_internal FROM attachments a JOIN ticket_messages m ON m.id = a.message_id WHERE a.id = ?',
            [$id]
        );
    }

    /** Stream a file to the browser. Images inline, PDFs as download. */
    public static function send(array $a, bool $forceDownload = false): never
    {
        $path = self::storageDir() . '/' . $a['stored_name'];
        if (!in_array($a['mime'], self::TYPES, true) || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        $inline = !$forceDownload && str_starts_with($a['mime'], 'image/');
        $name = (string) $a['original_name'];
        $ascii = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $name);

        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: ' . $a['mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
        header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600');
        header_remove('Pragma');
        header_remove('Expires');
        readfile($path);
        exit;
    }

    public static function humanSize(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
