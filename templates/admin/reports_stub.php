<?php
$title = 'Reports';
ob_start();
?>
<h1 class="h4">Reports</h1>
<p class="text-muted">Detailed reports (invoice balance, party-wise, pay in/out, etc.) are planned for <strong>Phase 2</strong>.
    Menu visibility here still follows Page Manager flags, matching the old software behaviour.</p>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
