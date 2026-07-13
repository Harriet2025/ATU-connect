<?php include __DIR__ . '/../layout/app_header.php'; ?>

<h1 style="margin-top:0;">Notifications</h1>

<div class="card">
  <?php if (empty($notifications)): ?>
    <div class="empty-state">You have no notifications.</div>
  <?php else: ?>
    <?php foreach ($notifications as $n): ?>
      <div class="list-item">
        <div>
          <div class="list-item-title"><?= Validator::clean($n['message']) ?></div>
          <div class="list-item-meta"><?= date('d M Y, g:ia', strtotime($n['created_at'])) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../layout/app_footer.php'; ?>
