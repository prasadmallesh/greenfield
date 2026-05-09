<?php
$title = 'Invoice created';
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
/** @var string $sbid */
/** @var string $pdfUrl */
ob_start();
?>
<h1 class="h4 mb-3">Invoice created</h1>
<p class="mb-2">Bill <strong><?= htmlspecialchars($sbid, ENT_QUOTES, 'UTF-8') ?></strong> has been saved as a normal invoice (no longer in the pre-order list).</p>
<p class="mb-3">
    <a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Open invoice PDF</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($base . '/admin/preorders', ENT_QUOTES, 'UTF-8') ?>">Back to pre-orders</a>
</p>
<p class="text-muted small">A PDF tab should open automatically (allow pop-ups if it did not).</p>
<script>
(function () {
    var u = <?= json_encode($pdfUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
    window.open(u, 'InvoicePrint', 'width=900,height=700,toolbar=yes,resizable=yes,scrollbars=yes');
})();
</script>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
