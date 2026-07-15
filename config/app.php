<?php
// =========================================================
// APPLICATION CONFIGURATION
// =========================================================

return [
    'name'            => 'ATU Connect',
    'env'             => getenv('APP_ENV') ?: 'development',
    'debug'           => getenv('APP_DEBUG') ? filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN) : true,
    'url'             => getenv('APP_URL') ?: 'http://localhost/atu-connect',
    'timezone'        => 'Africa/Accra',

    'session' => [
        'name'              => 'atu_connect_session',
        'lifetime_minutes'  => 60,
        'remember_me_days'  => 30,
        // false by default for local HTTP dev; set APP_URL to an https://
        // address (Coolify/any HTTPS host) to switch this on automatically,
        // or set COOKIE_SECURE=true explicitly to force it.
        'cookie_secure'     => getenv('COOKIE_SECURE')
            ? filter_var(getenv('COOKIE_SECURE'), FILTER_VALIDATE_BOOLEAN)
            : str_starts_with(getenv('APP_URL') ?: 'http://localhost/atu-connect', 'https://'),
        'cookie_httponly'   => true,
        'cookie_samesite'   => 'Lax',
    ],

    'uploads' => [
        'base_path'        => __DIR__ . '/../uploads',
        'max_size_mb'      => 25,
        'allowed_assignment' => ['pdf', 'doc', 'docx', 'zip', 'rar'],
        'allowed_image'      => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_document'   => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
    ],

    'roles' => [
        'student', 'lecturer', 'dept_executive', 'src_executive', 'jcrc_executive',
        'faculty_executive', 'hod', 'faculty_dean', 'registrar_office',
        'student_affairs', 'university_management', 'administrator',
    ],

    // Roles permitted to author announcements (everyone except plain students).
    'announcement_roles' => [
        'lecturer', 'dept_executive', 'src_executive', 'jcrc_executive',
        'faculty_executive', 'hod', 'faculty_dean', 'registrar_office',
        'student_affairs', 'university_management', 'administrator',
    ],
];
