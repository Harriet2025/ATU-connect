<?php
// =========================================================
// GLOBAL CONSTANTS
// =========================================================

define('BASE_PATH', dirname(__DIR__));

// Local/environment secrets (DB credentials etc.) — untracked, optional.
if (file_exists(BASE_PATH . '/config/.env.php')) {
    require BASE_PATH . '/config/.env.php';
}

define('APP_CONFIG', require BASE_PATH . '/config/app.php');
define('DB_CONFIG', require BASE_PATH . '/config/database.php');

// uploads live at the project/web root so they're directly web-servable by Apache
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('LOG_PATH', BASE_PATH . '/uploads/logs');
define('UPLOAD_URL_BASE', '/uploads');

// HTTP status codes used across controllers
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_VALIDATION_ERROR', 422);
define('HTTP_SERVER_ERROR', 500);

// Roles
define('ROLE_STUDENT', 'student');
define('ROLE_LECTURER', 'lecturer');
define('ROLE_DEPT_EXECUTIVE', 'dept_executive');
define('ROLE_SRC_EXECUTIVE', 'src_executive');
define('ROLE_JCRC_EXECUTIVE', 'jcrc_executive');
define('ROLE_FACULTY_EXECUTIVE', 'faculty_executive');
define('ROLE_HOD', 'hod');
define('ROLE_FACULTY_DEAN', 'faculty_dean');
define('ROLE_REGISTRAR_OFFICE', 'registrar_office');
define('ROLE_STUDENT_AFFAIRS', 'student_affairs');
define('ROLE_UNIVERSITY_MANAGEMENT', 'university_management');
define('ROLE_ADMIN', 'administrator');

// Roles authorized to author announcements (all except plain students)
define('ANNOUNCEMENT_ROLES', [
    ROLE_LECTURER, ROLE_DEPT_EXECUTIVE, ROLE_SRC_EXECUTIVE, ROLE_JCRC_EXECUTIVE,
    ROLE_FACULTY_EXECUTIVE, ROLE_HOD, ROLE_FACULTY_DEAN, ROLE_REGISTRAR_OFFICE,
    ROLE_STUDENT_AFFAIRS, ROLE_UNIVERSITY_MANAGEMENT, ROLE_ADMIN,
]);
