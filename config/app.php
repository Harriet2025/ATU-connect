<?php
// =========================================================
// APPLICATION CONFIGURATION
// =========================================================

return [
    'name'            => 'ATU Connect',
    'env'             => getenv('APP_ENV') ?: 'development',
    'debug'           => getenv('APP_DEBUG') ? filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN) : true,
    'url'             => getenv('APP_URL') ?: 'http://localhost/atu-connect/public',
    'timezone'        => 'Africa/Accra',

    'session' => [
        'name'              => 'atu_connect_session',
        'lifetime_minutes'  => 60,
        'remember_me_days'  => 30,
        'cookie_secure'     => false, // set true when served over HTTPS
        'cookie_httponly'   => true,
        'cookie_samesite'   => 'Lax',
    ],

    'uploads' => [
        'base_path'        => __DIR__ . '/../public/uploads',
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
