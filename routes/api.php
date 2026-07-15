<?php
// =========================================================
// API ROUTES
//
// Auth policy: every endpoint requires a logged-in session
// (Middleware\AuthMiddleware) EXCEPT POST /api/login and
// POST /api/register. This includes announcement reads —
// the whole platform sits behind login, so "public" reads
// still require an authenticated ATU Connect account (each
// controller action also calls requireAuth()/requireRole()
// itself as a defense-in-depth second check).
//
// Every non-GET route also carries Middleware\CsrfMiddleware.
// =========================================================

use Core\Router;

/** @var Router $router */

$auth = ['Middleware\\AuthMiddleware'];
$csrf = ['Middleware\\CsrfMiddleware'];
$authCsrf = ['Middleware\\AuthMiddleware', 'Middleware\\CsrfMiddleware'];

// -------------------- Auth --------------------
$router->get('/api/csrf-token', 'Controllers\\AuthController@csrfToken');
$router->post('/api/login', 'Controllers\\AuthController@login', $csrf);
$router->post('/api/register', 'Controllers\\AuthController@register', $csrf);
$router->post('/api/logout', 'Controllers\\AuthController@logout', $authCsrf);
$router->get('/api/me', 'Controllers\\AuthController@me', $auth);
$router->post('/api/password/forgot', 'Controllers\\PasswordResetController@request', $csrf);
$router->post('/api/password/reset', 'Controllers\\PasswordResetController@reset', $csrf);

// -------------------- Announcements --------------------
// Registered before /api/announcements/{id} so "categories" (a distinct,
// non-numeric path) can't be swallowed by the {id} pattern.
$router->get('/api/announcement-categories', 'Controllers\\AnnouncementController@categories', $auth);
$router->get('/api/announcements', 'Controllers\\AnnouncementController@index', $auth);
$router->get('/api/announcements/{id}', 'Controllers\\AnnouncementController@show', $auth);
$router->post('/api/announcements', 'Controllers\\AnnouncementController@store', $authCsrf);
$router->put('/api/announcements/{id}', 'Controllers\\AnnouncementController@update', $authCsrf);
$router->delete('/api/announcements/{id}', 'Controllers\\AnnouncementController@destroy', $authCsrf);
$router->post('/api/announcements/{id}/like', 'Controllers\\AnnouncementController@like', $authCsrf);
$router->post('/api/announcements/{id}/comment', 'Controllers\\AnnouncementController@comment', $authCsrf);
$router->post('/api/announcements/{id}/pin', 'Controllers\\AnnouncementController@pin', $authCsrf);

// -------------------- Assignments --------------------
$router->get('/api/assignments', 'Controllers\\AssignmentController@index', $auth);
$router->post('/api/assignments', 'Controllers\\AssignmentController@store', $authCsrf);
$router->get('/api/assignments/{id}', 'Controllers\\AssignmentController@show', $auth);
$router->put('/api/assignments/{id}', 'Controllers\\AssignmentController@update', $authCsrf);
$router->delete('/api/assignments/{id}', 'Controllers\\AssignmentController@destroy', $authCsrf);
$router->get('/api/assignments/{id}/submissions', 'Controllers\\AssignmentController@submissions', $auth);
$router->get('/api/assignments/{id}/my-submission', 'Controllers\\AssignmentController@mySubmission', $auth);
$router->post('/api/assignments/{id}/submit', 'Controllers\\AssignmentController@submit', $authCsrf);
$router->post('/api/submissions/{id}/grade', 'Controllers\\AssignmentController@grade', $authCsrf);

// -------------------- Courses --------------------
$router->get('/api/courses', 'Controllers\\CourseController@index', $auth);
$router->post('/api/courses', 'Controllers\\CourseController@store', $authCsrf); // HOD/admin
// "catalog" (public) + "mine" registered before /courses/{id} so their
// non-numeric paths aren't swallowed by the {id} pattern.
$router->get('/api/courses/catalog', 'Controllers\\CourseController@catalog'); // public — registration form
$router->get('/api/courses/mine', 'Controllers\\CourseController@mine', $auth); // lecturer
$router->get('/api/courses/{id}', 'Controllers\\CourseController@show', $auth);
$router->put('/api/courses/{id}', 'Controllers\\CourseController@update', $authCsrf); // HOD/admin
$router->post('/api/courses/{id}/register', 'Controllers\\CourseController@register', $authCsrf);
$router->post('/api/courses/{id}/drop', 'Controllers\\CourseController@drop', $authCsrf);
$router->post('/api/courses/{id}/message-class', 'Controllers\\CourseController@messageClass', $authCsrf); // lecturer

