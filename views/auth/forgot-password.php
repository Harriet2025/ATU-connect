<?php $pageTitle = 'Forgot Password'; ?>
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
    <h1>Reset your password</h1>
    <p class="subtitle">Enter your Student ID or Email and we'll generate a reset link.</p>

    <?php if ($flash = Session::flash('success')): ?>
      <div class="alert alert-success"><?= Validator::clean($flash) ?></div>
    <?php endif; ?>

    <?php if (!empty($devResetLink)): ?>
      <div class="alert alert-info">
        Development mode — email sending is not configured, so here is your reset link:<br>
        <a href="<?= Validator::clean($devResetLink) ?>"><?= Validator::clean($devResetLink) ?></a>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/forgot-password" novalidate>
      <?= Csrf::field() ?>
      <div class="form-group">
        <label for="identifier">Student ID / Email</label>
        <input type="text" id="identifier" name="identifier" class="form-control" required>
        <?php if (!empty($errors['identifier'])): ?><div class="form-error"><?= Validator::clean($errors['identifier']) ?></div><?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
    </form>

    <div class="auth-footer-link"><a href="<?= BASE_URL ?>/login">&larr; Back to Login</a></div>
  </div>
</div>
</body>
</html>
