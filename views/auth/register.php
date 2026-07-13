<?php $pageTitle = 'Register'; ?>
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
  <div class="auth-card" style="max-width:480px;">
    <h1>Create your student account</h1>
    <p class="subtitle">Staff accounts (lecturer, HOD, admin) are created by the university administrator.</p>

    <form method="post" action="<?= BASE_URL ?>/register" novalidate>
      <?= Csrf::field() ?>
      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" class="form-control" value="<?= Validator::clean($old['full_name'] ?? '') ?>" required>
        <?php if (!empty($errors['full_name'])): ?><div class="form-error"><?= Validator::clean($errors['full_name']) ?></div><?php endif; ?>
      </div>
      <div class="form-group">
        <label for="student_id">Student ID / Index Number</label>
        <input type="text" id="student_id" name="student_id" class="form-control" value="<?= Validator::clean($old['student_id'] ?? '') ?>" required>
        <?php if (!empty($errors['student_id'])): ?><div class="form-error"><?= Validator::clean($errors['student_id']) ?></div><?php endif; ?>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control" value="<?= Validator::clean($old['email'] ?? '') ?>" required>
        <?php if (!empty($errors['email'])): ?><div class="form-error"><?= Validator::clean($errors['email']) ?></div><?php endif; ?>
      </div>
      <div class="form-group">
        <label for="class_id">Class</label>
        <select id="class_id" name="class_id" class="form-control" required>
          <option value="">Select your class</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (($old['class_id'] ?? '') == $c['id']) ? 'selected' : '' ?>><?= Validator::clean($c['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['class_id'])): ?><div class="form-error"><?= Validator::clean($errors['class_id']) ?></div><?php endif; ?>
        <?php if (empty($classes)): ?><div class="form-hint">No classes configured yet — ask an administrator to set up programmes/classes first.</div><?php endif; ?>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control" required minlength="8">
        <?php if (!empty($errors['password'])): ?><div class="form-error"><?= Validator::clean($errors['password']) ?></div><?php endif; ?>
      </div>
      <div class="form-group">
        <label for="password_confirm">Confirm Password</label>
        <input type="password" id="password_confirm" name="password_confirm" class="form-control" required minlength="8">
        <?php if (!empty($errors['password_confirm'])): ?><div class="form-error"><?= Validator::clean($errors['password_confirm']) ?></div><?php endif; ?>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create Account</button>
    </form>

    <div class="auth-footer-link">Already have an account? <a href="<?= BASE_URL ?>/login">Log in</a></div>
  </div>
</div>
</body>
</html>
