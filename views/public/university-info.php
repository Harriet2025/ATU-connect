<?php include __DIR__ . '/../layout/public_header.php'; ?>

<div class="container" style="padding:40px 20px;">
  <h1 style="color:var(--color-primary-dark);">University Information</h1>
  <p style="color:var(--color-text-muted);">Academic calendar, registration notices, exam schedules, news, scholarships and internship opportunities.</p>

  <div class="card">
    <h2 class="card-title">Announcements & Notices</h2>
    <?php if (empty($announcements)): ?>
      <div class="empty-state">No notices published yet.</div>
    <?php else: ?>
      <?php foreach ($announcements as $a): ?>
        <div class="list-item">
          <div>
            <div class="list-item-title"><?= Validator::clean($a['title']) ?>
              <span class="status-pill graded" style="text-transform:capitalize;"><?= Validator::clean($a['category']) ?></span>
            </div>
            <div class="list-item-meta"><?= nl2br(Validator::clean($a['body'])) ?></div>
            <div class="list-item-meta">By <?= Validator::clean($a['author_name']) ?> &middot; <?= date('d M Y', strtotime($a['created_at'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="card-title">Upcoming Events</h2>
    <?php if (empty($events)): ?>
      <div class="empty-state">No upcoming events.</div>
    <?php else: ?>
      <?php foreach ($events as $e): ?>
        <div class="list-item">
          <div>
            <div class="list-item-title"><?= Validator::clean($e['title']) ?></div>
            <div class="list-item-meta"><?= date('d M Y, g:ia', strtotime($e['starts_at'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../layout/public_footer.php'; ?>
