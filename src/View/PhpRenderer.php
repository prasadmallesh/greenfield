<?php

declare(strict_types=1);

namespace App\View;

final class PhpRenderer
{
    public function __construct(private string $templatesDir)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $templateRelativePath, array $data = []): string
    {
        $path = $this->templatesDir . DIRECTORY_SEPARATOR . $templateRelativePath . '.php';
        if (!is_readable($path)) {
            throw new \RuntimeException('Template not found: ' . $path);
        }
        extract($data, \EXTR_SKIP);
        ob_start();
        include $path;

        return (string) ob_get_clean();
    }
}