// -------------------- Lecturer management (HOD/admin) --------------------
// "pending" registered before any {id} pattern so it isn't swallowed.
$router->get('/api/lecturers/pending', 'Controllers\\LecturerController@pending', $auth);
$router->get('/api/lecturers', 'Controllers\\LecturerController@index', $auth);
$router->post('/api/lecturers/{id}/approve', 'Controllers\\LecturerController@approve', $authCsrf);
$router->post('/api/lecturers/{id}/reject', 'Controllers\\LecturerController@reject', $authCsrf);

// -------------------- Messages --------------------
// "requests" + "unread-count" registered before /messages/{id} so their
// distinct (non-numeric) paths aren't swallowed by the {id} pattern.
$router->get('/api/messages/requests', 'Controllers\\MessageController@requests', $auth);
$router->get('/api/messages/unread-count', 'Controllers\\MessageController@unreadCount', $auth);
$router->get('/api/messages', 'Controllers\\MessageController@conversations', $auth);
$router->get('/api/messages/{id}', 'Controllers\\MessageController@show', $auth);
$router->post('/api/messages', 'Controllers\\MessageController@store', $authCsrf);
$router->post('/api/messages/{id}/accept', 'Controllers\\MessageController@accept', $authCsrf);
$router->post('/api/messages/{id}/decline', 'Controllers\\MessageController@decline', $authCsrf);

// -------------------- Departments (public — for the register dropdown) --------------------
$router->get('/api/departments', 'Controllers\\DepartmentController@index');

// -------------------- Sections (group channels) --------------------
$router->get('/api/sections', 'Controllers\\SectionController@index', $auth);
$router->get('/api/sections/{id}/messages', 'Controllers\\SectionController@messages', $auth);
$router->post('/api/sections/{id}/messages', 'Controllers\\SectionController@postMessage', $authCsrf);

// -------------------- Users (message recipient picker) --------------------
$router->get('/api/users/search', 'Controllers\\UserController@search', $auth);
$router->get('/api/users', 'Controllers\\UserController@directory', $auth); // full directory

// -------------------- SMS test (self-service) --------------------
$router->post('/api/sms/test', 'Controllers\\SmsController@test', $authCsrf);

// -------------------- Notifications --------------------
$router->get('/api/notifications', 'Controllers\\NotificationController@index', $auth);
$router->post('/api/notifications/{id}/read', 'Controllers\\NotificationController@markRead', $authCsrf);
$router->post('/api/notifications/read-all', 'Controllers\\NotificationController@markAllRead', $authCsrf);
$router->delete('/api/notifications/{id}', 'Controllers\\NotificationController@destroy', $authCsrf);

// -------------------- Profile --------------------
$router->get('/api/profile', 'Controllers\\ProfileController@show', $auth);
$router->put('/api/profile', 'Controllers\\ProfileController@update', $authCsrf);
$router->post('/api/profile/picture', 'Controllers\\ProfileController@uploadPicture', $authCsrf);

// -------------------- Documents --------------------
$router->get('/api/documents', 'Controllers\\DocumentController@index', $auth);
$router->get('/api/documents/{id}', 'Controllers\\DocumentController@show', $auth);
$router->post('/api/documents', 'Controllers\\DocumentController@store', $authCsrf);
$router->put('/api/documents/{id}', 'Controllers\\DocumentController@update', $authCsrf);
$router->delete('/api/documents/{id}', 'Controllers\\DocumentController@destroy', $authCsrf);
$router->post('/api/documents/{id}/pin', 'Controllers\\DocumentController@pin', $authCsrf);

// -------------------- Events --------------------
$router->get('/api/events', 'Controllers\\EventController@index', $auth);
$router->get('/api/events/{id}', 'Controllers\\EventController@show', $auth);
$router->post('/api/events', 'Controllers\\EventController@store', $authCsrf);
$router->put('/api/events/{id}', 'Controllers\\EventController@update', $authCsrf);
$router->delete('/api/events/{id}', 'Controllers\\EventController@destroy', $authCsrf);

// -------------------- Dashboards --------------------
$router->get('/api/dashboard/student', 'Controllers\\DashboardController@student', $auth);
$router->get('/api/dashboard/lecturer', 'Controllers\\DashboardController@lecturer', $auth);
$router->get('/api/dashboard/admin', 'Controllers\\DashboardController@admin', $auth);
