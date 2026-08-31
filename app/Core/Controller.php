<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller. Provides response helpers to all controllers.
 * Business logic belongs in Services (from Phase 2+), not here.
 */
abstract class Controller
{
    public function __construct(protected Container $container)
    {
    }

    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        /** @var View $view */
        $view = $this->container->get(View::class);
        return Response::html($view->render($template, $data), $status);
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $to, int $status = 302): Response
    {
        return Response::redirect($to, $status);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        /** @var Config $config */
        $config = $this->container->get(Config::class);
        return $config->get($key, $default);
    }
}
