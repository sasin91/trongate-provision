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
set -Eeuo pipefail

DOMAIN=<?= $sq($domain) ?>
CERTBOT_EMAIL=<?= $sq($email) ?>
LIVE_LINK=<?= $sq($webroot) ?>

trap 'status=$?; echo "SSL setup failed at script line ${LINENO} with exit code ${status}."; exit $status' ERR

run_as_root() {
    if [ "$(id -u)" -eq 0 ]; then
        "$@"
        return
    fi

    if command -v sudo >/dev/null 2>&1; then
        if sudo -n "$@"; then
            return
        fi

        echo "SUDO_DENIED_COMMAND=$*"
        exit 13
    fi

    echo "SSL setup requires root privileges. Connect as root or configure passwordless sudo for $(whoami)."
    exit 13
}

show_sudo_context() {
    echo "Remote user: $(whoami)"
    if [ "$(id -u)" -eq 0 ]; then
        echo "Running as root; sudo is not needed."
        return
    fi

    if ! command -v sudo >/dev/null 2>&1; then
        echo "sudo is not installed."
        return
    fi

    echo "Passwordless sudo check:"
    sudo -n -l 2>&1 || true
}

wait_for_apt() {
  local lock_seen=0

  for _ in $(seq 1 30); do
    lock_seen=0

    for lock in \
      /var/lib/dpkg/lock-frontend \
      /var/lib/dpkg/lock \
      /var/lib/apt/lists/lock \
      /var/cache/apt/archives/lock
    do
      if command -v fuser >/dev/null 2>&1; then
        if fuser "$lock" >/dev/null 2>&1; then
          lock_seen=1
        fi
      fi
    done

    if [ "$lock_seen" -eq 0 ]; then
      return
    fi

    echo "Waiting for another apt/dpkg process to finish..."
    sleep 10
  done

  echo "Timed out waiting for apt/dpkg locks to clear."
  exit 75
}

export DEBIAN_FRONTEND=noninteractive
echo "Preparing certbot installation for ${DOMAIN}..."
wait_for_apt
echo "Updating apt package lists..."
run_as_root apt-get -o DPkg::Lock::Timeout=180 update -q
show_sudo_context
echo "Installing certbot and Apache plugin..."
run_as_root apt-get -o DPkg::Lock::Timeout=180 install -y -q certbot python3-certbot-apache

echo "Writing Apache virtual host for ${DOMAIN}..."
cat << VHOSTEOF | run_as_root tee "/etc/apache2/sites-available/${DOMAIN}.conf" >/dev/null
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

echo "Enabling Apache site for ${DOMAIN}..."
run_as_root a2ensite "$DOMAIN"
run_as_root a2dissite 000-default 2>/dev/null || true
run_as_root systemctl reload apache2

echo "Requesting Let's Encrypt certificate for ${DOMAIN}..."
set +e
run_as_root certbot --apache -d "$DOMAIN" --non-interactive --agree-tos -m "$CERTBOT_EMAIL" --redirect
status=$?
set -e
if [ "$status" -ne 0 ]; then
    echo "Certbot exited with status ${status}."
    echo "Recent Let's Encrypt log:"
    run_as_root tail -n 80 /var/log/letsencrypt/letsencrypt.log 2>/dev/null || true
    exit "$status"
fi

echo "SSL enabled for ${DOMAIN}"
