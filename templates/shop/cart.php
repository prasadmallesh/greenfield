<?php
$title = 'View cart';
/** @var string $error */
ob_start();
$totqty = 0.0;
$totamt = 0.0;
$totnet = 0.0;
$tottax = 0.0;
foreach ($lines as $t) {
    $tax_amt = ((float)($t['taxamt'] ?? 0) == 0.0) ? (float)($t['rptaxamt'] ?? 0) : (float)$t['taxamt'];
    $pro_rp_amt = ((float)($t['amt'] ?? 0) > 0) ? (float)$t['amt'] : (float)($t['rpamt'] ?? 0);
    $totqty += (float)($t['qty'] ?? 0);
    $totamt += $pro_rp_amt;
    $totnet += (float)($t['net_amt'] ?? 0);
    $tottax += $tax_amt;
}
foreach ($fuel as $ex) {
    $fc = (float)($ex['fuel_cost'] ?? 0);
    $totamt += $fc;
    $totnet += $fc;
}
$invdt = date('d/m/Y');
?>
<h1 class="h5 mb-3">View cart</h1>
<?php if ($error !== ''): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<?php if ($lines === []): ?>
    <p class="text-muted">Your cart is empty. <a href="<?= htmlspecialchars($base . '/shop/menu', ENT_QUOTES, 'UTF-8') ?>">Browse menu</a></p>
<?php else: ?>
<form method="post" action="">
    <div class="form-row mb-2">
        <div class="form-group col-md-3">
            <label>Order date</label>
            <input class="form-control" name="invdt" value="<?= htmlspecialchars($invdt, ENT_QUOTES, 'UTF-8') ?>" maxlength="10" required>
        </div>
        <div class="form-group col-md-5">
            <label>Special note</label>
            <textarea class="form-control" name="prodesc" rows="1"></textarea>
        </div>
    </div>
    <div class="table-responsive">
    <table class="table table-sm table-bordered bg-white">
        <thead class="thead-light"><tr>
            <th>Product</th><th>Qty</th><th>Rate</th><th>Amount</th><th>GST</th><th>Net</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($lines as $t): ?>
            <?php
            $tax_amt = ((float)($t['taxamt'] ?? 0) == 0.0) ? (float)($t['rptaxamt'] ?? 0) : (float)$t['taxamt'];
            ?>
            <tr>
                <td><?= htmlspecialchars(strtoupper((string)($t['pname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($t['qty'] ?? ''), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars((string)($t['punit'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($t['rate'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($t['amt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= number_format($tax_amt, 2, '.', '') ?></td>
                <td><?= number_format((float)($t['net_amt'] ?? 0), 2, '.', '') ?></td>
                <td><a class="btn btn-sm btn-outline-danger" href="<?= htmlspecialchars($base . '/shop/cart/delete?pid=' . (int)($t['pid'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Delete</a></td>
            </tr>
        <?php endforeach; ?>
        <?php foreach ($fuel as $ex): ?>
            <tr>
                <td colspan="5" class="text-right"><strong><?= htmlspecialchars((string)($ex['fuel_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                <td><strong><?= number_format((float)($ex['fuel_cost'] ?? 0), 2, '.', '') ?></strong></td>
                <td></td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="5" class="text-right"><strong>Total</strong></td>
            <td><strong><?= number_format($totnet, 2, '.', '') ?></strong></td>
            <td></td>
        </tr>
        <tr>
            <td colspan="5" class="text-right"><strong>Delivery date</strong></td>
            <td colspan="2">
                <select class="form-control form-control-sm" name="dvdt" required>
                    <?php foreach ($dvopts as $dk => $lab): ?>
                        <option value="<?= htmlspecialchars((string)$dk, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$lab, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        </tbody>
    </table>
    </div>
    <input type="hidden" name="hd_tot_qty" value="<?= htmlspecialchars((string)$totqty, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="hd_tot_amt" value="<?= htmlspecialchars((string)$totamt, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="hd_paid_amt" value="0">
    <input type="hidden" name="hd_bal_amt" value="<?= htmlspecialchars((string)$totamt, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="advamt" value="0">
    <input type="hidden" name="hd_paytype" value="<?= htmlspecialchars($paycode, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="hd_outstanding_amt" value="0">
    <input type="hidden" name="chkpay" value="">
    <button type="submit" name="btnsubmit" value="Submit" class="btn text-white" style="background:#0c6932;">Submit order</button>
</form>
<?php endif; ?>
<?php
$body = ob_get_clean();
include __DIR__ . '/layout.php';
