<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="templates_module/css/app.css">
    <title><?= htmlspecialchars($page_title ?? 'Provision') ?></title>
    <?= $additional_includes_top ?? '' ?>
</head>
<body>

<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-brand">Provi<em>sion</em></div>

        <nav class="sidebar-nav">
            <div class="sidebar-section">Main</div>
            <a href="customer" class="<?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', str_replace('http://localhost', '', BASE_URL) . 'customer') && !str_contains($_SERVER['REQUEST_URI'] ?? '', 'logout') ? 'active' : '' ?>">
                &#9632; Dashboard
            </a>

            <div class="sidebar-section">Infrastructure</div>
            <a href="environment" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/environment') ? 'active' : '' ?>">
                &#9670; Environments
            </a>
            <a href="server" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/server') ? 'active' : '' ?>">
                &#9646; Servers
            </a>
            <a href="deployment" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/deployment') ? 'active' : '' ?>">
                &#10148; Deployments
            </a>
            <a href="provider" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/provider') ? 'active' : '' ?>">
                &#9729; Providers
            </a>
            <a href="event/feed" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/event') ? 'active' : '' ?>">
                &#128203; Activity
            </a>
        </nav>

        <div class="sidebar-footer">
            <?php if (!empty($current_email)): ?>
                <span class="email"><?= htmlspecialchars($current_email) ?></span>
            <?php endif; ?>
            <a href="customer/logout">Sign out</a>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <span class="topbar-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></span>
            <div class="topbar-actions">
                <?php if (!empty($topbar_actions)): ?>
                    <?= $topbar_actions ?>
                <?php endif; ?>
            </div>
        </header>

        <div class="content">
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <?= display($data) ?>
        </div>
    </div>
</div>

<script src="js/trongate-mx.js"></script>
<script src="js/provision.js"></script>
<?= $additional_includes_btm ?? '' ?>
</body>
</html>
