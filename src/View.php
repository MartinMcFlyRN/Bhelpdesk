<?php
declare(strict_types=1);

namespace Bhelpdesk;

final class View
{
    /**
     * Render templates/{area}/{template}.php inside templates/{area}/layout.php
     * (shared templates such as error.php live in templates/).
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

    private static function capture(string $__template, array $__vars): string
    {
        // unusual local names so template variables (e.g. $file) never collide
        extract($__vars, EXTR_SKIP);
        unset($__vars);
        ob_start();
        require $__template;
        return (string) ob_get_clean();
    }
}
