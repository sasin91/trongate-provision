<?php
/**
 * View: scripts/certbot_script
 *
 * Renders the on-demand Let's Encrypt / certbot setup script.
 *
 * @var object $server
 */

$sq = static fn(string $value): string => "'" . str_replace("'", "'\\''", $value) . "'";
$domain = trim((string) ($server->domain ?? ""));
$email = trim((string) ($server->customer_email ?? ""));
$webroot = trim((string) ($server->web_root ?: "/var/www/html"));
?>
#!/bin/bash
set -euo pipefail

DOMAIN=<?= $sq($domain) ?>
CERTBOT_EMAIL=<?= $sq($email) ?>
LIVE_LINK=<?= $sq($webroot) ?>

export DEBIAN_FRONTEND=noninteractive
apt-get update -q
apt-get install -y -q certbot python3-certbot-apache

cat > "/etc/apache2/sites-available/${DOMAIN}.conf" << VHOSTEOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${LIVE_LINK}
    <Directory ${LIVE_LINK}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
VHOSTEOF

a2ensite "$DOMAIN"
a2dissite 000-default 2>/dev/null || true
systemctl reload apache2

certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$CERTBOT_EMAIL" --redirect

echo "SSL enabled for ${DOMAIN}"
