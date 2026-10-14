<?php
declare(strict_types=1);

namespace App\Routing;

final class Router
{
    /**
     * @var list<array{method: string, path: string, handler: callable}>
     */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function dispatch(): void
    {
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (!is_string($requestPath)) {
            http_response_code(400);
            exit('Permintaan tidak valid.');
        }

        $basePath = APP_BASE_URL;
        if ($basePath !== '' && ($requestPath === $basePath || str_starts_with($requestPath, $basePath . '/'))) {
            $requestPath = substr($requestPath, strlen($basePath));
        }
        $requestPath = '/' . trim($requestPath, '/');
        if ($requestPath === '/') {
            $requestPath = '/';
        }
        $_SERVER['APP_ROUTE_PATH'] = $requestPath;

        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $parameters = $this->match($route['path'], $requestPath);
            if ($parameters === null) {
                continue;
            }

            $methodMatches = $route['method'] === $requestMethod
                || ($requestMethod === 'HEAD' && $route['method'] === 'GET');
            if (!$methodMatches) {
                $allowedMethods[] = $route['method'];
                if ($route['method'] === 'GET') {
                    $allowedMethods[] = 'HEAD';
                }
                continue;
            }

            $_GET = array_merge($_GET, $parameters);
            ($route['handler'])();
            return;
        }

        if ($allowedMethods !== []) {
            $allowedMethods = array_values(array_unique($allowedMethods));
            http_response_code(405);
            header('Allow: ' . implode(', ', $allowedMethods));
            exit('Metode permintaan tidak diizinkan.');
        }

        app_render_error_page(404);
        exit;
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $routePath, string $requestPath): ?array
    {
        $routeSegments = $routePath === '/' ? [] : explode('/', trim($routePath, '/'));
        $requestSegments = $requestPath === '/' ? [] : explode('/', trim($requestPath, '/'));
        if (count($routeSegments) !== count($requestSegments)) {
            return null;
        }

        $parameters = [];
        foreach ($routeSegments as $index => $segment) {
            if (preg_match('/^\{([a-zA-Z][a-zA-Z0-9_]*)\}$/', $segment, $parameterMatch) === 1) {
                $parameters[$parameterMatch[1]] = rawurldecode($requestSegments[$index]);
                continue;
            }

            if ($segment !== $requestSegments[$index]) {
                return null;
            }
        }

        return $parameters;
    }
}
