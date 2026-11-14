<?php
declare(strict_types=1);

namespace Bhelpdesk;

final class View
{
    /**
     * Renderizza templates/{area}/{template}.php dentro templates/{area}/layout.php
     * (error.php è condiviso in templates/).
     */
    public static function render(string $area, string $template, array $vars = []): void
    {
        $file = BHD_ROOT . "/templates/$area/$template.php";
        if (!is_file($file)) {
            $file = BHD_ROOT . "/templates/$template.php";
        }
        $content = self::capture($file, $vars);
        echo self::capture(BHD_ROOT . "/templates/$area/layout.php", $vars + ['content' => $content]);
    }

    private static function capture(string $file, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
