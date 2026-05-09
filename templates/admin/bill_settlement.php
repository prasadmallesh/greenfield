<?php
$title = 'Invoice bill settlement';
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
/** @var array<string, string> $ptyarr */
/** @var string $pmode */
/** @var string $fdt */
/** @var string $tdt */
/** @var int $pid */
/** @var int $cid */
/** @var string $sv */
/** @var list<array<string, mixed>> $tarr */
/** @var list<array<string, mixed>> $crparr */
/** @var list<array<string, mixed>> $partyRows */
/** @var list<array<string, mixed>> $custRows */
/** @var string $paddress */
/** @var string $cperson */
/** @var string $cmobno */
/** @var string $cpemail */
/** @var array{bid:mixed,cdt:string,baltotamt:float,balpayamt:float}|null $pbEntry */
/** @var string $flashMsg */
/** @var string $flashType */

$pchecked = $pmode === 'P' ? 'checked' : '';
$cchecked = $pmode === 'C' ? 'checked' : '';
$pdisplay = $pmode === 'P' ? '' : 'display:none';
$cdisplay = $pmode === 'C' ? '' : 'display:none';
$hdParty = isset($tarr[0]['partyid']) ? (int) $tarr[0]['partyid'] : $pid;
$hdCust = isset($tarr[0]['custid']) ? (int) $tarr[0]['custid'] : $cid;
$totbal = 0.0;

