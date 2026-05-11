<?php
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
/** @var string $title */
/** @var string $contentHtml */
/** @var array{productCount:int,partyCount:int}|null $adminSidebarCounts */
if (!isset($adminSidebarCounts) || !is_array($adminSidebarCounts)) {
    $adminSidebarCounts = ['productCount' => 0, 'partyCount' => 0];
}
$showDashSidebar = isset($menu) && $menu instanceof \App\Services\MenuPermissionService;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>body{background:#f4f6f4;} .gf-main{background:#fff;padding:1.25rem;border-radius:4px;}</style>
</head>
<body class="pb-5">
<div class="container-fluid">
    <?php include __DIR__ . '/partials/nav.php'; ?>
    <main class="container">
        <?php if ($showDashSidebar): ?>
            <div class="row">
                <aside class="col-lg-3 mb-3">
                    <?php include __DIR__ . '/partials/dashboard_sidebar.php'; ?>
                </aside>
                <div class="col-lg-9 gf-main">
                    <?= $contentHtml ?>
                </div>
            </div>
        <?php else: ?>
            <div class="gf-main">
                <?= $contentHtml ?>
            </div>
        <?php endif; ?>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
