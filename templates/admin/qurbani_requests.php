<?php
$title = 'Qurbani request info';
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
/** @var array{productCount:int,partyCount:int} $adminSidebarCounts */
/** @var string $dtype */
/** @var string $location */
/** @var list<array<string, mixed>> $rows */
ob_start();
?>
<h1 class="h4 mb-3">Qurbani request information</h1>
<form method="get" class="mb-3 form-inline" action="<?= htmlspecialchars($base . '/admin/qurbani-requests', ENT_QUOTES, 'UTF-8') ?>">
    <label class="mr-2 small font-weight-bold">Delivery type</label>
    <select name="dtype" class="form-control form-control-sm mr-3">
        <option value="">— All —</option>
        <option value="Del" <?= $dtype === 'Del' ? 'selected' : '' ?>>Delivery</option>
        <option value="Pick" <?= $dtype === 'Pick' ? 'selected' : '' ?>>Pickup</option>
    </select>
    <label class="mr-2 small font-weight-bold">Location</label>
    <select name="location" class="form-control form-control-sm mr-3">
        <option value="">— All —</option>
        <option value="Wentworthville Shop" <?= $location === 'Wentworthville Shop' ? 'selected' : '' ?>>Wentworthville Shop</option>
        <option value="Deewhy Shop" <?= $location === 'Deewhy Shop' ? 'selected' : '' ?>>Deewhy Shop</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm mr-2">Filter</button>
    <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($base . '/admin/qurbani-requests?export=1&dtype=' . rawurlencode($dtype) . '&location=' . rawurlencode($location), ENT_QUOTES, 'UTF-8') ?>">Export CSV</a>
</form>

<div class="table-responsive">
    <table class="table table-sm table-bordered bg-white">
        <thead class="thead-light">
        <tr>
            <th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Entry</th><th>Del. date</th><th>Type</th><th>Location</th><th>Status</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="9" class="text-muted text-center">No rows (table <code>qurbani_data</code> missing or empty).</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int) ($r['qid'] ?? 0) ?></td>
                    <td><?= htmlspecialchars(trim((string) ($r['fname'] ?? '') . ' ' . (string) ($r['lname'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['phno'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['edt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['ddt'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['dtype'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) ($r['order_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
