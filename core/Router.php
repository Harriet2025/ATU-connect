<?php
namespace Core;

// =========================================================
// ROUTER — maps method+path to [ControllerClass, method], supports {param}
// =========================================================
class Router
{
    private array $routes = [];

    public function add(string $method, string $path, string $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $this->toRegex($path),
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function get(string $path, string $handler, array $middleware = []): void { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, string $handler, array $middleware = []): void { $this->add('POST', $path, $handler, $middleware); }
    public function put(string $path, string $handler, array $middleware = []): void { $this->add('PUT', $path, $handler, $middleware); }
    public function delete(string $path, string $handler, array $middleware = []): void { $this->add('DELETE', $path, $handler, $middleware); }

    private function toRegex(string $path): string
    {
        $pattern = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): void
    {
        $uri = rtrim($request->uri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            if (preg_match($route['pattern'], $uri, $matches)) {
                array_shift($matches);

                foreach ($route['middleware'] as $middlewareClass) {
                    $instance = new $middlewareClass();
                    if ($instance->handle() === false) {
                        return;
                    }
                }

                [$controllerClass, $method] = explode('@', $route['handler']);
                $controller = new $controllerClass();
                call_user_func_array([$controller, $method], array_merge([$request], $matches));
                return;
            }
        }

        Response::error('Route not found.', HTTP_NOT_FOUND);
    }
}
