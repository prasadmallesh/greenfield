<?php
$title = 'View last invoice details';
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
/** @var array{pdt:string,partynm:string,invno:string,payamt:string,paymode:string}|null $lastBill */
/** @var array{pdt:string,last_party_name:string}|null $lastEmail */
/** @var array{productCount:int,partyCount:int} $adminSidebarCounts */
$isAdmin = (string) ($_SESSION['login_user_type'] ?? '') === 'A';
ob_start();
?>
<h1 class="h4 mb-3">View last invoice details</h1>
<p class="text-muted small">Same headings as legacy <code>view-last-bill.php</code>: last bill settlement row and last invoice email row.</p>

<div class="mb-4">
    <h2 class="h6">Last invoice bill settlement</h2>
    <?php if ($lastBill === null): ?>
        <p class="text-muted small mb-0">No party credit settlement data yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered bg-white">
                <thead class="thead-light"><tr>
                    <th>Date</th><th>Party / customer</th><th>Invoice no.</th><th>Paid amt.</th><th>Pay mode</th>
                </tr></thead>
                <tbody><tr>
                    <td><?= htmlspecialchars($lastBill['pdt'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($lastBill['partynm'] !== '' ? $lastBill['partynm'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?php if ($lastBill['invno'] !== ''): ?><a href="<?= htmlspecialchars($base . '/admin/invoices/' . rawurlencode($lastBill['invno']) . '/pdf', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($lastBill['invno'], ENT_QUOTES, 'UTF-8') ?></a><?php else: ?>—<?php endif; ?></td>
                    <td><?= htmlspecialchars($lastBill['payamt'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($lastBill['paymode'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr></tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="mb-4">
    <h2 class="h6">Last invoice email send</h2>
    <?php if ($lastEmail === null): ?>
        <p class="text-muted small mb-0">No <code>last_invoice_email_data</code> row (table missing or empty).</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-bordered bg-white">
                <thead class="thead-light"><tr><th>Last email date</th><th>Party / customer</th></tr></thead>
                <tbody><tr>
                    <td><?= htmlspecialchars($lastEmail['pdt'] === '00/00/0000' ? '—' : $lastEmail['pdt'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($lastEmail['last_party_name'] !== '' ? $lastEmail['last_party_name'] : '—', ENT_QUOTES, 'UTF-8') ?></td>
                </tr></tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
    <div class="alert alert-light border small mb-0">
        <strong>Store-wise sales report</strong> and <strong>total invoice report</strong> from the legacy screen used AJAX (<code>invtot_process.php</code> / <code>saletot_process.php</code>).
        Use <a href="<?= htmlspecialchars($base . '/admin/reports', ENT_QUOTES, 'UTF-8') ?>">Reports</a> for aggregates, or extend this page with the same queries when you are ready.
    </div>
<?php endif; ?>

<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
