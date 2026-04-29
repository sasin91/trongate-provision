<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="templates_module/css/app.css">
    <title>Create account — Provision</title>
</head>
<body class="auth-page">

<div class="auth-card">
    <div class="auth-brand">Provi<em>sion</em></div>

    <?php if (!empty($_SESSION['form_submission_errors'])): ?>
        <div class="alert alert-danger">
            <?php foreach ($_SESSION['form_submission_errors'] as $errs): ?>
                <?php foreach ((array) $errs as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            <?php endforeach; unset($_SESSION['form_submission_errors']); ?>
        </div>
    <?php endif; ?>

    <div class="auth-heading">Create account</div>
    <p class="auth-sub">Start provisioning LAMP servers in minutes.</p>

    <form method="post" action="<?= $form_location ?>">
        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars(post('email', true) ?: '') ?>" required autofocus>
        </div>
        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
            <span class="form-hint">Minimum 6 characters</span>
        </div>
        <div class="form-group">
            <label class="form-label">Repeat password</label>
            <input type="password" name="password_confirm" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Create account</button>
        <?= form_close() ?>

    <p style="margin-top:1.25rem;text-align:center;font-size:.85rem;color:#64748b">
        Already have an account? <a href="customer/login" style="color:#6366f1">Sign in</a>
    </p>
</div>

</body>
</html>
