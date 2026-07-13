<?php
/**
 * Authenticated app shell header. Expects: $pageTitle (optional), $activeNav (optional key).
 */
$currentUser = Auth::user();
$role = Auth::primaryRole();

$navByRole = [
    'student' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/student/dashboard', 'icon' => '&#8962;'],
        ['key' => 'courses', 'label' => 'My Courses', 'href' => '/student/courses', 'icon' => '&#128218;'],
        ['key' => 'assignments', 'label' => 'Assignments', 'href' => '/student/assignments', 'icon' => '&#128221;'],
        ['key' => 'projects', 'label' => 'Projects', 'href' => '/student/projects', 'icon' => '&#128188;'],
        ['key' => 'announcements', 'label' => 'Announcements', 'href' => '/announcements', 'icon' => '&#128226;'],
        ['key' => 'messages', 'label' => 'Messages', 'href' => '/messages', 'icon' => '&#9993;'],
    ],
    'lecturer' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/lecturer/dashboard', 'icon' => '&#8962;'],
        ['key' => 'courses', 'label' => 'My Courses', 'href' => '/lecturer/courses', 'icon' => '&#128218;'],
        ['key' => 'assignments', 'label' => 'Assignments', 'href' => '/lecturer/assignments', 'icon' => '&#128221;'],
        ['key' => 'submissions', 'label' => 'Submissions', 'href' => '/lecturer/submissions', 'icon' => '&#128203;'],
        ['key' => 'messages', 'label' => 'Messages', 'href' => '/messages', 'icon' => '&#9993;'],
    ],
    'hod' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/hod/dashboard', 'icon' => '&#8962;'],
        ['key' => 'department', 'label' => 'Department Info', 'href' => '/hod/department', 'icon' => '&#127970;'],
        ['key' => 'announcements', 'label' => 'Announcements', 'href' => '/announcements', 'icon' => '&#128226;'],
        ['key' => 'messages', 'label' => 'Messages', 'href' => '/messages', 'icon' => '&#9993;'],
    ],
    'super_admin' => [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/admin/dashboard', 'icon' => '&#8962;'],
        ['key' => 'users', 'label' => 'Users', 'href' => '/admin/users', 'icon' => '&#128101;'],
        ['key' => 'departments', 'label' => 'Departments', 'href' => '/admin/departments', 'icon' => '&#127970;'],
        ['key' => 'courses', 'label' => 'Courses', 'href' => '/admin/courses', 'icon' => '&#128218;'],
        ['key' => 'announcements', 'label' => 'Announcements', 'href' => '/admin/announcements', 'icon' => '&#128226;'],
        ['key' => 'reports', 'label' => 'Reports', 'href' => '/admin/reports', 'icon' => '&#128202;'],
    ],
];
$navByRole['uni_admin'] = $navByRole['super_admin'];
$navByRole['executive'] = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/dashboard', 'icon' => '&#8962;'],
    ['key' => 'announcements', 'label' => 'Announcements', 'href' => '/announcements', 'icon' => '&#128226;'],
    ['key' => 'events', 'label' => 'Events', 'href' => '/events', 'icon' => '&#128197;'],
];
$navByRole['class_rep'] = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => '/dashboard', 'icon' => '&#8962;'],
    ['key' => 'announcements', 'label' => 'Class Announcements', 'href' => '/announcements', 'icon' => '&#128226;'],
    ['key' => 'messages', 'label' => 'Messages', 'href' => '/messages', 'icon' => '&#9993;'],
];

$navItems = $navByRole[$role] ?? $navByRole['student'];
$activeNav = $activeNav ?? '';
$unreadNotifications = $currentUser ? Notifier::unreadCount($currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? Validator::clean($pageTitle) . ' — ' : '' ?><?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body data-base-url="<?= BASE_URL ?>">
<div class="app-shell">
  <aside class="sidebar">
    <div class="sidebar-brand">ATU Connect</div>
    <nav>
      <?php foreach ($navItems as $item): ?>
        <a href="<?= BASE_URL . $item['href'] ?>" class="<?= $activeNav === $item['key'] ? 'active' : '' ?>">
          <span><?= $item['icon'] ?></span> <?= $item['label'] ?>
        </a>
      <?php endforeach; ?>
      <a href="<?= BASE_URL ?>/logout"><span>&#8630;</span> Logout</a>
    </nav>
  </aside>

  <div class="app-main">
    <header class="app-header">
      <button class="navbar-toggle" data-toggle="sidebar" aria-label="Toggle menu">&#9776;</button>
      <form class="search-box" action="<?= BASE_URL ?>/search" method="get">
        <input type="text" name="q" class="form-control" placeholder="Search courses, announcements, lecturers...">
      </form>
      <div class="header-actions">
        <a href="<?= BASE_URL ?>/notifications" class="icon-btn" title="Notifications">
          &#128276;
          <span class="badge" data-badge="notifications" style="<?= $unreadNotifications ? '' : 'display:none;' ?>"><?= $unreadNotifications ?></span>
        </a>
        <a href="<?= BASE_URL ?>/messages" class="icon-btn" title="Messages">
          &#9993;
          <span class="badge" data-badge="messages" style="display:none;"></span>
        </a>
        <span style="font-weight:600;font-size:14px;"><?= Validator::clean($currentUser['full_name'] ?? '') ?></span>
      </div>
    </header>

    <div class="app-content">
      <?php $flashError = Session::flash('error'); $flashSuccess = Session::flash('success'); ?>
      <?php if ($flashError): ?><div class="alert alert-error" data-autodismiss><?= Validator::clean($flashError) ?></div><?php endif; ?>
      <?php if ($flashSuccess): ?><div class="alert alert-success" data-autodismiss><?= Validator::clean($flashSuccess) ?></div><?php endif; ?>
