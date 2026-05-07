<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Green Farm — portal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body { background: #f4f6f4; }
        .gf-header { background: #0c6932; color: #fff; padding: 1rem 0; margin-bottom: 2rem; }
        .card-portal { border: none; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .btn-gf { background: #0c6932; color: #fff; }
        .btn-gf:hover { color: #fff; background: #094d25; }
    </style>
</head>
<body>
<div class="gf-header">
    <div class="container"><h1 class="h4 mb-0">Green Farm Meat — ordering portal</h1></div>
</div>
<div class="container pb-5">
    <p class="text-muted">Choose the same area you use today: staff back-office or customer ordering.</p>
    <div class="row">
        <div class="col-md-6 mb-3">
            <div class="card card-portal h-100">
                <div class="card-body">
                    <h2 class="h5">Staff</h2>
                    <p class="small text-muted">Pre-orders, page permissions, masters (read-only), PDFs.</p>
                    <a class="btn btn-gf" href="<?= htmlspecialchars($base . '/admin/login', ENT_QUOTES, 'UTF-8') ?>">Staff login</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card card-portal h-100">
                <div class="card-body">
                    <h2 class="h5">Customer ordering</h2>
                    <p class="small text-muted">Mobile number login (same as legacy customer portal).</p>
                    <a class="btn btn-gf" href="<?= htmlspecialchars($base . '/shop', ENT_QUOTES, 'UTF-8') ?>">Customer login</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
