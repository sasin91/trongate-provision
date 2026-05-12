<?php
/**
 * View: scripts/lamp_script
 *
 * Renders the editable LAMP setup bash script for a server.
 *
 * @var object $server
 */

$ver = $server->php_version;
$ip = $server->ip_address;
$name = $server->name;
$date = date("Y-m-d H:i:s");
?>
#!/bin/bash
# ────────────────────────────────────────────────────────────────
# Provision — LAMP Setup Script
# Server : <?= $name ?> (<?= $ip ?>)
# PHP    : <?= $ver ?>
# Generated: <?= $date ?>
# ────────────────────────────────────────────────────────────────
# Run as root on your Ubuntu 22.04 / 24.04 server:
#   bash lamp-setup.sh
# ────────────────────────────────────────────────────────────────

set -euo pipefail

PHP_VERSION="<?= $ver ?>"
DEBIAN_FRONTEND=noninteractive

echo "==> Updating packages..."
apt-get update -y
apt-get upgrade -y

# ── Apache ──────────────────────────────────────────────────────
echo "==> Installing Apache..."
apt-get install -y apache2
a2enmod rewrite headers ssl
systemctl enable apache2

# ── PHP ─────────────────────────────────────────────────────────
echo "==> Installing PHP $PHP_VERSION..."
apt-get install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt-get update -y

apt-get install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-cli \
    php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-gd \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-intl \
    php${PHP_VERSION}-bcmath \
    libapache2-mod-php${PHP_VERSION}

a2enmod php${PHP_VERSION}
systemctl enable php${PHP_VERSION}-fpm

# ── MariaDB ──────────────────────────────────────────────────────
echo "==> Installing MariaDB..."
apt-get install -y mariadb-server
systemctl enable mariadb

mysql -e "DELETE FROM mysql.user WHERE User='';" 2>/dev/null || true
mysql -e "DROP DATABASE IF EXISTS test;" 2>/dev/null || true
mysql -e "FLUSH PRIVILEGES;" 2>/dev/null || true

# ── Git ──────────────────────────────────────────────────────────
apt-get install -y git curl unzip

# ── Web root ─────────────────────────────────────────────────────
mkdir -p /var/www/html
chown -R www-data:www-data /var/www

# ── Firewall ─────────────────────────────────────────────────────
if command -v ufw &>/dev/null; then
    ufw allow OpenSSH
    ufw allow 'Apache Full'
    ufw --force enable
fi

# ── Summary ──────────────────────────────────────────────────────
systemctl restart apache2 mariadb

echo ""
echo "╔════════════════════════════════════╗"
echo "║   LAMP setup complete!             ║"
echo "╚════════════════════════════════════╝"
echo "  Apache : $(apache2 -v 2>&1 | head -1)"
echo "  PHP    : $(php -v | head -1)"
echo "  MariaDB: $(mysql --version)"
echo ""
echo "  Next: create a deployment in Provision and"
echo "        run the deployment script to clone your app."
