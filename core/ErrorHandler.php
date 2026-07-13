<?php
namespace Core;

// =========================================================
// ERROR HANDLER — converts PHP errors/exceptions into JSON + log file
// =========================================================
class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
    }

    public static function handleError($severity, $message, $file, $line): void
    {
        self::log("Error: $message in $file on line $line");
        if (APP_CONFIG['debug']) {
            Response::error("$message in $file:$line", HTTP_SERVER_ERROR);
        }
        Response::error('An unexpected error occurred.', HTTP_SERVER_ERROR);
    }

    public static function handleException(\Throwable $e): void
    {
        self::log('Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        if (APP_CONFIG['debug']) {
            Response::error($e->getMessage(), HTTP_SERVER_ERROR, ['trace' => $e->getTraceAsString()]);
        }
        Response::error('An unexpected error occurred.', HTTP_SERVER_ERROR);
    }

    private static function log(string $message): void
    {
        $logFile = LOG_PATH . '/error_' . date('Y-m-d') . '.log';
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}
