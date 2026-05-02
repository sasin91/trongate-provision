<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provision — Simple PHP Deployments</title>
    <link rel="stylesheet" href="welcome_module/css/welcome.css">
</head>
<body>

<section class="hero">
    <h1>Provision</h1>
    <p>Self-hosted server provisioning and zero-downtime deployments for PHP.
    SSH in, LAMP up, deploy — no agents, no YAML, no noise.</p>
    <div class="hero-actions">
        <a href="customer/register" class="btn-primary-hero">Get started</a>
        <a href="customer/login" class="btn-ghost">Sign in</a>
    </div>
</section>

<section class="timeline-section">
    <h2>How it works</h2>
    <div class="timeline">

        <div class="step">
            <div class="step-marker">1</div>
            <h3>Add your SSH key</h3>
            <p>Paste your public key once. Provision embeds it into every
            setup script — <code>ssh root@server</code> works the moment
            provisioning finishes.</p>
        </div>

        <div class="step">
            <div class="step-marker">2</div>
            <h3>Define an environment</h3>
            <p>Set the PHP version, domain, database name, and encrypted
            environment variables (<code>DB_PASSWORD</code>,
            <code>STRIPE_KEY</code>, &hellip;). One environment maps to
            one server.</p>
        </div>

        <div class="step">
            <div class="step-marker">3</div>
            <h3>Add a server</h3>
            <p>Enter a VPS IP — Provision installs Apache, PHP, and MariaDB
            over SSH. Or choose a region and server type and let Provision
            create a Hetzner Cloud VM in seconds.</p>
        </div>

        <div class="step">
            <div class="step-marker">4</div>
            <h3>Create a deployment</h3>
            <p>Point to a Git repository and branch. Provision pulls the
            archive from GitHub or GitLab and transfers it to your server.
            Or upload a <code>.zip</code> directly.</p>
        </div>

        <div class="step">
            <div class="step-marker">5</div>
            <h3>Stage, review, promote</h3>
            <p>The release lands in <code>/var/www/releases/</code> before
            going live. Inspect SQL files, remove the ones you
            don&rsquo;t want, then promote with a zero-downtime
            symlink swap.</p>
        </div>

        <div class="step">
            <div class="step-marker">6</div>
            <h3>Roll back instantly</h3>
            <p>The previous release stays on disk. One click reverts the
            web root — no re-deploy, no waiting.</p>
        </div>

    </div>
</section>

<div class="pillars">
    <div class="pillar">
        <h4>No agents</h4>
        <p>Everything runs over plain SSH. Nothing installed permanently on your servers.</p>
    </div>
    <div class="pillar">
        <h4>Encrypted secrets</h4>
        <p>Environment variables are encrypted at rest and injected at deploy time.</p>
    </div>
    <div class="pillar">
        <h4>Hetzner integration</h4>
        <p>Spin up VMs in seconds using your Hetzner API token.</p>
    </div>
    <div class="pillar">
        <h4>Git or ZIP</h4>
        <p>Deploy from GitHub/GitLab or upload an artifact. Both use the same staged release flow.</p>
    </div>
</div>

<footer>
    Built with PHP &middot; <a href="customer/register">Create account</a>
</footer>

</body>
</html>
