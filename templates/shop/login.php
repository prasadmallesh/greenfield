<?php
$title = 'Customer login';
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h5 text-center">Log in with your account</h1>
                <?php if (($msg ?? '') !== ''): ?>
                    <div class="alert alert-danger py-1 small"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <div class="form-group">
                        <label>Mobile No.</label>
                        <input type="text" name="wmob" class="form-control" maxlength="10" pattern="[0-9]*" inputmode="numeric" required>
                        <small class="text-muted">Do not include +61 or space</small>
                    </div>
                    <div class="form-group">
                        <img src="<?= htmlspecialchars($base . '/shop/captcha', ENT_QUOTES, 'UTF-8') ?>" alt="captcha" class="mb-1"><br>
                        <input type="text" name="captchacode" class="form-control" maxlength="4" required placeholder="Security code">
                    </div>
                    <button class="btn btn-block text-white" style="background:#0c6932;">Log in</button>
                </form>
                <p class="text-center mt-3 small mb-0"><a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>">Home</a></p>
            </div>
        </div>
    </div>
</div>
<?php
$body = ob_get_clean();
include __DIR__ . '/layout.php';
