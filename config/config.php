<?php
define('BASE_URL', 'http://localhost/provision/');
define('ENV', 'dev');
define('DEFAULT_MODULE', 'welcome');
define('DEFAULT_METHOD', 'index');
define('MODULE_ASSETS_TRIGGER', '_module');
define('ERROR_404', 'templates/error_404');
define('INTERCEPTORS', ['customer' => 'intercept']);

// AES-256-CBC key for encrypting environment variables (32 printable chars)
define('ENCRYPTION_KEY', getenv('PROVISION_ENCRYPTION_KEY') ?: 'ch4ng3-th1s-k3y-b3f0r3-pr0duct10n!!');

// Ed25519 key pair used to SSH into target servers for provisioning and deployment.
// Copy the private key: sudo cp ~/.ssh/id_ed25519 /var/www/.ssh/id_ed25519
//                       sudo chown www-data:www-data /var/www/.ssh/id_ed25519 && sudo chmod 400 /var/www/.ssh/id_ed25519
// Add the public key to authorized_keys on each target server.
define('RUNNER_SSH_KEY', getenv('RUNNER_SSH_KEY') ?: '~/.ssh/id_ed25519');

define('RUNNER_SCRIPT_TIMEOUT', 3600);
