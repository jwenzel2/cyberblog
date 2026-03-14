<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->map('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->map('POST', $pattern, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $param) {
                $params[] = $matches[$param] ?? null;
            }

            if (is_array($route['handler'])) {
                [$class, $methodName] = $route['handler'];
                $controller = new $class();
                $controller->{$methodName}(...$params);
                return;
            }

            $route['handler'](...$params);
            return;
        }

        Response::abort(404, 'Not found');
    }

    private function map(string $method, string $pattern, callable|array $handler): void
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $pattern, $matches);
        $params = $matches[1];
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $this->routes[$method][] = [
            'regex' => '#^' . $regex . '$#',
            'params' => $params,
            'handler' => $handler,
        ];
    }
}
