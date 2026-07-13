<?php include __DIR__ . '/../layout/public_header.php'; ?>

<div class="container" style="padding:40px 20px;">
  <h1 style="color:var(--color-primary-dark);">Departments</h1>
  <div class="two-col" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr));">
    <?php if (empty($departments)): ?>
      <div class="empty-state">No departments configured yet.</div>
    <?php endif; ?>
    <?php foreach ($departments as $d): ?>
      <div class="card">
        <h2 class="card-title"><?= Validator::clean($d['name']) ?></h2>
        <p style="color:var(--color-text-muted);font-size:14px;"><?= Validator::clean($d['description'] ?? '') ?></p>
        <a href="<?= BASE_URL ?>/department/<?= urlencode($d['slug']) ?>" class="btn btn-outline btn-sm">View Department</a>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/../layout/public_footer.php'; ?>
