<?php include __DIR__ . '/../layout/app_header.php'; ?>

<h1 style="margin-top:0;">My Profile</h1>

<div class="card" style="max-width:520px;">
  <form method="post" action="<?= BASE_URL ?>/student/profile" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <div class="form-group">
      <label for="full_name">Full Name</label>
      <input type="text" id="full_name" name="full_name" class="form-control" value="<?= Validator::clean($user['full_name']) ?>" required>
      <?php if (!empty($errors['full_name'])): ?><div class="form-error"><?= Validator::clean($errors['full_name']) ?></div><?php endif; ?>
    </div>
    <div class="form-group">
      <label>Student ID</label>
      <input type="text" class="form-control" value="<?= Validator::clean($user['student_id'] ?? '') ?>" disabled>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input type="text" class="form-control" value="<?= Validator::clean($user['email']) ?>" disabled>
    </div>
    <div class="form-group">
      <label for="phone">Phone Number</label>
      <input type="text" id="phone" name="phone" class="form-control" value="<?= Validator::clean($user['phone'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="profile_photo">Profile Photo</label>
      <input type="file" id="profile_photo" name="profile_photo" accept="image/*">
    </div>
    <hr style="border:none;border-top:1px solid var(--color-border);margin:20px 0;">
    <div class="form-group">
      <label for="password">New Password (leave blank to keep current)</label>
      <input type="password" id="password" name="password" class="form-control" minlength="8">
    </div>
    <div class="form-group">
      <label for="password_confirm">Confirm New Password</label>
      <input type="password" id="password_confirm" name="password_confirm" class="form-control" minlength="8">
    </div>
    <button type="submit" class="btn btn-primary">Save Changes</button>
  </form>
</div>

<?php include __DIR__ . '/../layout/app_footer.php'; ?>
