<?php include __DIR__ . '/../layout/public_header.php'; ?>

<div class="container" style="padding:40px 20px;">
  <h1 style="color:var(--color-primary-dark);"><?= Validator::clean($department['name']) ?></h1>
  <p style="color:var(--color-text-muted);"><?= Validator::clean($department['description'] ?? '') ?></p>

  <div class="card">
    <h2 class="card-title">Department Announcements</h2>
    <?php if (empty($announcements)): ?>
      <div class="empty-state">No announcements from this department yet.</div>
    <?php else: ?>
      <?php foreach ($announcements as $a): ?>
        <div class="list-item">
          <div>
            <div class="list-item-title"><?= Validator::clean($a['title']) ?></div>
            <div class="list-item-meta"><?= nl2br(Validator::clean($a['body'])) ?></div>
            <div class="list-item-meta">By <?= Validator::clean($a['author_name']) ?> &middot; <?= date('d M Y', strtotime($a['created_at'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../layout/public_footer.php'; ?>
