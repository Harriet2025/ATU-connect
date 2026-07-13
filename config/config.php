<?php
/**
 * Central configuration. In production, load secrets from environment variables
 * instead of hardcoding them here.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'atu_connect');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('BASE_URL', '/atu-connect/public');
define('STORAGE_PATH', dirname(__DIR__) . '/storage');

define('SESSION_NAME', 'atu_connect_session');
define('SESSION_LIFETIME', 60 * 60 * 4); // 4 hours

define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024); // 20MB
define('ALLOWED_UPLOAD_TYPES', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip']);

define('APP_NAME', 'ATU Connect');
define('APP_ENV', 'development'); // development | production

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
