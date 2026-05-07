<?php /** @var string $base */ /** @var string $title */ /** @var string $body */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        .gf-bar{background:#0c6932;color:#fff;padding:.75rem 0;margin-bottom:1rem;}
        .gf-bar a{color:#fff;text-decoration:underline;}
    </style>
</head>
<body class="bg-light">
<div class="gf-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <strong>GFM NSW Halal</strong>
        <span>
            <?php if (isset($_SESSION['customer_partyid']) && (int) $_SESSION['customer_partyid'] > 0): ?>
                <?= htmlspecialchars((string)($_SESSION['customer_partynm'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                &nbsp;|&nbsp;
                <a href="<?= htmlspecialchars($base . '/shop/cart', ENT_QUOTES, 'UTF-8') ?>">Cart</a>
                &nbsp;|&nbsp;
                <a href="<?= htmlspecialchars($base . '/shop/menu', ENT_QUOTES, 'UTF-8') ?>">Menu</a>
                &nbsp;|&nbsp;
                <a href="<?= htmlspecialchars($base . '/shop/logout', ENT_QUOTES, 'UTF-8') ?>">Logout</a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>">Home</a>
            <?php endif; ?>
        </span>
    </div>
</div>
<div class="container pb-5">
    <?= $body ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
