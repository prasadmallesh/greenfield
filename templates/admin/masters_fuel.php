<?php
$title = 'Fuel Master';
/** @var list<array<string,mixed>> $rows */
/** @var array<string,mixed>|null $editRow */
/** @var string $msg */
/** @var string $alert */
ob_start();
$ed = $editRow ?? [];
$eid = (int) ($ed['fuel_id'] ?? 0);
?>
<h1 class="h4">Fuel Master</h1>
<?php include __DIR__ . '/_flash.php'; ?>
<div class="card mb-4">
    <div class="card-body">
        <form method="post" action="<?= htmlspecialchars($base . '/admin/masters/fuel', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="hd_edit_id" value="<?= $eid ?>">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Fuel name</label>
                    <input type="text" name="fuel_name" class="form-control" required value="<?= htmlspecialchars((string)($ed['fuel_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label>Cost</label>
                    <input type="number" step="0.01" name="fuel_cost" class="form-control" required value="<?= htmlspecialchars((string)($ed['fuel_cost'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-2 d-flex align-items-end">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="fuelact" name="is_active" <?= ((int)($ed['is_active'] ?? 1) === 1) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="fuelact">Active</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn text-white" style="background:#0c6932;"><?= $eid > 0 ? 'Update' : 'Add' ?></button>
            <?php if ($eid > 0): ?>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base . '/admin/masters/fuel', ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<div class="table-responsive">
<table class="table table-sm table-striped table-bordered">
    <thead class="thead-light"><tr><th>ID</th><th>Name</th><th>Cost</th><th>Active</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int) ($r['fuel_id'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($r['fuel_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($r['fuel_cost'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int)($r['is_active'] ?? 0) === 1 ? 'Y' : 'N' ?></td>
            <td>
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($base . '/admin/masters/fuel?act=E&id=' . (int)($r['fuel_id'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete?');" action="<?= htmlspecialchars($base . '/admin/masters/fuel', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="delete_id" value="<?= (int)($r['fuel_id'] ?? 0) ?>">
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
