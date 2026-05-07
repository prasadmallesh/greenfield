<?php
$title = 'Product Master';
/** @var list<array<string,mixed>> $rows */
/** @var list<array<string,mixed>> $categories */
/** @var list<array<string,mixed>> $units */
/** @var array<string,mixed>|null $editRow */
/** @var string $msg */
/** @var string $alert */
ob_start();
$ed = $editRow ?? [];
$eid = (int) ($ed['pid'] ?? 0);
$selCat = (int) ($ed['catid'] ?? 0);
$selUnit = (string) ($ed['punit'] ?? '');
?>
<h1 class="h4">Product Master</h1>
<?php include __DIR__ . '/_flash.php'; ?>
<div class="card mb-4">
    <div class="card-header"><?= $eid > 0 ? 'Edit' : 'Add' ?> product</div>
    <div class="card-body">
        <form method="post" action="<?= htmlspecialchars($base . '/admin/masters/products', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="hd_edit_id" value="<?= $eid ?>">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Category</label>
                    <select name="category" class="form-control" required>
                        <option value="">—</option>
                        <?php foreach ($categories as $c): ?>
                            <?php $cid = (int) ($c['catid'] ?? 0); ?>
                            <option value="<?= $cid ?>"<?= $cid === $selCat ? ' selected' : '' ?>><?= htmlspecialchars((string)($c['catname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>Product name</label>
                    <input type="text" name="pname" class="form-control" required maxlength="200" value="<?= htmlspecialchars((string)($ed['pname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Unit</label>
                    <select name="punit" class="form-control" required>
                        <option value="">—</option>
                        <?php foreach ($units as $u): ?>
                            <?php $unm = (string) ($u['unitnm'] ?? ''); ?>
                            <option value="<?= htmlspecialchars($unm, ENT_QUOTES, 'UTF-8') ?>"<?= $unm === $selUnit ? ' selected' : '' ?>><?= htmlspecialchars($unm, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control" maxlength="500" value="<?= htmlspecialchars((string)($ed['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label>Minimum cost</label>
                    <input type="number" step="0.01" name="mincost" class="form-control" value="<?= htmlspecialchars((string)($ed['mincost'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label>Purchase cost (current)</label>
                    <input type="number" step="0.01" name="purchase_cost" class="form-control" value="<?= htmlspecialchars((string)($ed['purchase_cost'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3 d-flex align-items-end">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="taxinc" name="tax" <?= ((int)($ed['tax_include'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="taxinc">Tax included</label>
                    </div>
                </div>
                <div class="form-group col-md-3 d-flex align-items-end">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="pact" name="is_active" <?= ((int)($ed['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="pact">Active</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn text-white" style="background:#0c6932;"><?= $eid > 0 ? 'Update' : 'Add' ?></button>
            <?php if ($eid > 0): ?>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base . '/admin/masters/products', ENT_QUOTES, 'UTF-8') ?>">Cancel edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<h2 class="h6">All products</h2>
<div class="table-responsive">
<table class="table table-sm table-striped table-bordered">
    <thead class="thead-light"><tr><th>ID</th><th>Name</th><th>Category</th><th>Unit</th><th>Active</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int) ($r['pid'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($r['pname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($r['catname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($r['punit'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)($r['is_active'] ?? 0) === 1 ? 'Y' : 'N' ?></td>
            <td>
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($base . '/admin/masters/products?act=E&id=' . (int)($r['pid'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this product?');" action="<?= htmlspecialchars($base . '/admin/masters/products', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="delete_id" value="<?= (int)($r['pid'] ?? 0) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
