<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="templates_module/css/app.css">
    <title>Sign in — Provision</title>
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

    <div class="auth-heading">Sign in</div>
    <p class="auth-sub">LAMP provisioning made simple.</p>

    <form method="post" action="<?= $form_location ?>">
        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars(post('email', true) ?: '') ?>" required autofocus>
        </div>
        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:.5rem">
            <input type="checkbox" name="remember" id="remember" value="1">
            <label for="remember" style="font-size:.85rem;color:#64748b;cursor:pointer">Remember me for 30 days</label>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center">Sign in</button>
        <?= form_close() ?>

    <p style="margin-top:1.25rem;text-align:center;font-size:.85rem;color:#64748b">
        No account? <a href="customer/register" style="color:#6366f1">Create one</a>
    </p>
</div>

</body>
</html>
