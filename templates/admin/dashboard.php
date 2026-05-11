<?php
$title = 'Dashboard';
ob_start();
?>
<h1 class="h4">Dashboard</h1>
<p class="text-muted">Welcome, <?= htmlspecialchars((string) ($_SESSION['login_user'] ?? ''), ENT_QUOTES, 'UTF-8') ?>.
    Shortcuts on the <strong>left</strong> mirror the legacy <code>component/left-sidebar.php</code> (badges, bill settlement, last invoice, calendar, revert, Qurbani).
    The <strong>top</strong> menu groups masters, invoices, and reports. Visibility follows <strong>Page Manager</strong> flags.</p>
<ul>
    <li><a href="<?= htmlspecialchars($base . '/admin/preorders', ENT_QUOTES, 'UTF-8') ?>">View pre-orders</a></li>
    <?php if ((string) ($_SESSION['login_user_type'] ?? '') === 'A'): ?>
        <li><a href="<?= htmlspecialchars($base . '/admin/page-manager', ENT_QUOTES, 'UTF-8') ?>">Page Manager</a> — enable/disable pages per staff role.</li>
    <?php endif; ?>
</ul>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
