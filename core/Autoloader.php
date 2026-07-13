<?php
// =========================================================
// PSR-4-STYLE AUTOLOADER (no Composer)
// Maps namespace prefixes to project folders.
// =========================================================

class Autoloader
{
    private static array $prefixes = [
        'Core\\'        => BASE_PATH . '/core',
        'Controllers\\' => BASE_PATH . '/controllers',
        'Models\\'      => BASE_PATH . '/models',
        'Middleware\\'  => BASE_PATH . '/middleware',
        'Helpers\\'     => BASE_PATH . '/helpers',
    ];

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    private static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $dir) {
            if (str_starts_with($class, $prefix)) {
                $relative = substr($class, strlen($prefix));
                $file = $dir . '/' . str_replace('\\', '/', $relative) . '.php';
                if (is_file($file)) {
                    require_once $file;
                    return;
                }
            }
        }
    }
}
