<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Native-PHP template renderer (no compile step — fastest on shared hosting).
 *
 * Templates live in app/Views. A template may declare a layout via $this->layout()
 * and push content into named sections. All output is escaped in the template via
 * the e() helper; the renderer never echoes raw user data itself.
 */
final class View
{
    private ?string $layout = null;

    /** @var array<string,string> captured sections */
    private array $sections = [];

    /** @var array<int,string> stack of section names currently being captured */
    private array $sectionStack = [];

    public function __construct(private readonly string $viewPath)
    {
    }

    /**
     * Render a template with data and return the HTML string.
     *
     * @param array<string,mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        // Reset per-render state so a shared View instance never bleeds sections
        // or a layout from a previous render into this one.
        $this->layout = null;
        $this->sections = [];
        $this->sectionStack = [];

        $content = $this->renderTemplate($template, $data);

        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            // A template may either echo its body directly (captured here as
            // $content) or wrap it in start('content')/stop(). If it defined the
            // section explicitly, that wins — don't clobber it.
            if (!isset($this->sections['content'])) {
                $this->sections['content'] = $content;
            }
            return $this->renderTemplate($layout, $data);
        }

        return $content;
    }

    private function renderTemplate(string $template, array $data): string
    {
        $file = $this->resolve($template);

        if (!is_file($file)) {
            throw new RuntimeException("View '{$template}' not found at {$file}.");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;
        return (string) ob_get_clean();
    }

    private function resolve(string $template): string
    {
        $template = str_replace(['..', '\\'], ['', '/'], $template);
        $template = trim($template, '/');
        return $this->viewPath . DIRECTORY_SEPARATOR . str_replace('.', DIRECTORY_SEPARATOR, $template) . '.php';
    }

    // --- Template-facing API (call as $this->... inside templates) -----------

    public function layout(string $name): void
    {
        $this->layout = $name;
    }

    public function start(string $section): void
    {
        $this->sectionStack[] = $section;
        ob_start();
    }

    public function stop(): void
    {
        $section = array_pop($this->sectionStack);
        if ($section !== null) {
            $this->sections[$section] = (string) ob_get_clean();
        }
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }
}
