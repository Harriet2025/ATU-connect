<?php $pageTitle = 'Reset Password'; ?>
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
    <h1>Choose a new password</h1>
    <form method="post" action="<?= BASE_URL ?>/reset-password" novalidate>
      <?= Csrf::field() ?>
      <input type="hidden" name="token" value="<?= Validator::clean($_GET['token'] ?? $_POST['token'] ?? '') ?>">
      <div class="form-group">
        <label for="password">New Password</label>
        <input type="password" id="password" name="password" class="form-control" required minlength="8">
        <?php if (!empty($errors['password'])): ?><div class="form-error"><?= Validator::clean($errors['password']) ?></div><?php endif; ?>
      </div>
      <div class="form-group">
        <label for="password_confirm">Confirm New Password</label>
        <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="8">
        <?php if (!empty($errors['password_confirm'])): ?><div class="form-error"><?= Validator::clean($errors['password_confirm']) ?></div><?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Update Password</button>
    </form>
  </div>
</div>
</body>
</html>
