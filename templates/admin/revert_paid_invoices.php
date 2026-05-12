<?php
$title = 'Revert paid invoices';
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
/** @var array{productCount:int,partyCount:int} $adminSidebarCounts */
/** @var list<array{partyid:int,partynm:string}> $parties */
/** @var string $fdt */
/** @var string $tdt */
/** @var int $pid */
/** @var bool $hasSearch */
/** @var list<array{partynm:string,sbid:string,paydt:string,paidamt:float,invamt:float,payid:int}> $rows */
/** @var string $flashMsg */
/** @var string $flashType */
ob_start();
?>
<h1 class="h4 mb-3">Revert paid invoice(s)</h1>
<?php if ($flashMsg !== ''): ?>
    <div class="alert alert-<?= $flashType === 'ok' ? 'success' : 'danger' ?> py-2"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<div class="alert alert-light border small mb-3">
    <p class="mb-1"><strong>How this matches legacy <code>revert-bill.php</code></strong></p>
    <ul class="mb-0 pl-3">
        <li>Each table row is <strong>one payment line</strong> (<code>sale_payment_data</code>): invoice no. + pay date + pay amount, with internal <code>payid</code>).</li>
        <li>Tick <strong>one or many</strong> rows, then <strong>Revert your paid amount</strong> — the server runs <code>DELETE FROM sale_payment_data WHERE sbid = ? AND payid = ?</code> for each selection (same as <code>SetRevertInvoicePayment</code>).</li>
        <li>That <strong>removes the payment only</strong>; it does not delete the invoice header in <code>sale_data</code>. Outstanding balance on the invoice will increase again until re-paid.</li>
    </ul>
</div>

<form method="get" id="revert-search-form" class="mb-3 border-bottom pb-3" action="<?= htmlspecialchars($base . '/admin/revert-paid-invoices', ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-row align-items-end">
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">Pay date from</label>
            <input type="text" name="fdt" id="revert-fdt" class="form-control form-control-sm" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($fdt, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">To</label>
            <input type="text" name="tdt" id="revert-tdt" class="form-control form-control-sm" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($tdt, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group col-md-4">
            <label class="small font-weight-bold">Party / account</label>
            <select name="pid" id="revert-pid" class="form-control form-control-sm">
                <option value="0">— All —</option>
                <?php foreach ($parties as $pr): ?>
                    <option value="<?= (int) $pr['partyid'] ?>" <?= $pid === (int) $pr['partyid'] ? 'selected' : '' ?>><?= htmlspecialchars(strtoupper((string) ($pr['partynm'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group col-md-2">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </div>
    </div>
</form>

<form method="post" action="<?= htmlspecialchars($base . '/admin/revert-paid-invoices', ENT_QUOTES, 'UTF-8') ?>"
      onsubmit='var n=document.querySelectorAll(".oids-cb:checked").length;if(!n){alert("Please select at least one payment to revert.");return false;}return confirm("Delete "+n+" payment row(s) from sale_payment_data? This cannot be undone.");'>
    <input type="hidden" name="fdt" value="<?= htmlspecialchars($fdt, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="tdt" value="<?= htmlspecialchars($tdt, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="pid" value="<?= (int) $pid ?>">
    <div class="table-responsive">
        <table class="table table-sm table-bordered bg-white">
            <thead class="thead-light">
            <tr>
                <th class="text-center" style="width:2rem;"><input type="checkbox" title="Select all" aria-label="Select all" onclick="document.querySelectorAll('.oids-cb').forEach(function(c){c.checked=this.checked;}.bind(this))"></th>
                <th>Party</th><th>Invoice no.</th><th>Invoice amt.</th><th>Pay date</th><th>Pay amt.</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$hasSearch): ?>
                <tr><td colspan="6" class="text-muted text-center">Please select a <strong>pay date range</strong> (from and to) <strong>or</strong> a <strong>party / account</strong>, then click <strong>Search</strong> — same rule as legacy.</td></tr>
            <?php elseif ($rows === []): ?>
                <tr><td colspan="6" class="text-muted text-center">No payment rows match your filters.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-center"><input type="checkbox" class="oids-cb" name="oids[]" value="<?= htmlspecialchars($r['sbid'] . '~' . $r['payid'], ENT_QUOTES, 'UTF-8') ?>"></td>
                        <td><?= htmlspecialchars($r['partynm'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['sbid'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(number_format($r['invamt'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($r['paydt'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(number_format($r['paidamt'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($hasSearch && $rows !== []): ?>
        <button type="submit" class="btn btn-danger btn-sm" name="revert" value="1">Revert your paid amount</button>
    <?php endif; ?>
</form>
<script>
(function () {
    var f = document.getElementById('revert-search-form');
    if (!f) return;
    f.addEventListener('submit', function (e) {
        var a = document.getElementById('revert-fdt').value.trim();
        var b = document.getElementById('revert-tdt').value.trim();
        var p = document.getElementById('revert-pid').value;
        if ((a === '' || b === '') && p === '0') {
            e.preventDefault();
            alert('Please select date range or party/customer account.');
        }
    });
})();
</script>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
