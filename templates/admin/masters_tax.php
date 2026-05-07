<?php
$title = 'Tax Master';
/** @var list<array<string,mixed>> $rows */
/** @var array<string,mixed>|null $editRow */
/** @var string $msg */
/** @var string $alert */
ob_start();
$ed = $editRow ?? [];
$eid = (int) ($ed['taxid'] ?? 0);
?>
<h1 class="h4">Tax Master</h1>
<?php include __DIR__ . '/_flash.php'; ?>
<div class="card mb-4">
    <div class="card-body">
        <form method="post" action="<?= htmlspecialchars($base . '/admin/masters/tax', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="hd_edit_id" value="<?= $eid ?>">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Tax name</label>
                    <input type="text" name="taxnm" class="form-control" maxlength="120" required value="<?= htmlspecialchars((string)($ed['taxname'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label>Tax value (%)</label>
                    <input type="text" name="taxval" class="form-control" maxlength="6" required value="<?= htmlspecialchars((string)($ed['taxval'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-2">
                    <label>Order no.</label>
                    <input type="text" name="order_no" class="form-control" maxlength="3" value="<?= htmlspecialchars((string)($ed['order_no'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <button type="submit" class="btn text-white" style="background:#0c6932;"><?= $eid > 0 ? 'Update' : 'Add' ?></button>
            <?php if ($eid > 0): ?>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base . '/admin/masters/tax', ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<div class="table-responsive">
<table class="table table-sm table-striped table-bordered">
    <thead class="thead-light"><tr><th>ID</th><th>Name</th><th>%</th><th>Order</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int) ($r['taxid'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($r['taxname'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($r['taxval'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($r['order_no'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($base . '/admin/masters/tax?act=E&id=' . (int)($r['taxid'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete?');" action="<?= htmlspecialchars($base . '/admin/masters/tax', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="delete_id" value="<?= (int)($r['taxid'] ?? 0) ?>">
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
