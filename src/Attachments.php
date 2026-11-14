<?php
declare(strict_types=1);

namespace Bhelpdesk;

use RuntimeException;

/**
 * Ticket attachments: images (JPG, PNG, GIF, WEBP) and PDF only.
 *
 * - Type is detected from the file content (finfo), not from the browser
 *   or the extension; the extension must also match the detected type.
 * - Files are stored outside public/ with a random name and no extension,
 *   and are only served through PHP after a permission check.
 */
final class Attachments
{
    /** detected MIME type => allowed extensions */
    public const TYPES = [
        'image/jpeg'      => ['jpg', 'jpeg'],
        'image/png'       => ['png'],
        'image/gif'       => ['gif'],
        'image/webp'      => ['webp'],
        'application/pdf' => ['pdf'],
    ];

    public static function enabled(): bool
    {
        return (bool) config('attachments.enabled', true);
    }

    public static function maxFiles(): int
    {
        return max(1, (int) config('attachments.max_files', 5));
    }

    public static function maxSizeMb(): int
    {
        return max(1, (int) config('attachments.max_size_mb', 5));
    }

    public static function maxTotalMb(): int
    {
        return max(1, (int) config('attachments.max_total_mb', 20));
    }

    /** Value for the <input accept="..."> attribute */
    public static function acceptAttr(): string
    {
        $exts = [];
        foreach (self::TYPES as $list) {
            foreach ($list as $e) {
                $exts[] = '.' . $e;
            }
        }
        return implode(',', array_merge(array_keys(self::TYPES), $exts));
    }

    public static function storageDir(): string
    {
        return rtrim((string) (config('storage_path') ?: BHD_ROOT . '/storage'), '/') . '/attachments';
    }

    /**
     * Validate an uploaded field (e.g. $_FILES['attachments']).
     * @return array{files: list<array{tmp: string, name: string, size: int, mime: string}>, errors: list<string>}
     */
    public static function fromUpload(?array $field): array
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
        $maxBytes = self::maxSizeMb() * 1048576;
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

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
                $errors[] = __('val.file_too_big', ['name' => $name, 'size' => self::maxSizeMb()]);
                continue;
            }
            if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
                $errors[] = __('val.upload_failed', ['name' => $name]);
                continue;
            }

            $mime = (string) $finfo->file($tmp);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!isset(self::TYPES[$mime]) || !in_array($ext, self::TYPES[$mime], true) || !self::contentOk($tmp, $mime)) {
                $errors[] = __('val.file_type', ['name' => $name]);
                continue;
            }

            $total += $size;
            $files[] = ['tmp' => $tmp, 'name' => $name, 'size' => $size, 'mime' => $mime];
        }

        if ($count > self::maxFiles()) {
            array_unshift($errors, __('val.too_many_files', ['max' => self::maxFiles()]));
        }
        if ($total > self::maxTotalMb() * 1048576) {
            $errors[] = __('val.total_too_big', ['size' => self::maxTotalMb()]);
        }
        return ['files' => $errors ? [] : $files, 'errors' => $errors];
    }

    /** Extra content checks beyond the MIME sniffing */
    private static function contentOk(string $path, string $mime): bool
    {
        if ($mime === 'application/pdf') {
            $fh = fopen($path, 'rb');
            $head = $fh ? (string) fread($fh, 5) : '';
            if ($fh) {
                fclose($fh);
            }
            return $head === '%PDF-';
        }
        $info = @getimagesize($path);
        return is_array($info) && $info[0] > 0 && $info[1] > 0 && ($info['mime'] ?? '') === $mime;
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
                if (!move_uploaded_file($f['tmp'], $abs)) {
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
        if (!isset(self::TYPES[$a['mime']]) || !is_file($path)) {
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
