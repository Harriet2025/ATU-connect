<?php $pageTitle = 'Page Not Found'; include __DIR__ . '/../layout/public_header.php'; ?>
<div class="container" style="padding:80px 20px;text-align:center;">
  <h1 style="font-size:64px;color:var(--color-primary);margin:0;">404</h1>
  <p style="font-size:18px;color:var(--color-text-muted);">The page you're looking for doesn't exist.</p>
  <a href="<?= BASE_URL ?>/" class="btn btn-primary">Back to Home</a>
</div>
<?php include __DIR__ . '/../layout/public_footer.php'; ?>
