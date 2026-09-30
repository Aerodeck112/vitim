<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add(['POST'], $pattern, $handler);
    }

    public function any(string $pattern, callable|array $handler): void
    {
        $this->add(['GET', 'HEAD', 'POST'], $pattern, $handler);
    }

    private function add(array $methods, string $pattern, callable|array $handler): void
    {
        $regex = preg_replace_callback('#\{(\w+)(?::([^}]+))?\}#', function ($m) {
            $re = $m[2] ?? '[^/]+';
            return '(?P<' . $m[1] . '>' . $re . ')';
        }, $pattern);
        $this->routes[] = [$methods, '#^' . $regex . '$#u', $handler];
    }

    /** @return array{0: callable|array, 1: array}|null */
    public function match(string $method, string $path): ?array
    {
        foreach ($this->routes as [$methods, $regex, $handler]) {
            if (!in_array($method, $methods, true)) {
                continue;
            }
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$handler, $params];
            }
        }
        return null;
    }

    public static function call(callable|array $handler, array $params): mixed
    {
        if (is_array($handler) && is_string($handler[0])) {
            $obj = new $handler[0]();
            return $obj->{$handler[1]}(...array_values($params));
        }
        return $handler(...array_values($params));
    }
}
