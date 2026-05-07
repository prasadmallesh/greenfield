<?php
$title = 'Dashboard';
ob_start();
?>
<h1 class="h4">Dashboard</h1>
<p class="text-muted">Welcome, <?= htmlspecialchars((string) ($_SESSION['login_user'] ?? ''), ENT_QUOTES, 'UTF-8') ?>.
    Use the menu above — visibility follows <strong>Page Manager</strong> settings (same idea as the legacy portal).</p>
<ul>
    <li><a href="<?= htmlspecialchars($base . '/admin/preorders', ENT_QUOTES, 'UTF-8') ?>">View pre-orders</a></li>
    <?php if ((string) ($_SESSION['login_user_type'] ?? '') === 'A'): ?>
        <li><a href="<?= htmlspecialchars($base . '/admin/page-manager', ENT_QUOTES, 'UTF-8') ?>">Page Manager</a> — enable/disable pages per staff role.</li>
    <?php endif; ?>
</ul>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
