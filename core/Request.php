<?php
namespace Core;

// =========================================================
// REQUEST — reads method, JSON body, query params, sanitized input
// =========================================================
class Request
{
    private array $body;
    private array $query;
    public string $method;
    public string $uri;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = self::resolvePath();
        $this->query = $_GET ?? [];

        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);
        $this->body = is_array($decoded) ? $decoded : $_POST;
    }

    public function input(string $key, $default = null)
    {
        $value = $this->body[$key] ?? $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }

    // Strips the front controller's own directory (e.g. /atu-connect/public)
    // from the request path so routes can always be registered as plain
    // "/api/..." regardless of which subfolder the app is deployed under.
    private static function resolvePath(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptDir = rtrim($scriptDir, '/');

        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        return $path === '' ? '/' : $path;
    }
}
