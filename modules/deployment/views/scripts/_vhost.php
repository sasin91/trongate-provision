<?php
/**
 * View: scripts/_vhost
 *
 * Emits the bash block that writes (and enables) an Apache virtual host
 * for the configured domain. Included from `deploy_script.php` — inherits
 * its scope.
 *
 * NOTE: A `?>` tag immediately followed by `\n` causes PHP to strip that
 * newline. Lines that end with `<?= $foo ?>` therefore need a *trailing
 * blank line* below them in this template to produce a single newline in
 * the rendered output. When `?>` is followed by other literal text (e.g.
 * `>`), no stripping happens, so no extra blank line is needed there.
 *
 * Required vars from parent scope:
 *   @var string $domain  Fully-qualified domain name.
 *   @var string $webroot Document root on the target server.
 */
?>
echo "==> Configuring virtual host for <?= $domain ?>..."
sudo tee /etc/apache2/sites-available/<?= $domain ?>.conf << 'VHOSTEOF'
<VirtualHost *:80>
    ServerName <?= $domain ?>

    DocumentRoot <?= $webroot ?>

    <Directory <?= $webroot ?>>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/<?= $domain ?>-error.log

    CustomLog ${APACHE_LOG_DIR}/<?= $domain ?>-access.log combined
</VirtualHost>
VHOSTEOF
sudo a2ensite <?= $domain ?>

sudo a2dissite 000-default 2>/dev/null || true
