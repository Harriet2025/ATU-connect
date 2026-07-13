<?php include __DIR__ . '/../layout/app_header.php'; ?>

<h1 style="margin-top:0;">My Courses</h1>

<div class="card">
  <?php if (empty($courses)): ?>
    <div class="empty-state">You are not enrolled in any courses yet.</div>
  <?php else: ?>
    <table>
      <thead><tr><th>Code</th><th>Title</th><th>Lecturer</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($courses as $c): ?>
          <tr>
            <td><?= Validator::clean($c['code']) ?></td>
            <td><?= Validator::clean($c['title']) ?></td>
            <td><?= Validator::clean($c['lecturer_name']) ?></td>
            <td><a href="<?= BASE_URL ?>/course/<?= $c['id'] ?>" class="btn btn-outline btn-sm">Open</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../layout/app_footer.php'; ?>
