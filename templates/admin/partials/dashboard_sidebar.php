<?php
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
/** @var array{productCount:int,partyCount:int} $adminSidebarCounts */
$ut = (string) ($_SESSION['login_user_type'] ?? '');
$isAdmin = $ut === 'A';
$pc = (int) ($adminSidebarCounts['productCount'] ?? 0);
$par = (int) ($adminSidebarCounts['partyCount'] ?? 0);
?>
<nav class="list-group list-group-flush small mb-3" aria-label="Dashboard shortcuts">
    <?php if ($isAdmin || $menu->isActiveAtIndex(25)): ?>
        <a class="list-group-item list-group-item-action py-2" href="<?= htmlspecialchars($base . '/admin', ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
    <?php endif; ?>
    <?php if ($isAdmin || $menu->isActiveAtIndex(1)): ?>
        <a class="list-group-item list-group-item-action py-2 d-flex justify-content-between align-items-center" href="<?= htmlspecialchars($base . '/admin/masters/products', ENT_QUOTES, 'UTF-8') ?>">
            <span>Total product</span><span class="badge badge-warning"><?= $pc ?></span>
        </a>
    <?php endif; ?>
    <?php if ($isAdmin || $menu->isActiveAtIndex(2)): ?>
        <a class="list-group-item list-group-item-action py-2 d-flex justify-content-between align-items-center" href="<?= htmlspecialchars($base . '/admin/masters/parties', ENT_QUOTES, 'UTF-8') ?>">
            <span>Total account / party</span><span class="badge badge-info"><?= $par ?></span>
        </a>
    <?php endif; ?>
    <?php if ($isAdmin || $menu->isActiveAtIndex(15)): ?>
        <a class="list-group-item list-group-item-action py-2" href="<?= htmlspecialchars($base . '/admin/preorders', ENT_QUOTES, 'UTF-8') ?>">Create invoice <span class="text-muted">(pre-orders)</span></a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
        <a class="list-group-item list-group-item-action py-2" href="<?= htmlspecialchars($base . '/admin/bill-settlement', ENT_QUOTES, 'UTF-8') ?>">Invoice bill settlement</a>
    <?php endif; ?>
    <a class="list-group-item list-group-item-action py-2" href="<?= htmlspecialchars($base . '/admin/last-invoice-details', ENT_QUOTES, 'UTF-8') ?>">View last invoice details</a>
    <?php if ($menu->isActiveAtIndex(8)): ?>
        <a class="list-group-item list-group-item-action py-2" href="<?= htmlspecialchars($base . '/admin/calendar', ENT_QUOTES, 'UTF-8') ?>">Calendar</a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
        <a class="list-group-item list-group-item-action py-2" href="<?= htmlspecialchars($base . '/admin/revert-paid-invoices', ENT_QUOTES, 'UTF-8') ?>">Revert paid invoices</a>
    <?php endif; ?>
    <?php if ($menu->isActiveAtIndex(26)): ?>
        <a class="list-group-item list-group-item-action py-2" href="<?= htmlspecialchars($base . '/admin/qurbani-requests', ENT_QUOTES, 'UTF-8') ?>">Qurbani request info</a>
    <?php endif; ?>
</nav>
