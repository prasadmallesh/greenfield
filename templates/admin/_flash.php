<?php
/** @var string $msg */
/** @var string $alert */
?>
<?php if ($msg !== ''): ?>
    <div class="alert <?= ($alert === 'err') ? 'alert-danger' : 'alert-success' ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
