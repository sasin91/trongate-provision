#!/bin/bash
set -euo pipefail

PROVISION_USER="${PROVISION_USER:-provision}"
RELEASES_DIR="${RELEASES_DIR:-/var/www/releases}"
LIVE_LINK="${LIVE_LINK:-/var/www/html}"
DOMAIN="${DOMAIN:-}"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASSWORD="${DB_PASSWORD:-}"

# ── LAMP stack (MariaDB) ──────────────────────────────────────────
export DEBIAN_FRONTEND=noninteractive
apt-get update -q
PACKAGES="apache2 php libapache2-mod-php php-mysql php-curl php-mbstring php-xml php-zip mariadb-server unzip"
apt-get install -y -q $PACKAGES

# ── Dedicated deploy user ─────────────────────────────────────────
useradd -m -s /bin/bash "$PROVISION_USER" 2>/dev/null || true
mkdir -p "/home/$PROVISION_USER/.ssh"
cp /root/.ssh/authorized_keys "/home/$PROVISION_USER/.ssh/authorized_keys" 2>/dev/null || true
chown -R "$PROVISION_USER:$PROVISION_USER" "/home/$PROVISION_USER/.ssh"
chmod 700 "/home/$PROVISION_USER/.ssh"
chmod 600 "/home/$PROVISION_USER/.ssh/authorized_keys" 2>/dev/null || true

cat > "/etc/sudoers.d/$PROVISION_USER" << SUDOEOF
$PROVISION_USER ALL=(ALL) NOPASSWD: /usr/bin/systemctl reload apache2
$PROVISION_USER ALL=(ALL) NOPASSWD: /usr/bin/systemctl restart apache2
$PROVISION_USER ALL=(ALL) NOPASSWD: /usr/bin/tee /etc/apache2/sites-available/*.conf
$PROVISION_USER ALL=(ALL) NOPASSWD: /usr/sbin/a2ensite *
$PROVISION_USER ALL=(ALL) NOPASSWD: /usr/sbin/a2dissite 000-default
SUDOEOF
chmod 440 "/etc/sudoers.d/$PROVISION_USER"

# ── Apache ────────────────────────────────────────────────────────
a2enmod rewrite
sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf
chown "$PROVISION_USER":www-data /var/www
chmod 2775 /var/www
mkdir -p "$RELEASES_DIR"
chmod 2775 "$RELEASES_DIR"
# Replace Apache's default html dir so provision user controls the web root
rm -rf /var/www/html
mkdir -p /var/www/html
chown "$PROVISION_USER":www-data /var/www/html
chmod 2775 /var/www/html
systemctl enable --now apache2 mariadb

# ── MariaDB ───────────────────────────────────────────────────────
if [ -n "$DB_NAME" ]; then
    SQL_TMP=$(mktemp)
    printf "CREATE DATABASE IF NOT EXISTS \`%s\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n" \
        "$DB_NAME" >> "$SQL_TMP"
    if [ -n "$DB_USER" ] && [ -n "$DB_PASSWORD" ]; then
        DB_USER_ESC="${DB_USER//\'/\'\'}"
        DB_PASS_ESC="${DB_PASSWORD//\'/\'\'}"
        printf "CREATE USER IF NOT EXISTS '%s'@'localhost' IDENTIFIED BY '%s';\n" \
            "$DB_USER_ESC" "$DB_PASS_ESC" >> "$SQL_TMP"
        printf "GRANT ALL PRIVILEGES ON \`%s\`.* TO '%s'@'localhost';\n" \
            "$DB_NAME" "$DB_USER_ESC" >> "$SQL_TMP"
        printf "GRANT CREATE ON *.* TO '%s'@'localhost';\n" \
            "$DB_USER_ESC" >> "$SQL_TMP"
        printf "FLUSH PRIVILEGES;\n" >> "$SQL_TMP"
    fi
    mysql -u root < "$SQL_TMP"
    rm -f "$SQL_TMP"

    if [ -n "$DB_USER" ] && [ -n "$DB_PASSWORD" ]; then
        cat > "/home/$PROVISION_USER/.my.cnf" << MYCNFEOF
[client]
user=$DB_USER
password=$DB_PASSWORD
host=localhost
MYCNFEOF
        chmod 600 "/home/$PROVISION_USER/.my.cnf"
        chown "$PROVISION_USER:$PROVISION_USER" "/home/$PROVISION_USER/.my.cnf"
    fi
fi

# ── Apache vhost ──────────────────────────────────────────────────
if [ -n "$DOMAIN" ]; then
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
fi

echo "Provisioned. User: $PROVISION_USER"
