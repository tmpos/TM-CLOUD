<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        $views = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views';
        $templateFile = $views . DIRECTORY_SEPARATOR . $template . '.php';
        if (!is_file($templateFile)) {
            throw new \RuntimeException("View not found: $template");
        }
        extract($data, EXTR_SKIP);
        $content = static function () use ($templateFile, $data): string {
            extract($data, EXTR_SKIP);
            ob_start();
            require $templateFile;
            return (string) ob_get_clean();
        };
        $content = $content();
        require $views . DIRECTORY_SEPARATOR . 'layout.php';
    }
}
