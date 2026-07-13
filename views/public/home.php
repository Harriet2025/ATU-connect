<?php include __DIR__ . '/../layout/public_header.php'; ?>

<section class="hero">
  <div class="container">
    <h1>Welcome to ATU Connect</h1>
    <p>One centralized platform for all academic and campus communication at Accra Technical University.</p>
    <?php if (!Auth::check()): ?>
      <a href="<?= BASE_URL ?>/login" class="btn btn-accent">Login to Get Started</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/dashboard" class="btn btn-accent">Go to Dashboard</a>
    <?php endif; ?>
  </div>
</section>

<div class="container" style="padding:40px 20px;">
  <div class="two-col">
    <div class="card">
      <h2 class="card-title">Latest Announcements</h2>
      <?php if (empty($announcements)): ?>
        <div class="empty-state">No announcements yet. Check back soon.</div>
      <?php else: ?>
        <?php foreach ($announcements as $a): ?>
          <div class="list-item">
            <div>
              <div class="list-item-title"><?= Validator::clean($a['title']) ?></div>
              <div class="list-item-meta">
                By <?= Validator::clean($a['author_name']) ?> &middot; <?= date('d M Y', strtotime($a['created_at'])) ?>
              </div>
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
</div>

<?php include __DIR__ . '/../layout/public_footer.php'; ?>
