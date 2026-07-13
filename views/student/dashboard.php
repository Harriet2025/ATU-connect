<?php include __DIR__ . '/../layout/app_header.php'; ?>

<h1 style="margin-top:0;">Welcome back, <?= Validator::clean(Auth::user()['full_name']) ?></h1>

<div class="stat-grid" style="margin-bottom:24px;">
  <div class="stat-card"><div class="stat-value"><?= $courseCount ?></div><div class="stat-label">Courses</div></div>
  <div class="stat-card"><div class="stat-value"><?= count($assignments) ?></div><div class="stat-label">Assignments</div></div>
  <div class="stat-card"><div class="stat-value"><?= $pendingCount ?></div><div class="stat-label">Pending Submissions</div></div>
  <div class="stat-card"><div class="stat-value"><?= count($deadlines) ?></div><div class="stat-label">Upcoming Deadlines</div></div>
</div>

<div class="two-col">
  <div class="card">
    <h2 class="card-title">Recent Announcements</h2>
    <?php if (empty($announcements)): ?>
      <div class="empty-state">No announcements yet.</div>
    <?php else: ?>
      <?php foreach ($announcements as $a): ?>
        <div class="list-item">
          <div>
            <div class="list-item-title"><?= Validator::clean($a['title']) ?></div>
            <div class="list-item-meta">By <?= Validator::clean($a['author_name']) ?> &middot; <?= date('d M Y', strtotime($a['created_at'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="card-title">Upcoming Deadlines</h2>
    <?php if (empty($deadlines)): ?>
      <div class="empty-state">No upcoming deadlines.</div>
    <?php else: ?>
      <?php foreach ($deadlines as $d): ?>
        <div class="list-item">
          <div>
            <div class="list-item-title"><?= Validator::clean($d['course_code']) ?> — <?= Validator::clean($d['title']) ?></div>
            <div class="list-item-meta">Due <?= date('d M Y, g:ia', strtotime($d['deadline'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../layout/app_footer.php'; ?>
