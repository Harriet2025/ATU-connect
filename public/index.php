<?php
// =========================================================
// FRONT CONTROLLER — every /api/* request enters here
// =========================================================

require_once __DIR__ . '/../config/constants.php';

date_default_timezone_set(APP_CONFIG['timezone']);

require_once BASE_PATH . '/core/Autoloader.php';
Autoloader::register();

use Core\ErrorHandler;
use Core\Session;
use Core\Request;
use Core\Router;

ErrorHandler::register();
Session::start();

$router = new Router();
require BASE_PATH . '/routes/api.php';

$request = new Request();
$router->dispatch($request);
