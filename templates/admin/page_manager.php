<?php
$title = 'Page Manager';
/** @var list<array<string,mixed>> $rows */
/** @var string $msg */
ob_start();
?>
<h1 class="h4">Page Manager</h1>
<?php if ($msg !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<p class="text-muted small">Same data as legacy <code>page_settings_data</code>. Checked pages are visible in the menu for non-admin users where applicable.</p>
<form method="post" action="">
    <div class="row">
        <?php foreach ($rows as $idx => $row): ?>
            <div class="col-md-3 mb-2">
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input chk-ps" id="chk<?= (int)$row['psid'] ?>" data-psid="<?= (int)$row['psid'] ?>"
                        <?= ((int)$row['is_active'] === 1) ? 'checked' : '' ?>>
                    <label class="custom-control-label" for="chk<?= (int)$row['psid'] ?>"><?= htmlspecialchars((string)$row['psname'], ENT_QUOTES, 'UTF-8') ?></label>
                </div>
                <input type="hidden" name="ps[<?= (int)$row['psid'] ?>]" id="ps<?= (int)$row['psid'] ?>" value="<?= ((int)$row['is_active'] === 1) ? 'Y' : 'N' ?>">
            </div>
        <?php endforeach; ?>
    </div>
    <button type="submit" name="btnsubmit" value="Update" class="btn text-white mt-2" style="background:#0c6932;">Update</button>
</form>
<script>
document.querySelectorAll('.chk-ps').forEach(function (el) {
    el.addEventListener('change', function () {
        var id = el.getAttribute('data-psid');
        document.getElementById('ps' + id).value = el.checked ? 'Y' : 'N';
    });
});
</script>
<?php
$contentHtml = ob_get_clean();
include __DIR__ . '/layout_shell.php';
