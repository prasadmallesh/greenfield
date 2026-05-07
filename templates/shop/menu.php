<?php
$title = 'Order menu';
ob_start();
?>
<h1 class="h5 mb-3">Categories</h1>
<div class="list-group">
<?php foreach ($cats as $c): ?>
    <a class="list-group-item list-group-item-action" href="<?= htmlspecialchars($base . '/shop/category/' . (int)$c['catid'], ENT_QUOTES, 'UTF-8') ?>">
        <?= htmlspecialchars((string)($c['catname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
    </a>
<?php endforeach; ?>
</div>
<?php if ($cats === []): ?>
    <p class="text-muted">No categories with prices for your account.</p>
<?php endif; ?>
<?php
$body = ob_get_clean();
include __DIR__ . '/layout.php';
