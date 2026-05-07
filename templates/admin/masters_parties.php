<?php
$title = 'Party Master';
/** @var list<array<string,mixed>> $rows */
/** @var array<string,mixed>|null $editRow */
/** @var array<string,string> $payTerms code => label */
/** @var list<array<string,mixed>> $staffUsers */
/** @var string $msg */
/** @var string $alert */
ob_start();
$ut = (string) ($_SESSION['login_user_type'] ?? '');
$isAdmin = $ut === 'A';
$staffUid = (int) ($_SESSION['login_user_id'] ?? 0);
$ed = $editRow ?? [];
$eid = (int) ($ed['partyid'] ?? 0);
$ptCode = 'C';
if ($eid > 0) {
    $raw = strtoupper(trim((string) ($ed['pterms'] ?? '')));
    if ($raw !== '' && isset($payTerms[$raw])) {
        $ptCode = $raw;
    } else {
        $k = array_search($raw, $payTerms, true);
        $ptCode = $k !== false ? (string) $k : 'C';
    }
}
?>
<h1 class="h4">Account / Party Master</h1>
<p class="text-muted small">Party details match legacy account master (name, address, terms, limits, assigned user).</p>
<?php include __DIR__ . '/_flash.php'; ?>
<div class="card mb-4">
    <div class="card-header"><?= $eid > 0 ? 'Edit party' : 'Add party' ?></div>
    <div class="card-body">
        <form method="post" action="<?= htmlspecialchars($base . '/admin/masters/parties', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="form_action" value="save">
            <input type="hidden" name="hd_edit_id" value="<?= $eid ?>">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Party / business name</label>
                    <input type="text" name="partynm" class="form-control" required maxlength="200" value="<?= htmlspecialchars((string)($ed['partynm'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-3">
                    <label>ABN</label>
                    <input type="text" name="abnno" class="form-control" maxlength="50" value="<?= htmlspecialchars((string)($ed['abnno'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <?php if ($eid > 0 && !empty($ed['party_accno'])): ?>
                <div class="form-group col-md-3">
                    <label>Account no.</label>
                    <input type="text" class="form-control" readonly value="<?= htmlspecialchars((string)$ed['party_accno'], ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="paddress" class="form-control" required maxlength="300" value="<?= htmlspecialchars((string)($ed['address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>City</label>
                    <input type="text" name="city" class="form-control" required maxlength="120" value="<?= htmlspecialchars((string)($ed['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>State</label>
                    <input type="text" name="state" class="form-control" maxlength="80" value="<?= htmlspecialchars((string)($ed['state'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>TIN / tax ID</label>
                    <input type="text" name="tinno" class="form-control" maxlength="80" value="<?= htmlspecialchars((string)($ed['tinno'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Contact person</label>
                    <input type="text" name="cpersonm" class="form-control" maxlength="120" value="<?= htmlspecialchars((string)($ed['cpersonm'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Phone</label>
                    <input type="text" name="contactno" class="form-control" maxlength="40" value="<?= htmlspecialchars((string)($ed['contactno'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required maxlength="120" value="<?= htmlspecialchars((string)($ed['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label>Alternate email</label>
                    <input type="email" name="email2" class="form-control" maxlength="120" value="<?= htmlspecialchars((string)($ed['email2'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-6">
                    <label>Sales person / ref.</label>
                    <input type="text" name="sper" class="form-control" maxlength="120" value="<?= htmlspecialchars((string)($ed['sper'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Payment terms</label>
                    <select name="pterms" class="form-control">
                        <?php foreach ($payTerms as $code => $lbl): ?>
                            <option value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"<?= $code === $ptCode ? ' selected' : '' ?>><?= htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-8">
                    <label>Delivery instructions</label>
                    <input type="text" name="deliveryins" class="form-control" maxlength="500" value="<?= htmlspecialchars((string)($ed['deliveryins'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>Outstanding limit (amount)</label>
                    <input type="text" name="outstanding_limit_amt" class="form-control" value="<?= htmlspecialchars((string)($ed['outstanding_limit_amt'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="form-group col-md-4 d-flex align-items-end">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="pstatus" name="pstatus" <?= ($eid <= 0 || (int)($ed['is_active'] ?? 0) === 1 || (string)($ed['is_active'] ?? '') === '1') ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="pstatus">Active</label>
                    </div>
                </div>
                <div class="form-group col-md-4 d-flex align-items-end">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="hideinv" name="hide_invoice" <?= ((int)($ed['hide_invoice'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="hide_invoice">Hide invoice</label>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input" id="ignmin" name="ignore_pro_minprice" <?= ((int)($ed['ignore_pro_minprice'] ?? 0) === 1) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="ignmin">Ignore product minimum price</label>
                </div>
            </div>
            <div class="form-group">
                <label>Assigned staff user</label>
                <?php if ($isAdmin): ?>
                    <select name="puser" class="form-control">
                        <option value="0">— Unassigned —</option>
                        <?php foreach ($staffUsers as $su): ?>
                            <?php $uid = (int) ($su['uid'] ?? 0); ?>
                            <option value="<?= $uid ?>"<?= ((int)($ed['uid'] ?? 0) === $uid) ? ' selected' : '' ?>><?= htmlspecialchars((string)($su['user_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="puser" value="<?= $staffUid ?>">
                    <p class="form-control-plaintext border rounded px-2 py-1 bg-light mb-0">New and edited parties are assigned to your user (ID <?= $staffUid ?>).</p>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn text-white" style="background:#0c6932;"><?= $eid > 0 ? 'Update' : 'Add' ?></button>
            <?php if ($eid > 0): ?>
                <a class="btn btn-outline-secondary" href="<?= htmlspecialchars($base . '/admin/masters/parties', ENT_QUOTES, 'UTF-8') ?>">Cancel edit</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<h2 class="h6">Parties</h2>
<div class="table-responsive">
<table class="table table-sm table-striped table-bordered">
    <thead class="thead-light">
        <tr><th>ID</th><th>Name</th><th>City</th><th>Email</th><th>User</th><th>Active</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= (int) ($r['partyid'] ?? 0) ?></td>
            <td><?= htmlspecialchars((string)($r['partynm'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($r['city'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($r['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string)($r['user_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= ((int)($r['is_active'] ?? 0) === 1 || (string)($r['is_active'] ?? '') === '1') ? 'Y' : 'N' ?></td>
            <td>
                <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($base . '/admin/masters/parties?act=E&id=' . (int)($r['partyid'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                <?php if ($isAdmin): ?>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this party and related sales? This cannot be undone.');" action="<?= htmlspecialchars($base . '/admin/masters/parties', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="form_action" value="delete">
                    <input type="hidden" name="delete_id" value="<?= (int)($r['partyid'] ?? 0) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
