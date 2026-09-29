<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Șabloane PHP simple, cu layout și secțiuni.
 */
final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = self::partial($view, $data);
        if ($layout) {
            return self::partial($layout, $data + ['content' => $content]);
        }
        return $content;
    }

    public static function partial(string $view, array $data = []): string
    {
        $file = APP_PATH . '/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View lipsă: $view");
        }
        extract(self::$shared + $data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }
}
