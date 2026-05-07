<?php
$title = 'Calendar';
ob_start();
?>
<h1 class="h4">Calendar</h1>
<p class="text-muted">Delivery calendar (legacy <code>view-pre-orders.php</code>) will be wired here in a later pass.</p>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
