<?php
/** @var string $base */
/** @var \App\Services\MenuPermissionService $menu */
$ut = (string) ($_SESSION['login_user_type'] ?? '');
$isAdmin = $ut === 'A';
$payin = (int) ($_SESSION['login_payin'] ?? 0);
$payout = (int) ($_SESSION['login_payout'] ?? 0);
?>
<nav class="navbar navbar-expand-lg navbar-dark mb-3" style="background:#0c6932;">
    <a class="navbar-brand" href="<?= htmlspecialchars($base . '/admin', ENT_QUOTES, 'UTF-8') ?>">GFM</a>
    <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#gfNav">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="gfNav">
        <ul class="navbar-nav mr-auto">
            <?php if ($isAdmin || $menu->isActiveAtIndex(9)): ?>
                <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base . '/admin/preorders', ENT_QUOTES, 'UTF-8') ?>">View Pre Order(s)</a></li>
            <?php endif; ?>
            <?php if ($isAdmin || $menu->isActiveAtIndex(8)): ?>
                <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base . '/admin/calendar', ENT_QUOTES, 'UTF-8') ?>">Calendar</a></li>
            <?php endif; ?>
            <?php if ($isAdmin || $menu->isActiveAtIndex(25)): ?>
                <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base . '/admin', ENT_QUOTES, 'UTF-8') ?>">Dashboard</a></li>
            <?php endif; ?>

            <?php
            $mastersAny = $menu->isActiveAtIndex(0) || $menu->isActiveAtIndex(1) || $menu->isActiveAtIndex(14)
                || $menu->isActiveAtIndex(2) || $menu->isActiveAtIndex(3) || $menu->isActiveAtIndex(4)
                || $menu->isActiveAtIndex(5) || $menu->isActiveAtIndex(30);
            ?>
            <?php if ($isAdmin || $mastersAny): ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">Masters</a>
                <div class="dropdown-menu">
                    <?php if ($isAdmin || $menu->isActiveAtIndex(0)): ?><a class="dropdown-item" href="<?= htmlspecialchars($base . '/admin/masters/categories', ENT_QUOTES, 'UTF-8') ?>">Category Master</a><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(1)): ?><a class="dropdown-item" href="<?= htmlspecialchars($base . '/admin/masters/products', ENT_QUOTES, 'UTF-8') ?>">Product Master</a><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(14)): ?><a class="dropdown-item" href="<?= htmlspecialchars($base . '/admin/masters/units', ENT_QUOTES, 'UTF-8') ?>">Unit Master</a><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(2)): ?><a class="dropdown-item" href="<?= htmlspecialchars($base . '/admin/masters/parties', ENT_QUOTES, 'UTF-8') ?>">Account / Party Master</a><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(3)): ?><span class="dropdown-item text-muted">Supplier Master (soon)</span><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(4)): ?><span class="dropdown-item text-muted">Customer Master (soon)</span><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(5)): ?><a class="dropdown-item" href="<?= htmlspecialchars($base . '/admin/masters/tax', ENT_QUOTES, 'UTF-8') ?>">Tax Master</a><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(30)): ?><a class="dropdown-item" href="<?= htmlspecialchars($base . '/admin/masters/fuel', ENT_QUOTES, 'UTF-8') ?>">Fuel Master</a><?php endif; ?>
                </div>
            </li>
            <?php endif; ?>

            <?php
            $invAny = $menu->isActiveAtIndex(6) || $menu->isActiveAtIndex(7) || $menu->isActiveAtIndex(15);
            ?>
            <?php if ($isAdmin || $invAny): ?>
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">Invoice</a>
                <div class="dropdown-menu">
                    <?php if ($isAdmin || $menu->isActiveAtIndex(6)): ?><span class="dropdown-item text-muted">Previous Balance (soon)</span><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(7)): ?><span class="dropdown-item text-muted">Party Credit (soon)</span><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(15)): ?><span class="dropdown-item text-muted">Create Invoice (soon)</span><?php endif; ?>
                    <?php if ($isAdmin || $menu->isActiveAtIndex(6) || $menu->isActiveAtIndex(7) || $menu->isActiveAtIndex(15)): ?>
                        <a class="dropdown-item" href="<?= htmlspecialchars($base . '/admin/bill-settlement', ENT_QUOTES, 'UTF-8') ?>">Bill settlement</a>
                    <?php endif; ?>
                </div>
            </li>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <li class="nav-item"><span class="nav-link disabled text-white-50">Pay In (soon)</span></li>
                <li class="nav-item"><span class="nav-link disabled text-white-50">Pay Out (soon)</span></li>
            <?php else: ?>
                <?php if ($payin === 1): ?><li class="nav-item"><span class="nav-link disabled text-white-50">Pay In (soon)</span></li><?php endif; ?>
                <?php if ($payout === 1): ?><li class="nav-item"><span class="nav-link disabled text-white-50">Pay Out (soon)</span></li><?php endif; ?>
            <?php endif; ?>

            <?php
            $rptAny = $menu->isActiveAtIndex(16) || $menu->isActiveAtIndex(17) || $menu->isActiveAtIndex(18)
                || $menu->isActiveAtIndex(19) || $menu->isActiveAtIndex(20) || $menu->isActiveAtIndex(21)
                || $menu->isActiveAtIndex(22) || $menu->isActiveAtIndex(23) || $menu->isActiveAtIndex(24)
                || $menu->isActiveAtIndex(28) || $menu->isActiveAtIndex(29);
            ?>
            <?php if ($isAdmin || $rptAny): ?>
                <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars($base . '/admin/reports', ENT_QUOTES, 'UTF-8') ?>">Reports</a></li>
            <?php endif; ?>
            <?php if ($isAdmin || $menu->isActiveAtIndex(30)): ?>
                <li class="nav-item"><span class="nav-link disabled text-white-50">Notes (soon)</span></li>
            <?php endif; ?>
        </ul>
        <ul class="navbar-nav">
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                    <?= htmlspecialchars((string) ($_SESSION['login_user'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?>
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <?php if ($isAdmin): ?>
                        <a class="dropdown-item" href="<?= htmlspecialchars($base . '/admin/page-manager', ENT_QUOTES, 'UTF-8') ?>">Page Manager</a>
                    <?php endif; ?>
                    <a class="dropdown-item" href="<?= htmlspecialchars($base . '/admin/logout', ENT_QUOTES, 'UTF-8') ?>">Logout</a>
                </div>
            </li>
        </ul>
    </div>
</nav>
