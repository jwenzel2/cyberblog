<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        extract($data, EXTR_SKIP);
        $templateFile = app_path('views/' . $template . '.php');
        $layoutFile = app_path('views/' . $layout . '.php');
        require $layoutFile;
    }
}
