<?php $pageTitle = 'Login'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <h1>Welcome back</h1>
    <p class="subtitle">Log in to ATU Connect with your Student ID/Email and password.</p>

    <?php if ($flash = Session::flash('success')): ?>
      <div class="alert alert-success"><?= Validator::clean($flash) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/login" novalidate>
      <?= Csrf::field() ?>
      <div class="form-group">
        <label for="identifier">Student ID / Index Number or Email</label>
        <input type="text" id="identifier" name="identifier" class="form-control"
               value="<?= Validator::clean($old['identifier'] ?? '') ?>" required autofocus>
        <?php if (!empty($errors['identifier'])): ?><div class="form-error"><?= Validator::clean($errors['identifier']) ?></div><?php endif; ?>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" required>
        <?php if (!empty($errors['password'])): ?><div class="form-error"><?= Validator::clean($errors['password']) ?></div><?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log In</button>
    </form>

    <div class="auth-footer-link">
      <a href="<?= BASE_URL ?>/forgot-password">Forgot password?</a>
    </div>
    <div class="auth-footer-link">
      New student? <a href="<?= BASE_URL ?>/register">Create an account</a>
    </div>
    <div class="auth-footer-link">
      <a href="<?= BASE_URL ?>/">&larr; Back to Home</a>
    </div>
  </div>
</div>
</body>
</html>
