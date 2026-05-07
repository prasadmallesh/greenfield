<?php /** @var string $base */ /** @var string $msg */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body{background:#f4f6f4;}
        .login-wrap{max-width:380px;margin:3rem auto;}
        .brand{color:#0c6932;font-weight:600;}
    </style>
</head>
<body>
<div class="login-wrap">
    <p class="text-center brand">Green Farm — staff</p>
    <h1 class="h4 text-center mb-3">Login</h1>
    <?php if ($msg !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <form method="post" action="">
        <div class="form-group">
            <input type="text" name="username" class="form-control" placeholder="User name" maxlength="20" required>
        </div>
        <div class="form-group">
            <input type="password" name="pwd" class="form-control" placeholder="Password" maxlength="20" required>
        </div>
        <div class="form-group">
            <img src="<?= htmlspecialchars($base . '/admin/captcha', ENT_QUOTES, 'UTF-8') ?>" alt="captcha" class="mb-1"><br>
            <input type="text" name="captchacode" class="form-control" placeholder="Security code" maxlength="4" required>
        </div>
        <button type="submit" class="btn btn-block text-white" style="background:#0c6932;">Submit</button>
    </form>
    <p class="text-center mt-3 small"><a href="<?= htmlspecialchars($base . '/', ENT_QUOTES, 'UTF-8') ?>">Home</a></p>
</div>
</body>
</html>