ob_start();
?>
<h1 class="h4 mb-3">Invoice bill settlement</h1>
<?php if ($flashMsg !== ''): ?>
    <div class="alert alert-<?= $flashType === 'ok' ? 'success' : 'danger' ?> py-2"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="get" class="mb-3 border-bottom pb-3" id="search-form" action="<?= htmlspecialchars($base . '/admin/bill-settlement', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="pmode" id="pmode_field" value="<?= htmlspecialchars($pmode, ENT_QUOTES, 'UTF-8') ?>">
    <div class="form-row align-items-end">
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">From</label>
            <input type="text" name="fdt" class="form-control form-control-sm" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($fdt, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">To</label>
            <input type="text" name="tdt" class="form-control form-control-sm" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($tdt, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group col-md-2">
            <label class="small d-block font-weight-bold">Party / Customer</label>
            <label class="small mr-2"><input type="radio" name="pmode_radio" value="P" <?= $pchecked ?>> Party</label>
            <label class="small"><input type="radio" name="pmode_radio" value="C" <?= $cchecked ?>> Customer</label>
        </div>
        <div class="form-group col-md-3" id="party-wrap" style="<?= htmlspecialchars($pdisplay, ENT_QUOTES, 'UTF-8') ?>">
            <label class="small font-weight-bold">Party / account</label>
            <select name="pid" class="form-control form-control-sm">
                <option value="0">— Select —</option>
                <?php foreach ($partyRows as $pr): ?>
                    <option value="<?= (int) $pr['partyid'] ?>" <?= $pid === (int) $pr['partyid'] ? 'selected' : '' ?>><?= htmlspecialchars(strtoupper((string) ($pr['partynm'] ?? '')), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group col-md-3" id="cust-wrap" style="<?= htmlspecialchars($cdisplay, ENT_QUOTES, 'UTF-8') ?>">
            <label class="small font-weight-bold">Customer</label>
            <select name="cid" class="form-control form-control-sm">
                <option value="0">— Select —</option>
                <?php foreach ($custRows as $cr): ?>
                    <option value="<?= (int) $cr['custid'] ?>" <?= $cid === (int) $cr['custid'] ? 'selected' : '' ?>><?= htmlspecialchars(strtoupper((string) ($cr['custname'] ?? '')) . ' (' . ($cr['custcode'] ?? '') . ')', ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">Invoice no.</label>
            <input type="text" name="sv" class="form-control form-control-sm" placeholder="GREEN…" style="text-transform:uppercase" value="<?= htmlspecialchars($sv, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group col-md-2">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </div>
    </div>
</form>

<form method="post" id="settle-form" action="<?= htmlspecialchars($base . '/admin/bill-settlement/save', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="btnsave" value="Save">
    <input type="hidden" name="hd_partynm" value="<?= (int) $hdParty ?>">
    <input type="hidden" name="hd_custnm" value="<?= (int) $hdCust ?>">
    <input type="hidden" name="hd_amt_container" id="hd_amt_container" value="0">
    <input type="hidden" name="hd_credit_amt" id="hd_credit_amt" value="">
    <input type="hidden" name="hd_total_balamt" id="hd_total_balamt" value="0">

    <div class="form-row mb-2">
        <div class="form-group col-auto">
            <label class="small font-weight-bold d-block">Settlement as</label>
            <label class="small mr-2"><input type="radio" name="ctype[]" value="P" <?= $pmode === 'P' ? 'checked' : '' ?>> Party / account</label>
            <?php if ($custRows !== []): ?>
                <label class="small"><input type="radio" name="ctype[]" value="C" <?= $pmode === 'C' ? 'checked' : '' ?>> Customer</label>
            <?php endif; ?>
        </div>
        <?php if ($pid > 0 && $crparr !== []): ?>
        <div class="form-group col-md-4">
            <label class="small font-weight-bold">Party credit invoice</label>
            <select name="partycr" id="partycr" class="form-control form-control-sm">
                <option value="">— Select —</option>
                <?php foreach ($crparr as $cw): ?>
                    <?php
                    $tsb = (string) ($cw['sbid'] ?? '');
                    $tamt = (float) ($cw['totamt'] ?? 0);
                    $cram = (float) ($cw['cramt'] ?? 0);
                    if (round($tamt, 2) === round($cram, 2)) {
                        continue;
                    }
                    ?>
                    <option value="<?= htmlspecialchars($tsb, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tsb, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">Total (cash envelope)</label>
            <input type="text" name="cpay_display" id="cpayamt" class="form-control form-control-sm" autocomplete="off">
        </div>
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">Balance left</label>
            <input type="text" id="bpayamt" class="form-control form-control-sm" readonly>
        </div>
        <div class="form-group col-md-2">
            <label class="small font-weight-bold">Credit amount</label>
            <input type="text" id="cramt" class="form-control form-control-sm" readonly>
        </div>
    </div>

    <?php if ($pid > 0 && $paddress !== ''): ?>
        <p class="small text-muted mb-2"><?= htmlspecialchars($cperson !== '' ? $cperson : '—', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($cmobno, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($cpemail, ENT_QUOTES, 'UTF-8') ?></p>
        <p class="small mb-3"><?= htmlspecialchars($paddress, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered bg-white">
            <thead class="thead-light">
            <tr>
                <th></th>
                <th>Invoice date</th>
                <th>Invoice no.</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Pay amount</th>
                <th>Credit amount</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($pbEntry !== null):
                $pbstr = '';
                $baltot = (float) ($pbEntry['baltotamt'] ?? 0);
                $balpaid = (float) ($pbEntry['balpayamt'] ?? 0);
                $prevbal = $baltot - $balpaid;
                if ($prevbal > 0) {
                    $totbal += $prevbal;
                }
                if (round($baltot, 2) === round($balpaid, 2)) {
                    $pbstr = 'P';
                }
                $bid = (string) ($pbEntry['bid'] ?? '');
                if ($pbstr !== 'P' && $prevbal > 0):
            ?>
                <tr>
                    <td class="text-center"><input type="checkbox" name="chk[]" class="chkcls" id="chk<?= htmlspecialchars($bid, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($bid, ENT_QUOTES, 'UTF-8') ?>" data-row="<?= htmlspecialchars($bid, ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><?= htmlspecialchars((string) ($pbEntry['cdt'] ?? ''), ENT_QUOTES, 'UTF-8') ?><input type="hidden" name="pbmode[<?= htmlspecialchars($bid, ENT_QUOTES, 'UTF-8') ?>]" value="PB"></td>
                    <td>PREVIOUS BALANCE</td>
                    <td><?= $baltot > 0 ? htmlspecialchars((string) $baltot, ENT_QUOTES, 'UTF-8') : '' ?><input type="hidden" name="tpayamt[<?= htmlspecialchars($bid, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars((string) $baltot, ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><?= htmlspecialchars(number_format($balpaid, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $prevbal > 0 ? htmlspecialchars(number_format($prevbal, 2, '.', ''), ENT_QUOTES, 'UTF-8') : '' ?><input type="hidden" id="bamt<?= htmlspecialchars($bid, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars(number_format($prevbal, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><input type="text" name="payamt[<?= htmlspecialchars($bid, ENT_QUOTES, 'UTF-8') ?>]" id="payamt<?= htmlspecialchars($bid, ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm text-box" disabled></td>
                    <td></td>
                </tr>
            <?php endif; endif; ?>

            <?php for ($t = 0, $n = count($tarr); $t < $n; $t++):
                $pstr = '';
                $invtot = (float) ($tarr[$t]['invtotamt'] ?? 0);
                $invpay = (float) ($tarr[$t]['invpayamt'] ?? 0);
                if (round($invtot, 2) === round($invpay, 2)) {
                    $pstr = 'P';
                }
                $sid = (int) ($tarr[$t]['sid'] ?? 0);
                $sbid = (string) ($tarr[$t]['sbid'] ?? '');
                $totPay = $invtot > 0 ? $invtot : '';
                $balamt = $invtot - $invpay;
                if ($balamt > 0) {
                    $totbal += $balamt;
                }
                if ($pstr === 'P' || $balamt <= 0) {
                    continue;
                }
                ?>
                <tr>
                    <td class="text-center"><input type="checkbox" name="chk[]" class="chkcls" id="chk<?= $sid ?>" value="<?= htmlspecialchars($sbid, ENT_QUOTES, 'UTF-8') ?>" data-row="<?= $sid ?>"></td>
                    <td><?= htmlspecialchars((string) ($tarr[$t]['pdt'] ?? ''), ENT_QUOTES, 'UTF-8') ?><input type="hidden" name="pbmode[<?= htmlspecialchars($sbid, ENT_QUOTES, 'UTF-8') ?>]" value="IV"></td>
                    <td><a href="<?= htmlspecialchars($base . '/admin/invoices/' . rawurlencode($sbid) . '/pdf', ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($sbid, ENT_QUOTES, 'UTF-8') ?></a></td>
                    <td><?= $totPay !== '' ? htmlspecialchars((string) $totPay, ENT_QUOTES, 'UTF-8') : '' ?><input type="hidden" name="tpayamt[<?= htmlspecialchars($sbid, ENT_QUOTES, 'UTF-8') ?>]" value="<?= htmlspecialchars((string) $totPay, ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><?= htmlspecialchars(number_format($invpay, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $balamt > 0 ? htmlspecialchars(number_format($balamt, 2, '.', ''), ENT_QUOTES, 'UTF-8') : '' ?><input type="hidden" id="bamt<?= $sid ?>" value="<?= htmlspecialchars(number_format($balamt, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><input type="text" name="payamt[<?= htmlspecialchars($sbid, ENT_QUOTES, 'UTF-8') ?>]" id="payamt<?= $sid ?>" class="form-control form-control-sm text-box pycls" disabled></td>
                    <td><input type="text" name="paycramt[<?= htmlspecialchars($sbid, ENT_QUOTES, 'UTF-8') ?>]" id="paycramt<?= $sid ?>" class="form-control form-control-sm cr-text-box" disabled></td>
                </tr>
            <?php endfor; ?>
            <tr>
                <td colspan="5" class="text-right"><strong>Total balance</strong></td>
                <td><strong><?= htmlspecialchars(number_format($totbal, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></strong></td>
                <td><span id="amt-container"></span></td>
                <td></td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="form-row mb-3">
        <div class="form-group col-md-3">
            <label class="small font-weight-bold">Pay date</label>
            <input type="text" name="invdt" id="invdt" class="form-control form-control-sm" required value="<?= htmlspecialchars(date('d/m/Y'), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group col-md-3">
            <label class="small font-weight-bold">Payment type</label>
            <select name="paytype" id="paytype" class="form-control form-control-sm">
                <?php foreach ($ptyarr as $k => $v): ?>
                    <option value="<?= htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <input type="hidden" name="pname" value="<?= (int) $pid ?>">
    <input type="hidden" name="custname" value="<?= (int) $cid ?>">

    <button type="submit" class="btn btn-primary btn-sm" id="btnsubmit">Submit payment</button>
    <a href="<?= htmlspecialchars($base . '/admin/preorders', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-secondary btn-sm ml-2">Back</a>
</form>

<script>
(function () {
    var form = document.getElementById('settle-form');
    if (!form) return;

    function parseNum(s) {
        if (s == null || s === '') return NaN;
        return parseFloat(String(s).trim().replace(',', '.'));
    }

    function setPay(rowId) {
        var chk = document.getElementById('chk' + rowId);
        if (!chk) return;
        var payEl = document.getElementById('payamt' + rowId);
        var crEl = document.getElementById('paycramt' + rowId);
        var partycr = document.getElementById('partycr');
        var bpay = document.getElementById('bpayamt');
        var bamtEl = document.getElementById('bamt' + rowId);
        if (chk.checked) {
            if (payEl) payEl.disabled = true;
            if (!partycr || partycr.value === '') {
                if (payEl) {
                    payEl.disabled = false;
                    var bamt = parseNum(bamtEl ? bamtEl.value : '0');
                    var left = parseNum(bpay ? bpay.value : '0');
                    if (document.getElementById('cpayamt') && document.getElementById('cpayamt').value !== '') {
                        payEl.value = (bamt > left ? left : bamt).toFixed(2);
                    } else {
                        payEl.value = bamt.toFixed(2);
                    }
                }
                chkAmount(rowId);
                setBalanceAmt();
            } else if (crEl) {
                crEl.disabled = false;
            }
        } else {
            if (payEl) { payEl.disabled = true; payEl.value = ''; }
            if (crEl) { crEl.disabled = true; crEl.value = ''; }
            chkAmount(rowId);
            setBalanceAmt();
        }
    }

    function sumTextBoxes(sel) {
        var tot = 0;
        document.querySelectorAll(sel).forEach(function (el) {
            if (el.value !== '' && !el.disabled) tot += parseNum(el.value) || 0;
        });
        return tot;
    }

    function chkAmount(rowId) {
        var bamtEl = document.getElementById('bamt' + rowId);
        var pamtEl = document.getElementById('payamt' + rowId);
        if (!bamtEl || !pamtEl) return true;
        var bamt = parseNum(bamtEl.value);
        var pamt = parseNum(pamtEl.value);
        if (pamt > bamt) {
            alert('Amount is greater than balance.');
            pamtEl.value = '';
            return false;
        }
        var cp = document.getElementById('cpayamt');
        var gtot = sumTextBoxes('.text-box');
        if (cp && cp.value !== '' && gtot > parseNum(cp.value)) {
            alert('Pay amount is greater than total envelope.');
            pamtEl.value = '';
            return false;
        }
        document.getElementById('hd_amt_container').value = gtot.toFixed(2);
        document.getElementById('amt-container').textContent = gtot.toFixed(2);
        setBalanceAmt();
        return true;
    }

    function setBalanceAmt() {
        var cp = document.getElementById('cpayamt');
        var bp = document.getElementById('bpayamt');
        if (!cp || !bp || cp.value === '') { bp.value = ''; return; }
        var gtot = sumTextBoxes('.text-box');
        bp.value = (parseNum(cp.value) - gtot).toFixed(2);
    }

    document.querySelectorAll('.chkcls').forEach(function (chk) {
        chk.addEventListener('change', function () {
            setPay(chk.getAttribute('data-row') || chk.id.replace('chk', ''));
        });
    });
    document.querySelectorAll('.text-box').forEach(function (el) {
        el.addEventListener('blur', function () {
            var id = el.id.replace('payamt', '');
            chkAmount(id);
        });
    });

    var cpEl = document.getElementById('cpayamt');
    if (cpEl) {
        cpEl.addEventListener('blur', function () {
            if (cpEl.value === '') document.getElementById('bpayamt').value = '';
            else setBalanceAmt();
        });
    }

    var partycr = document.getElementById('partycr');
    if (partycr) {
        partycr.addEventListener('change', function () {
            var v = partycr.value;
            if (!v) {
                document.getElementById('cramt').value = '';
                document.getElementById('hd_credit_amt').value = '';
                return;
            }
            fetch('<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>/admin/bill-settlement/api/credit?sbid=' + encodeURIComponent(v))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var p = data.price || '0';
                    document.getElementById('cramt').value = p;
                    document.getElementById('hd_credit_amt').value = p;
                    document.querySelectorAll('.chkcls').forEach(function (c) { c.checked = false; });
                    document.querySelectorAll('.text-box, .cr-text-box').forEach(function (x) {
                        x.value = ''; x.disabled = true;
                    });
                    document.getElementById('hd_amt_container').value = '0.00';
                    document.getElementById('amt-container').textContent = '0.00';
                });
        });
    }

    document.getElementById('hd_total_balamt').value = <?= json_encode(number_format($totbal, 2, '.', '')) ?>;

    form.addEventListener('submit', function (e) {
        var ok = false;
        document.querySelectorAll('.chkcls').forEach(function (c) { if (c.checked) ok = true; });
        if (!ok) { e.preventDefault(); alert('Please select at least one line.'); return false; }
        var partycr = document.getElementById('partycr');
        var crAmt = parseNum(document.getElementById('cramt') ? document.getElementById('cramt').value : '0');
        if (crAmt > 0 && partycr && partycr.value !== '') {
            document.querySelectorAll('.chkcls').forEach(function (c) {
                if (!c.checked) return;
                var id = c.getAttribute('data-row');
                var pc = document.getElementById('paycramt' + id);
                if (pc && (!pc.value || parseNum(pc.value) <= 0)) ok = false;
            });
            if (!ok) { e.preventDefault(); alert('Enter credit amount on each selected line.'); return false; }
        } else {
            var payOk = true;
            document.querySelectorAll('.chkcls').forEach(function (c) {
                if (!c.checked) return;
                var id = c.getAttribute('data-row');
                var p = document.getElementById('payamt' + id);
                if (!p || !p.value || parseNum(p.value) <= 0) payOk = false;
            });
            if (!payOk) { e.preventDefault(); alert('Enter pay amount on each selected line.'); return false; }
        }
    });

    var searchForm = document.getElementById('search-form');
    if (searchForm) {
        document.querySelectorAll('input[name=pmode_radio]').forEach(function (r) {
            r.addEventListener('change', function () {
                document.getElementById('pmode_field').value = r.value;
                document.getElementById('party-wrap').style.display = r.value === 'P' ? '' : 'none';
                document.getElementById('cust-wrap').style.display = r.value === 'C' ? '' : 'none';
            });
        });
    }
})();
</script>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
