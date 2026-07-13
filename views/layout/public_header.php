<?php
/** Expects: $pageTitle (optional) */
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
<header class="navbar">
  <div class="container navbar-inner">
    <a href="<?= BASE_URL ?>/" class="navbar-brand">
      <span class="brand-mark">A</span> ATU Connect
    </a>
    <nav class="navbar-links">
      <a href="<?= BASE_URL ?>/">Home</a>
      <a href="<?= BASE_URL ?>/university-info">University Info</a>
      <a href="<?= BASE_URL ?>/search">Search</a>
      <?php if (Auth::check()): ?>
        <a href="<?= BASE_URL ?>/dashboard" class="btn btn-outline btn-sm">Dashboard</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/login" class="btn btn-primary btn-sm">Login</a>
      <?php endif; ?>
    </nav>
    <button class="navbar-toggle" aria-label="Toggle menu">&#9776;</button>
  </div>
</header>
<main>
<?php $flashError = Session::flash('error'); $flashSuccess = Session::flash('success'); ?>
<?php if ($flashError || $flashSuccess): ?>
  <div class="container" style="padding-top:20px;">
    <?php if ($flashError): ?><div class="alert alert-error" data-autodismiss><?= Validator::clean($flashError) ?></div><?php endif; ?>
    <?php if ($flashSuccess): ?><div class="alert alert-success" data-autodismiss><?= Validator::clean($flashSuccess) ?></div><?php endif; ?>
  </div>
<?php endif; ?>
