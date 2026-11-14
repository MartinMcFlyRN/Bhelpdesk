<?php
declare(strict_types=1);

namespace Bhelpdesk;

/**
 * Self-hosted image captcha (PHP GD): no external service, no data leaves the server.
 *
 * - GET /customer/captcha generates a new code, stores its hash in the session
 *   and outputs a PNG.
 * - verify() is single-use: the code is discarded after every attempt.
 */
final class Captcha
{
    private const CHARS = 'ACDEFHJKMNPRTWXY34679'; // no look-alikes: 0/O/Q, 1/I/L, 2/Z, 5/S, 8/B, 6/G, U/V
    private const LENGTH = 5;
    private const TTL = 900;
    private const W = 180;
    private const H = 60;

    private const FONT_CANDIDATES = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/liberation-sans/LiberationSans-Bold.ttf',
    ];

    public static function enabled(): bool
    {
        return (bool) config('captcha.enabled', true);
    }

    public static function output(): never
    {
        $code = '';
        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }
        $_SESSION['_captcha'] = ['h' => hash('sha256', $code), 'exp' => time() + self::TTL];

        if (!function_exists('imagecreatetruecolor')) {
            error_log('Bhelpdesk: PHP GD extension missing, captcha cannot be drawn');
            http_response_code(500);
            exit;
        }

        $img = imagecreatetruecolor(self::W, self::H);
        $bg = imagecolorallocate($img, random_int(235, 250), random_int(235, 250), random_int(235, 250));
        imagefilledrectangle($img, 0, 0, self::W, self::H, $bg);

        // background noise: arcs and dots
        for ($i = 0; $i < 6; $i++) {
            $c = imagecolorallocate($img, random_int(150, 210), random_int(150, 210), random_int(150, 210));
            imagesetthickness($img, random_int(1, 2));
            imagearc($img, random_int(0, self::W), random_int(0, self::H), random_int(60, 220), random_int(30, 100), random_int(0, 360), random_int(0, 360), $c);
        }
        for ($i = 0; $i < 350; $i++) {
            $c = imagecolorallocate($img, random_int(120, 220), random_int(120, 220), random_int(120, 220));
            imagesetpixel($img, random_int(0, self::W - 1), random_int(0, self::H - 1), $c);
        }

        $font = self::font();
        $cell = (self::W - 20) / self::LENGTH;
        for ($i = 0; $i < self::LENGTH; $i++) {
            $color = imagecolorallocate($img, random_int(20, 90), random_int(20, 90), random_int(40, 120));
            $x = (int) (10 + $i * $cell + random_int(0, 6));
            if ($font) {
                imagettftext($img, random_int(24, 30), random_int(-28, 28), $x, random_int(40, 50), $color, $font, $code[$i]);
            } else {
                self::drawBuiltinChar($img, $code[$i], $x, $color);
            }
        }

        // a couple of lines across the text
        for ($i = 0; $i < 2; $i++) {
            $c = imagecolorallocate($img, random_int(60, 140), random_int(60, 140), random_int(60, 140));
            imagesetthickness($img, 2);
            imageline($img, 0, random_int(10, self::H - 10), self::W, random_int(10, self::H - 10), $c);
        }

        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        imagepng($img);
        exit;
    }

    public static function verify(string $input): bool
    {
        $c = $_SESSION['_captcha'] ?? null;
        unset($_SESSION['_captcha']);
        if (!is_array($c) || ($c['exp'] ?? 0) < time()) {
            return false;
        }
        $in = strtoupper((string) preg_replace('/\s+/', '', $input));
        return $in !== '' && hash_equals((string) $c['h'], hash('sha256', $in));
    }

    private static function font(): ?string
    {
        if (!function_exists('imagettftext')) {
            return null;
        }
        $candidates = array_filter([(string) config('captcha.font', '')]);
        array_push($candidates, ...self::FONT_CANDIDATES);
        foreach ($candidates as $f) {
            if (@is_readable($f)) {
                return $f;
            }
        }
        return null;
    }

    /** Fallback without TTF fonts: enlarge and rotate GD's built-in font. */
    private static function drawBuiltinChar(\GdImage $img, string $ch, int $x, int $color): void
    {
        $small = imagecreatetruecolor(10, 16);
        $white = imagecolorallocate($small, 255, 255, 255);
        imagefill($small, 0, 0, $white);
        imagecolortransparent($small, $white);
        $rgb = imagecolorsforindex($img, $color);
        imagestring($small, 5, 1, 0, $ch, imagecolorallocate($small, $rgb['red'], $rgb['green'], $rgb['blue']));
        $rot = imagerotate($small, random_int(-25, 25), $white);
        imagecolortransparent($rot, $white);
        imagecopyresized($img, $rot, $x, random_int(6, 14), 0, 0, 28, 40, imagesx($rot), imagesy($rot));
    }
}
