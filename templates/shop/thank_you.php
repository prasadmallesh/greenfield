<?php
$title = 'Thank you';
ob_start();
?>
<h1 class="h5 text-success">Thank you</h1>
<?php if (($sbid ?? '') !== ''): ?>
    <p>Your order reference: <strong><?= htmlspecialchars($sbid, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <p><a target="_blank" href="<?= htmlspecialchars($base . '/shop/invoice/' . rawurlencode($sbid) . '/pdf', ENT_QUOTES, 'UTF-8') ?>">Download PDF</a></p>
<?php else: ?>
    <p class="text-muted">No reference stored (open this page directly?).</p>
<?php endif; ?>
<p class="small text-muted">Email / WhatsApp to admin is <strong>not sent</strong> while <code>APP_DISABLE_OUTBOUND_COMMUNICATIONS=1</code> (default).</p>
<p><a href="<?= htmlspecialchars($base . '/shop/menu', ENT_QUOTES, 'UTF-8') ?>">Continue ordering</a></p>
<?php
$body = ob_get_clean();
include __DIR__ . '/layout.php';
