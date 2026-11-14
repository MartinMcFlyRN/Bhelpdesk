<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Images inserted with the editor (signatures, replies, canned responses).
 *
 * They must be reachable by customers and, later, by email clients, so they are
 * served publicly from /customer/media/<32 hex>.<ext>: the random name is the
 * only "key". Files live in storage/media (outside public/), are checked with
 * getimagesize() and served with nosniff + a sandbox CSP.
 */
final class Media
{
    public const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    public const MAX_BYTES = 5 * 1048576;

    public static function dir(): string
    {
        return rtrim((string) (config('storage_path') ?: BHD_ROOT . '/storage'), '/') . '/media';
    }

    /**
     * Store an uploaded image.
     * @return array{url: ?string, error: ?string}
     */
    public static function upload(?array $file, int $operatorId): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return ['url' => null, 'error' => __('media.upload_failed')];
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            return ['url' => null, 'error' => __('media.too_big', ['size' => self::MAX_BYTES / 1048576])];
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $info = @getimagesize((string) $file['tmp_name']);
        if (!isset(self::TYPES[$mime]) || !is_array($info) || ($info['mime'] ?? '') !== $mime) {
            return ['url' => null, 'error' => __('media.only_images')];
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        $dir = self::dir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return ['url' => null, 'error' => __('media.upload_failed')];
        }
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['url' => null, 'error' => __('media.upload_failed')];
        }
        @chmod($dir . '/' . $name, 0640);
        Db::insert(
            'INSERT INTO media (file_name, mime, size, width, height, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)',
            [$name, $mime, (int) $file['size'], (int) $info[0], (int) $info[1], $operatorId]
        );
        return ['url' => '/customer/media/' . $name, 'error' => null];
    }

    public static function send(string $name): never
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp)$/', $name) || !is_file(self::dir() . '/' . $name)) {
            http_response_code(404);
            exit;
        }
        $mime = (string) array_search(pathinfo($name, PATHINFO_EXTENSION), self::TYPES, true);
        $path = self::dir() . '/' . $name;
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=2592000, immutable');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header_remove('Pragma');
        header_remove('Expires');
        header_remove('Set-Cookie');
        readfile($path);
        exit;
    }
}
