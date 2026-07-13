<?php include __DIR__ . '/../layout/app_header.php'; ?>

<h1 style="margin-top:0;">Assignments</h1>

<div class="card">
  <?php if (empty($assignments)): ?>
    <div class="empty-state">No assignments yet.</div>
  <?php else: ?>
    <?php foreach ($assignments as $a):
      $isSubmitted = !empty($a['submission_id']);
      $isPastDeadline = strtotime($a['deadline']) < time();
      $status = $isSubmitted ? ($a['score'] !== null ? 'graded' : ($a['is_late'] ? 'late' : 'submitted')) : ($isPastDeadline ? 'late' : 'pending');
    ?>
      <div class="list-item">
        <div>
          <div class="list-item-title"><?= Validator::clean($a['course_code']) ?> — <?= Validator::clean($a['title']) ?>
            <span class="status-pill <?= $status ?>"><?= $status === 'pending' ? 'Not Submitted' : ucfirst($status) ?></span>
          </div>
          <div class="list-item-meta">Due <?= date('d M Y, g:ia', strtotime($a['deadline'])) ?>
            <?php if ($isSubmitted && $a['score'] !== null): ?> &middot; Score: <?= Validator::clean($a['score']) ?>/100<?php endif; ?>
          </div>
        </div>
        <div>
          <?php if (!$isSubmitted): ?>
            <form method="post" action="<?= BASE_URL ?>/student/assignments/<?= $a['id'] ?>/submit" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
              <?= Csrf::field() ?>
              <input type="file" name="submission_file" required>
              <button type="submit" class="btn btn-primary btn-sm">Submit</button>
            </form>
          <?php else: ?>
            <span class="form-hint">Submitted <?= date('d M Y', strtotime($a['submitted_at'])) ?></span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../layout/app_footer.php'; ?>
