<?php
// =========================================================
// STANDALONE CRON SCRIPT — assignment due-date reminders
//
// Not reachable via the app's normal routes/front controller. Run manually
// (`php cron_reminders.php`) or schedule daily via Windows Task Scheduler
// (see README "Scheduling reminders" section).
// =========================================================

require_once __DIR__ . '/../config/constants.php';

date_default_timezone_set(APP_CONFIG['timezone']);

require_once BASE_PATH . '/core/Autoloader.php';
Autoloader::register();

use Controllers\AssignmentController;

AssignmentController::remindDueSoon();

echo "Reminders dispatched." . PHP_EOL;
