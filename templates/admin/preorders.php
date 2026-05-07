<?php
$title = 'View Pre Order(s)';
/** @var list<array<string,mixed>> $rows */
/** @var list<array<string,mixed>> $parties */
/** @var list<array<string,mixed>> $users */
ob_start();
$ut = (string) ($_SESSION['login_user_type'] ?? '');
?>
<h1 class="h4">View Pre Order(s)</h1>
<form class="form-inline mb-3" method="get" action="">
    <label class="mr-1 small">From</label>
    <input class="form-control form-control-sm mr-2" name="fdt" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($fdt, ENT_QUOTES, 'UTF-8') ?>">
    <label class="mr-1 small">To</label>
    <input class="form-control form-control-sm mr-2" name="tdt" placeholder="dd/mm/yyyy" value="<?= htmlspecialchars($tdt, ENT_QUOTES, 'UTF-8') ?>">
    <?php if ($ut === 'A'): ?>
        <label class="mr-1 small">Party</label>
        <select class="form-control form-control-sm mr-2" name="pid">
            <option value="0">All</option>
            <?php foreach ($parties as $p): ?>
                <option value="<?= (int) $p['partyid'] ?>" <?= ((int)($pid ?? 0) === (int)$p['partyid']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $p['partynm'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label class="mr-1 small">User</label>
        <select class="form-control form-control-sm mr-2" name="puid">
            <option value="0">All</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int) $u['uid'] ?>" <?= ((int)($puid ?? 0) === (int)$u['uid']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) $u['user_name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <button type="submit" class="btn btn-sm text-white" style="background:#0c6932;">Filter</button>
</form>
<div class="table-responsive">
<table class="table table-sm table-striped table-bordered">
    <thead class="thead-light"><tr>
        <th>Bill</th><th>Party</th><th>Amount</th><th>Pay mode</th><th>Order dt</th><th>Delivery</th><th>Type</th><th>PDF</th><th>Mark invoiced</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= htmlspecialchars((string) ($r['sbid'] ?: '—'), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($r['partynm'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($r['totamt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($r['paymode'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($r['sdt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($r['dvdt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($r['invname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <?php if (($r['sbid'] ?? '') !== ''): ?>
                    <a class="small" target="_blank" href="<?= htmlspecialchars($base . '/admin/invoices/' . rawurlencode((string)$r['sbid']) . '/pdf', ENT_QUOTES, 'UTF-8') ?>">PDF</a>
                <?php else: ?>
                    <span class="text-muted small">—</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if (($r['sbid'] ?? '') !== ''): ?>
                <form method="post" action="<?= htmlspecialchars($base . '/admin/preorders/convert', ENT_QUOTES, 'UTF-8') ?>" class="d-inline" onsubmit="return confirm('Mark this pre-order as converted to invoice?');">
                    <input type="hidden" name="sbid" value="<?= htmlspecialchars((string)$r['sbid'], ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Convert</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if ($rows === []): ?>
        <tr><td colspan="9" class="text-center text-muted">No rows</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
