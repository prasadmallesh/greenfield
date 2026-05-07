<?php
$title = 'Unit Master';
/** @var list<array<string,mixed>> $rows */
/** @var array<string,mixed>|null $editRow */
/** @var string $msg */
/** @var string $alert */
ob_start();
$ed = $editRow ?? [];
$eid = (int) ($ed['utid'] ?? 0);
?>
<h1 class="h4">Unit Master</h1>
<?php include __DIR__ . '/_flash.php'; ?>
<div class="card mb-4">
    <div class="card-body">
        <form method="post" action="<?= htmlspecialchars($base . '/admin/masters/units', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="hd_edit_id" value="<?= $eid ?>">
            <div class="form-group">
                <label>Unit name</label>
                <input type="text" name="unitnm" class="form-control" maxlength="50" required value="<?= htmlspecialchars((string)($ed['unitnm'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <button type="submit" class="btn text-white" style="background:#0c6932;"><?= $eid > 0 ? 'Update' : 'Add' ?></button>
            <?php if ($eid > 0): ?>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base . '/admin/masters/units', ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<div class="table-responsive">
<table class="table table-sm table-striped table-bordered">
    <thead class="thead-light"><tr><th>ID</th><th>Unit</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int) ($r['utid'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($r['unitnm'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($base . '/admin/masters/units?act=E&id=' . (int)($r['utid'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete?');" action="<?= htmlspecialchars($base . '/admin/masters/units', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="delete_id" value="<?= (int)($r['utid'] ?? 0) ?>">
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
