<?php
$title = 'Revert paid invoices';
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
/** @var array{productCount:int,partyCount:int} $adminSidebarCounts */
/** @var list<array{partyid:int,partynm:string}> $parties */
/** @var string $fdt */
/** @var string $tdt */
/** @var int $pid */
/** @var list<array{partynm:string,sbid:string,paydt:string,paidamt:float,invamt:float,payid:int}> $rows */
/** @var string $flashMsg */
/** @var string $flashType */
ob_start();
?>
<h1 class="h4 mb-3">Revert paid invoice(s)</h1>
<?php if ($flashMsg !== ''): ?>
    <div class="alert alert-<?= $flashType === 'ok' ? 'success' : 'danger' ?> py-2"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<p class="text-muted small">Lists payments from <code>sale_payment_data</code> (legacy <code>SearchRevertSaleData</code>). Submitting deletes the selected payment rows — same as legacy <code>SetRevertInvoicePayment</code>.</p>

<form method="get" class="mb-3 border-bottom pb-3" action="<?= htmlspecialchars($base . '/admin/revert-paid-invoices', ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-row align-items-end">
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">Pay date from</label>
            <input type="text" name="fdt" class="form-control form-control-sm" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($fdt, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">To</label>
            <input type="text" name="tdt" class="form-control form-control-sm" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($tdt, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group col-md-4">
            <label class="small font-weight-bold">Party / account</label>
            <select name="pid" class="form-control form-control-sm">
                <option value="0">All</option>
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
      onsubmit="return confirm('Delete selected payment rows? This cannot be undone.');">
    <input type="hidden" name="fdt" value="<?= htmlspecialchars($fdt, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="tdt" value="<?= htmlspecialchars($tdt, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="pid" value="<?= (int) $pid ?>">
    <div class="table-responsive">
        <table class="table table-sm table-bordered bg-white">
            <thead class="thead-light">
            <tr>
                <th class="text-center" style="width:2rem;"><input type="checkbox" onclick="document.querySelectorAll('.revchk').forEach(function(c){c.checked=this.checked;}.bind(this))"></th>
                <th>Party</th><th>Invoice no.</th><th>Invoice amt.</th><th>Pay date</th><th>Pay amt.</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="text-muted text-center">No data</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td class="text-center"><input type="checkbox" class="revchk" name="rev[]" value="<?= htmlspecialchars($r['sbid'] . '~' . $r['payid'], ENT_QUOTES, 'UTF-8') ?>"></td>
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
    <?php if ($rows !== []): ?>
        <button type="submit" class="btn btn-danger btn-sm" name="revert" value="1">Revert selected payments</button>
    <?php endif; ?>
</form>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
