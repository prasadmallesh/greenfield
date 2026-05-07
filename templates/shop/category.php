<?php
$title = 'Products';
ob_start();
?>
<h1 class="h5 mb-3">Products</h1>
<?php foreach ($products as $pr): ?>
    <?php
    $pid = (int) ($pr['pid'] ?? 0);
    $price = (float) ($pr['price'] ?? 0);
    $pname = (string) ($pr['pname'] ?? '');
    $unit = (string) ($pr['punit'] ?? 'KG');
    if ($price <= 0) { continue; }
    $pval = $unit . '~' . $price;
    ?>
    <div class="card mb-2">
        <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <strong><?= htmlspecialchars($pname, ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="text-muted small"><?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <form method="post" action="<?= htmlspecialchars($base . '/shop/cart/add', ENT_QUOTES, 'UTF-8') ?>" class="form-inline m-0">
                <input type="hidden" name="pid" value="<?= $pid ?>">
                <input type="hidden" name="punit" value="<?= htmlspecialchars($unit, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="rate" value="<?= htmlspecialchars((string)$price, ENT_QUOTES, 'UTF-8') ?>">
                <input type="number" name="qty" class="form-control form-control-sm mr-1" style="width:5rem" min="0" step="0.01" value="0">
                <button type="submit" class="btn btn-sm text-white" style="background:#0c6932;">Add</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
<p><a href="<?= htmlspecialchars($base . '/shop/menu', ENT_QUOTES, 'UTF-8') ?>">&larr; Categories</a></p>
<?php
$body = ob_get_clean();
include __DIR__ . '/layout.php';
