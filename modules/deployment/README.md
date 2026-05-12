# Deployment Module

Drop-in Trongate module for zero-infrastructure deployments. Inspired by [Laravel Envoy](https://laravel.com/docs/envoy) — configuration lives in one file you edit, not a database.

## Setup

1. Copy `modules/deployment/` into your Trongate project's `modules/` folder.
2. Set `RUNNER_SSH_KEY` in your `config/config.php` — path to the SSH private key the web server uses to connect to your remote server:
   ```php
   define('RUNNER_SSH_KEY', '/var/www/.ssh/id_ed25519');
   ```
3. Edit `modules/deployment/views/scripts/server.php` to match your server and application.

## Configuration

Open `modules/deployment/views/scripts/server.php` and fill in your values:

```php
$host = '1.2.3.4';        // remote server IP
$user = 'root';            // SSH user
$port = 22;                // SSH port

$repo   = 'https://github.com/you/app.git';
$branch = 'main';

$webroot       = '/var/www/html';
$releases_path = '/var/www/releases';

$domain      = 'example.com';   // leave empty to skip vhost setup
$php_version = '8.4';

$db_name = 'myapp';
$db_user = 'myapp';
$db_pass = 'secret';

$env_vars = [];   // extra environment variables exported into the release
```

That's it. No migrations, no admin UI, no environment records.

## Deployment scripts

The bash scripts are plain PHP views in `views/scripts/`. Edit them freely:

| File | What it does |
|---|---|
| `server.php` | **Your configuration** — server connection, app settings |
| `deploy_script.php` | Clones/unzips the release, sets up vhost, patches config, sets permissions |
| `promote_release.php` | Finds the newest timestamped release and atomically symlinks it to `$webroot` |
| `demote_release.php` | Rolls back to the previous release by re-symlinking |
| `_vhost.php` | Apache vhost block (included by deploy_script) |
| `_config_patch.php` | Database config patch (included by deploy_script) |
| `_env_vars.php` | Environment variable exports (included by deploy_script) |

Releases are stored as timestamped directories (`YYYYMMDDHHMMSS`) under `$releases_path` and are lexicographically sortable by default — no tracking needed.

## Routes

| URL | Action |
|---|---|
| `GET  /deployment` | Dashboard — deploy / promote / rollback buttons + live output |
| `GET  /deployment/stream` | Server-Sent Events stream of deploy script output |
| `POST /deployment/promote` | Symlink the newest release to `$webroot` |
| `POST /deployment/demote` | Roll back to the previous release |
