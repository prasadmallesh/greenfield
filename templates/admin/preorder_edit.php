<?php
$title = 'Edit pre-order';
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
/** @var string $sbid */
/** @var array<string, mixed> $header */
/** @var list<array<string, mixed>> $lines */
/** @var list<array<string, mixed>> $products */
/** @var list<array<string, mixed>> $fuel */
/** @var array<string, string> $dvopts */
/** @var list<array{code:string,label:string}> $payOpts */
/** @var string $invdt */
/** @var string $dvSelected */
/** @var string $flashMsg */
/** @var string $flashType */

$payCurrent = strtoupper(trim((string) ($header['paymode'] ?? 'C')));
$spnote = (string) ($header['spnote'] ?? '');
$partyNm = (string) ($header['partynm'] ?? '');

ob_start();
?>
<h1 class="h4 mb-3">Update pre-order — <?= htmlspecialchars($sbid, ENT_QUOTES, 'UTF-8') ?></h1>
<?php if ($flashMsg !== ''): ?>
    <div class="alert alert-<?= $flashType === 'ok' ? 'success' : 'danger' ?> py-2"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<p class="text-muted small">Party / account: <strong><?= htmlspecialchars($partyNm, ENT_QUOTES, 'UTF-8') ?></strong></p>

<form method="post" id="preorder-edit-form" action="">
    <input type="hidden" name="lines_json" id="lines_json" value="">
    <div class="form-row mb-3">
        <div class="form-group col-md-3">
            <label class="small font-weight-bold">Invoice / order date</label>
            <input class="form-control form-control-sm" name="invdt" id="invdt" required maxlength="10" value="<?= htmlspecialchars($invdt, ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="form-group col-md-3">
            <label class="small font-weight-bold">Payment terms</label>
            <select class="form-control form-control-sm" name="paymode" id="paymode">
                <?php foreach ($payOpts as $po): ?>
                    <option value="<?= htmlspecialchars($po['code'], ENT_QUOTES, 'UTF-8') ?>" <?= $payCurrent === $po['code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($po['label'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group col-md-6">
            <label class="small font-weight-bold">Special note</label>
            <textarea class="form-control form-control-sm" name="spnote" rows="2"><?= htmlspecialchars($spnote, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
    </div>

    <p class="small font-weight-bold text-secondary mb-1">Line items</p>
    <div class="table-responsive mb-2">
        <table class="table table-sm table-bordered bg-white" id="line-table">
            <thead class="thead-light">
                <tr>
                    <th style="min-width:14rem;">Product</th>
                    <th style="width:5rem;">Qty</th>
                    <th style="width:5rem;">Unit</th>
                    <th style="width:6rem;">Rate</th>
                    <th style="width:4rem;"></th>
                </tr>
            </thead>
            <tbody id="line-tbody">
            <?php foreach ($lines as $ln): ?>
                <tr class="line-row">
                    <td>
                        <select class="form-control form-control-sm line-pid" required>
                            <option value="">— Select —</option>
                            <?php foreach ($products as $pr): ?>
                                <option value="<?= (int) $pr['pid'] ?>" data-punit="<?= htmlspecialchars(strtoupper((string)($pr['punit'] ?? 'KG')), ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ((int)($ln['pid'] ?? 0) === (int)$pr['pid']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(strtoupper((string)($pr['pname'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm line-qty" required value="<?= htmlspecialchars((string)($ln['qty'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><input type="text" class="form-control form-control-sm line-punit text-uppercase" required maxlength="12" value="<?= htmlspecialchars((string)($ln['punit'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm line-rate" required value="<?= htmlspecialchars((string)($ln['rate'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></td>
                    <td><button type="button" class="btn btn-sm btn-outline-danger line-del" title="Remove">×</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="btn-add-line">+ Add line</button>

    <?php if ($fuel !== []): ?>
        <p class="small font-weight-bold text-secondary mb-1">Fuel / levy (included in total, same as shop)</p>
        <ul class="small mb-3">
            <?php foreach ($fuel as $f): ?>
                <li><?= htmlspecialchars((string)($f['fuel_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(number_format((float)($f['fuel_cost'] ?? 0), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="form-group col-md-6 px-0">
        <label class="small font-weight-bold">Delivery date</label>
        <select class="form-control form-control-sm" name="dvdt" id="dvdt" required>
            <?php foreach ($dvopts as $k => $lab): ?>
                <option value="<?= htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8') ?>" <?= $k === $dvSelected ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $lab, ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="d-flex flex-wrap align-items-center border-top pt-3 mt-2">
        <button type="submit" class="btn btn-primary btn-sm mr-2 mb-2" formaction="<?= htmlspecialchars($base . '/admin/preorders/' . rawurlencode($sbid) . '/modify', ENT_QUOTES, 'UTF-8') ?>" formmethod="post" id="btn-modify">Modify (save pre-order)</button>
        <button type="submit" class="btn btn-success btn-sm mr-2 mb-2" formaction="<?= htmlspecialchars($base . '/admin/preorders/' . rawurlencode($sbid) . '/finalize', ENT_QUOTES, 'UTF-8') ?>" formmethod="post" id="btn-finalize">Update &amp; create invoice</button>
        <a class="btn btn-secondary btn-sm mr-2 mb-2" href="<?= htmlspecialchars($base . '/admin/preorders', ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
        <button type="submit" class="btn btn-outline-danger btn-sm mb-2" formaction="<?= htmlspecialchars($base . '/admin/preorders/' . rawurlencode($sbid) . '/delete', ENT_QUOTES, 'UTF-8') ?>" formmethod="post" id="btn-delete" onclick="return confirm('Delete this pre-order permanently? This cannot be undone.');">Delete pre-order</button>
    </div>
    <p class="small text-muted mt-2 mb-0">Create invoice saves the bill, removes it from the pre-order list, then opens an invoice PDF in a new tab (like the old system’s print invoice step).</p>
</form>

<template id="line-row-template">
    <tr class="line-row">
        <td>
            <select class="form-control form-control-sm line-pid" required>
                <option value="">— Select —</option>
                <?php foreach ($products as $pr): ?>
                    <option value="<?= (int) $pr['pid'] ?>" data-punit="<?= htmlspecialchars(strtoupper((string)($pr['punit'] ?? 'KG')), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(strtoupper((string)($pr['pname'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" step="0.01" class="form-control form-control-sm line-qty" required value="1"></td>
        <td><input type="text" class="form-control form-control-sm line-punit text-uppercase" required maxlength="12" value="KG"></td>
        <td><input type="number" step="0.01" class="form-control form-control-sm line-rate" required value="0"></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger line-del" title="Remove">×</button></td>
    </tr>
</template>

<script>
(function () {
    var tbody = document.getElementById('line-tbody');
    var tpl = document.getElementById('line-row-template');
    var jsonField = document.getElementById('lines_json');
    var form = document.getElementById('preorder-edit-form');

    function bindRow(tr) {
        tr.querySelectorAll('.line-del').forEach(function (btn) {
            btn.onclick = function () {
                if (tbody.querySelectorAll('.line-row').length <= 1) {
                    alert('Keep at least one line.');
                    return;
                }
                tr.remove();
            };
        });
        var sel = tr.querySelector('.line-pid');
        if (sel) {
            sel.addEventListener('change', function () {
                var opt = sel.options[sel.selectedIndex];
                var pu = opt.getAttribute('data-punit') || 'KG';
                var inp = tr.querySelector('.line-punit');
                if (inp && !inp.dataset.userEdited) inp.value = pu;
            });
        }
        var puIn = tr.querySelector('.line-punit');
        if (puIn) puIn.addEventListener('input', function () { puIn.dataset.userEdited = '1'; });
    }
    tbody.querySelectorAll('.line-row').forEach(bindRow);

    document.getElementById('btn-add-line').onclick = function () {
        var node = tpl.content.cloneNode(true);
        var tr = node.querySelector('tr');
        tbody.appendChild(tr);
        bindRow(tr);
    };

    function packJson() {
        var rows = tbody.querySelectorAll('.line-row');
        var out = [];
        rows.forEach(function (tr) {
            var pid = parseInt(tr.querySelector('.line-pid').value, 10);
            var qty = parseFloat(tr.querySelector('.line-qty').value);
            var punit = (tr.querySelector('.line-punit').value || '').trim().toUpperCase();
            var rate = parseFloat(tr.querySelector('.line-rate').value);
            if (pid > 0 && qty > 0 && punit && rate > 0) {
                out.push({ pid: pid, qty: qty, punit: punit, rate: rate });
            }
        });
        jsonField.value = JSON.stringify(out);
    }

    ['btn-modify', 'btn-finalize'].forEach(function (id) {
        var b = document.getElementById(id);
        if (b) b.addEventListener('click', packJson);
    });
    form.addEventListener('submit', function (e) {
        var sub = e.submitter;
        if (sub && sub.id === 'btn-delete') {
            return;
        }
        packJson();
    });
})();
</script>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
