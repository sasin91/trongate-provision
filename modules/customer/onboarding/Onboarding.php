<?php

class Onboarding extends Trongate
{

  private array $php_versions = ['8.4', '8.3', '8.2', '8.1', '8.0', '7.4'];

  // ── Router ──────────────────────────────────────────────────────
  //  Step 1 → ssh_key
  //  Step 2 → environment
  //  Step 4 → choose_provider      (Manual | Hetzner)
  //  Step 5 → server_manual        (IP + name)
  //         → server_hetzner       (API token + guidance)
  //  Step 6 → register_deployment  (both paths: confirm server + create deployment)

  function index(): void
  {
    $customer = $this->_require_not_onboarded();

    if (empty($customer->ssh_public_key)) {
      redirect('customer-onboarding/ssh_key');
    }

    if ($this->model->first_environment((int) $customer->id) === false) {
      redirect('customer-onboarding/environment');
    }

    $choice = $_SESSION['onboarding_provider'] ?? null;

    if ($choice === null) {
      redirect('customer-onboarding/choose_provider');
    }

    if ($choice === 'manual') {
      if ($this->model->first_server((int) $customer->id) === false) {
        redirect('customer-onboarding/server_manual');
      }
    } elseif ($choice === 'hetzner') {
      $this->module('provider');
      if (!$this->provider->model->has_hetzner((int) $customer->id)) {
        redirect('customer-onboarding/server_hetzner');
      }
    }

    if ($this->model->first_deployment((int) $customer->id) === false) {
      redirect('customer-onboarding/register_deployment');
    }

    $this->model->mark_onboarded((int) $customer->id);
    unset($_SESSION['onboarding_provider']);
    redirect('customer');
  }

  // ── Step 1: SSH Key ─────────────────────────────────────────────

  function ssh_key(): void
  {
    $this->_require_not_onboarded();
    $this->view('ssh_key');
  }

  function submit_ssh_key(): void
  {
    $customer = $this->_require_not_onboarded();

    $this->validation->set_rules('public_key', 'SSH Public Key', 'required|callback_validate_ssh_key');

    if ($this->validation->run() !== true) {
      $this->ssh_key();
      return;
    }

    $this->model->save_ssh_key((int) $customer->id, post('public_key'));
    redirect('customer-onboarding/environment');
  }

  // ── Step 2: Environment ─────────────────────────────────────────

  function environment(): void
  {
    $customer = $this->_require_not_onboarded();

    if (empty($customer->ssh_public_key)) {
      redirect('customer-onboarding/ssh_key');
    }

    $this->view('environment', ['php_versions' => $this->php_versions]);
  }

  function submit_environment(): void
  {
    $customer = $this->_require_not_onboarded();

    $this->validation->set_rules('name',        'Environment name', 'required|max_length[100]');
    $this->validation->set_rules('php_version', 'PHP version',      'required');

    if ($this->validation->run() !== true) {
      $this->environment();
      return;
    }

    $name    = post('name', true);
    $db_name = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');

    $env_id = $this->model->create_environment([
      'customer_id' => (int) $customer->id,
      'name'        => $name,
      'php_version' => post('php_version', true),
      'web_root'    => '/var/www/html',
      'domain'      => post('domain', true) ?: null,
      'db_name'     => $db_name,
    ]);

    $allowed = [
      'apache2'    => ['type' => 'http',        'port' => 80,   'name' => 'Apache2'],
      'mariadb'    => ['type' => 'mysql',        'port' => 3306, 'name' => 'MariaDB'],
      'redis'      => ['type' => 'redis',        'port' => 6379, 'name' => 'Redis'],
      'postgresql' => ['type' => 'postgresql',   'port' => 5432, 'name' => 'PostgreSQL'],
    ];

    $selected = (array) ($_POST['services'] ?? []);
    foreach ($selected as $svc) {
      if (!isset($allowed[$svc])) continue;
      $def = $allowed[$svc];
      $this->db->insert([
        'environment_id' => (int) $env_id,
        'customer_id'    => (int) $customer->id,
        'name'           => $def['name'],
        'type'           => $def['type'],
        'host'           => $def['type'] === 'http' ? (post('domain', true) ?: null) : null,
        'port'           => $def['port'],
        'status'         => 'pending',
      ], 'service');
    }

    $cfg_patches = array_filter([
      'PROVISION_ENV'          => post('cfg_env', true),
      'PROVISION_WEBSITE_NAME' => post('cfg_website_name', true),
      'PROVISION_OUR_NAME'     => post('cfg_our_name', true),
      'PROVISION_OUR_TELNUM'   => post('cfg_our_telnum', true),
      'PROVISION_OUR_ADDRESS'  => post('cfg_our_address', true),
      'PROVISION_OUR_EMAIL'    => post('cfg_our_email', true),
    ], fn($v) => $v !== '' && $v !== null);

    $this->module('environment');
    $this->environment->model->save_variables((int) $env_id, (int) $customer->id, array_merge([
      'DB_NAME'     => $db_name,
      'DB_USER'     => $db_name,
      'DB_PASSWORD' => bin2hex(random_bytes(16)),
    ], $cfg_patches));

    redirect('customer-onboarding/choose_provider');
  }

  // ── Step 4: Choose provider ─────────────────────────────────────

  function choose_provider(): void
  {
    $customer = $this->_require_not_onboarded();

    if (empty($customer->ssh_public_key)) {
      redirect('customer-onboarding/ssh_key');
    }
    if ($this->model->first_environment((int) $customer->id) === false) {
      redirect('customer-onboarding/environment');
    }

    $this->view('choose_provider');
  }

  function submit_choose_provider(): void
  {
    $this->_require_not_onboarded();

    $choice = post('provider', true);
    if (!in_array($choice, ['manual', 'hetzner'], true)) {
      redirect('customer-onboarding/choose_provider');
      return;
    }

    $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
    if ($this->validation->run() !== true) {
      redirect('customer-onboarding/choose_provider');
      return;
    }

    $_SESSION['onboarding_provider'] = $choice;

    redirect(
      $choice === 'hetzner'
        ? 'customer-onboarding/server_hetzner'
        : 'customer-onboarding/server_manual'
    );
  }

  // ── Step 5a: Manual server ──────────────────────────────────────

  function server_manual(): void
  {
    $customer = $this->_require_not_onboarded();

    if (($_SESSION['onboarding_provider'] ?? null) !== 'manual') {
      redirect('customer-onboarding/choose_provider');
    }

    $env = $this->model->first_environment((int) $customer->id);
    if ($env === false) {
      redirect('customer-onboarding/environment');
    }

    $this->view('server_manual', [
      'env' => $env,
    ]);
  }

  function submit_server_manual(): void
  {
    $customer = $this->_require_not_onboarded();

    $env = $this->model->first_environment((int) $customer->id);
    if ($env === false) {
      redirect('customer-onboarding/environment');
    }

    $this->validation->set_rules('name',       'Server name', 'required|max_length[100]');
    $this->validation->set_rules('ip_address', 'IP address',  'required|max_length[45]');

    if ($this->validation->run() !== true) {
      $this->server_manual();
      return;
    }

    $this->model->create_server([
      'environment_id' => (int) $env->id,
      'customer_id'    => (int) $customer->id,
      'name'           => post('name', true),
      'ip_address'     => post('ip_address', true),
      'ssh_user'       => 'root',
      'ssh_port'       => 22,
      'provider'       => 'manual',
      'status'         => 'pending',
    ]);

    $this->model->backfill_service_hosts((int) $env->id, post('ip_address', true));

    redirect('customer-onboarding/register_deployment');
  }

  // ── Step 6: Create deployment (both paths) ─────────────────────

  function register_deployment(): void
  {
    $customer = $this->_require_not_onboarded();
    $choice   = $_SESSION['onboarding_provider'] ?? null;

    $env = $this->model->first_environment((int) $customer->id);
    if ($env === false) {
      redirect('customer-onboarding/environment');
    }

    if ($choice === 'manual') {
      $server = $this->model->first_server((int) $customer->id);
      if ($server === false) {
        redirect('customer-onboarding/server_manual');
      }

      $this->view('register_deployment', [
        'provider' => 'manual',
        'env'      => $env,
        'server'   => $server,
      ]);
    } elseif ($choice === 'hetzner') {
      $this->module('provider');
      if (!$this->provider->model->has_hetzner((int) $customer->id)) {
        redirect('customer-onboarding/server_hetzner');
      }

      try {
        $h            = $this->_hetzner_client((int) $customer->id);
        $regions      = $h->list_regions();
        $server_types = $h->list_server_types();
        $all_servers  = $h->list_servers();
        $this->module('server');
        $tracked    = $this->server->model->tracked_hetzner_ids((int) $customer->id);
        $importable = array_values(array_filter($all_servers, fn($s) => !in_array($s['id'], $tracked)));
      } catch (Throwable $e) {
        $_SESSION['flash_error'] = 'Could not load Hetzner data: ' . $e->getMessage();
        redirect('customer-onboarding/server_hetzner');
      }

      $this->view('register_deployment', [
        'provider'     => 'hetzner',
        'env'          => $env,
        'regions'      => $regions,
        'server_types' => $server_types,
        'importable'   => $importable,
      ]);
    } else {
      redirect('customer-onboarding/choose_provider');
    }
  }

  function submit_register_deployment(): void
  {
    $customer = $this->_require_not_onboarded();
    $choice   = $_SESSION['onboarding_provider'] ?? null;

    $env = $this->model->first_environment((int) $customer->id);
    if ($env === false) {
      redirect('customer-onboarding/environment');
    }

    // Validate source fields before touching any server resources
    $source_type = post('source_type', true) === 'zip' ? 'zip' : 'git';
    if ($source_type === 'git') {
      $this->validation->set_rules('repo_url', 'repo URL', 'required|max_length[500]');
      $this->validation->set_rules('branch',   'branch',   'required|max_length[100]');
    }

    $zip_path = null;
    if ($source_type === 'zip') {
      $zip_path = $this->_store_zip();
      if ($zip_path === false) {
        $_SESSION['form_submission_errors'][] = ['Zip upload failed or file missing.'];
        $this->register_deployment();
        return;
      }
    }

    if ($choice === 'manual') {
      $server = $this->model->first_server((int) $customer->id);
      if ($server === false) {
        redirect('customer-onboarding/server_manual');
      }
      $server_id = (int) $server->id;
    } elseif ($choice === 'hetzner') {
      $server_id = match (post('provider', true)) {
        'hetzner'        => $this->_try_hetzner_new($customer, $env),
        'hetzner_import' => $this->_try_hetzner_import($customer, $env),
        default          => null,
      };
      if ($server_id === null) {
        $this->register_deployment();
        return;
      }
    } else {
      redirect('customer-onboarding/choose_provider');
      return;
    }

    $id = $this->model->create_deployment([
      'server_id'      => $server_id,
      'environment_id' => (int) $env->id,
      'customer_id'    => (int) $customer->id,
      'source_type'    => $source_type,
      'repo_url'       => $source_type === 'git' ? post('repo_url', true) : null,
      'branch'         => $source_type === 'git' ? post('branch', true)   : null,
      'zip_path'       => $zip_path,
      'is_canary'      => 0,
      'canary_weight'  => 100,
      'status'         => 'script_ready',
    ]);

    $this->model->mark_onboarded((int) $customer->id);
    $_SESSION['onboarding_server_id']     = $server_id;
    $_SESSION['onboarding_deployment_id'] = (int) $id;
    unset($_SESSION['onboarding_provider']);
    redirect('customer-onboarding/provision_server');
  }

  // ── Step 5b: Hetzner ────────────────────────────────────────────

  function server_hetzner(): void
  {
    $customer = $this->_require_not_onboarded();

    if (($_SESSION['onboarding_provider'] ?? null) !== 'hetzner') {
      redirect('customer-onboarding/choose_provider');
    }

    if (empty($customer->ssh_public_key)) {
      redirect('customer-onboarding/ssh_key');
    }

    $this->module('provider');
    $creds = $this->provider->model->get_hetzner((int) $customer->id);

    $this->view('server_hetzner', [
      'existing_token' => $creds['token'] ?? '',
    ]);
  }

  function submit_server_hetzner(): void
  {
    $customer = $this->_require_not_onboarded();

    $this->validation->set_rules('token', 'API Token', 'required|callback_validate_hetzner_token');

    if ($this->validation->run() !== true) {
      $this->server_hetzner();
      return;
    }

    $token = post('token', true);

    $this->module('cloud');
    $h = $this->cloud->hetzner($token);

    // Upload customer SSH key to Hetzner
    $label  = 'provision-' . substr(md5($customer->email), 0, 8);
    $key_id = '';

    if (!empty($customer->ssh_public_key)) {
      try {
        $key_id = $h->upload_ssh_key($label, $customer->ssh_public_key);
      } catch (Client_Error $e) {
        if ($e->getCode() === 409) {
          foreach ($h->list_ssh_keys() as $k) {
            $stored = preg_split('/\s+/', trim($k['public_key']));
            $input  = preg_split('/\s+/', trim($customer->ssh_public_key));
            if (isset($stored[1], $input[1]) && $stored[1] === $input[1]) {
              $key_id = (string) $k['id'];
              $label  = $k['name'];
              break;
            }
          }
        }
      }
    }

    $this->module('provider');
    $this->provider->model->save_hetzner((int) $customer->id, $token, $key_id, $label);

    redirect('customer-onboarding/register_deployment');
  }

  private function _try_hetzner_new(object $customer, object $env): ?int
  {
    $this->validation->set_rules('name',        'server name', 'required|max_length[100]');
    $this->validation->set_rules('region',      'region',      'required');
    $this->validation->set_rules('server_type', 'server type', 'required');

    if ($this->validation->run() !== true) return null;

    $h      = $this->_hetzner_client((int) $customer->id);
    $this->module('provider');
    $creds  = $this->provider->model->get_hetzner((int) $customer->id);
    $name   = post('name', true);
    $region = post('region', true);
    $type   = post('server_type', true);

    $ssh_key_ids = !empty($creds['ssh_key_id']) ? [$creds['ssh_key_id']] : [];

    $result = $h->create_server(
      name: preg_replace('/[^a-z0-9-]/', '-', strtolower($name)),
      type: $type,
      region: $region,
      ssh_keys: $ssh_key_ids,
      labels: ['managed_by' => 'provision'],
      user_data: '',
      image: 'ubuntu-22.04',
    );

    $server_id = (int) $this->model->create_server([
      'environment_id' => (int) $env->id,
      'customer_id'    => (int) $customer->id,
      'name'           => $name,
      'ip_address'     => $result['ipv4'],
      'ssh_user'       => 'root',
      'ssh_port'       => 22,
      'provider'       => 'hetzner',
      'provider_id'    => $result['provider_id'],
      'region'         => $result['region'],
      'server_type'    => $result['type'],
      'status'         => 'pending',
    ]);

    $this->model->backfill_service_hosts((int) $env->id, $result['ipv4']);

    return $server_id;
  }

  private function _try_hetzner_import(object $customer, object $env): ?int
  {
    $this->validation->set_rules('name',       'server name', 'required|max_length[100]');
    $this->validation->set_rules('hetzner_id', 'server',      'required');

    if ($this->validation->run() !== true) return null;

    $h           = $this->_hetzner_client((int) $customer->id);
    $provider_id = post('hetzner_id', true);
    $remote      = $h->get_server($provider_id);

    if (!$remote) {
      $_SESSION['form_submission_errors'][] = ['Server not found in your Hetzner account.'];
      return null;
    }

    $server_id = (int) $this->model->create_server([
      'environment_id' => (int) $env->id,
      'customer_id'    => (int) $customer->id,
      'name'           => post('name', true),
      'ip_address'     => $remote['ip'],
      'ssh_user'       => 'root',
      'ssh_port'       => 22,
      'provider'       => 'hetzner',
      'provider_id'    => $remote['provider_id'],
      'region'         => $remote['region'],
      'server_type'    => $remote['type'],
      'status'         => ($remote['status'] === 'running') ? 'provisioning' : 'pending',
    ]);

    $this->model->backfill_service_hosts((int) $env->id, $remote['ip']);

    return $server_id;
  }

  private function _hetzner_client(int $customer_id): Hetzner
  {
    $this->module('provider');
    $creds = $this->provider->model->get_hetzner($customer_id);
    if (empty($creds['token'])) {
      throw new RuntimeException('Hetzner token not configured.');
    }
    $this->module('cloud');
    return $this->cloud->hetzner($creds['token']);
  }

  // ── Step 7: Provision server ────────────────────────────────────

  function provision_server(): void
  {
    $customer  = $this->_require_logged_in();
    $server_id = (int) ($_SESSION['onboarding_server_id'] ?? 0);
    if ($server_id === 0) {
      redirect('customer');
      return;
    }
    $this->module('server');
    $server = $this->server->model->get($server_id, (int) $customer->id);
    if ($server === false) {
      redirect('customer');
      return;
    }
    $this->view('provision_server', [
      'server'        => $server,
      'stream_url'    => BASE_URL . 'server/stream/' . (int) $server->id,
      'server_active' => $server->status === 'active',
    ]);
  }

  // ── Step 8: Deploy app ──────────────────────────────────────────

  function deploy_app(): void
  {
    $customer      = $this->_require_logged_in();
    $deployment_id = (int) ($_SESSION['onboarding_deployment_id'] ?? 0);
    if ($deployment_id === 0) {
      redirect('customer');
      return;
    }
    $this->module('deployment');
    $deployment = $this->deployment->model->get($deployment_id, (int) $customer->id);
    if ($deployment === false) {
      redirect('customer');
      return;
    }
    $this->view('deploy_app', [
      'deployment' => $deployment,
      'stream_url' => BASE_URL . 'deployment/stream/' . (int) $deployment->id,
    ]);
  }

  // ── Validation callbacks ────────────────────────────────────────

  function validate_ssh_key(string $public_key): bool|string
  {
    $public_key = trim($public_key);
    if (empty($public_key)) return 'SSH Public Key is required.';

    $valid_types = ['ssh-rsa', 'ssh-ed25519', 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521'];
    $ok = false;
    foreach ($valid_types as $type) {
      if (str_starts_with($public_key, $type . ' ')) {
        $ok = true;
        break;
      }
    }

    if (!$ok) return 'Invalid format. Must start with ssh-rsa, ssh-ed25519, or ecdsa-sha2-*.';

    $parts = preg_split('/\s+/', $public_key);
    if (count($parts) < 2) return 'Invalid structure. Expected: [type] [base64-data] [optional-comment]';
    if (!preg_match('/^[A-Za-z0-9+\/]+=*$/', $parts[1])) return 'Invalid key data — not valid base64.';

    return true;
  }

  function validate_hetzner_token(string $token): bool|string
  {
    if (empty($token)) return 'API Token is required.';
    $this->module('cloud');
    if (!$this->cloud->hetzner($token)->validate_credentials()) {
      return 'Invalid API token. Verify it has Read &amp; Write access in your Hetzner project.';
    }
    return true;
  }

  // ── Auth guard ──────────────────────────────────────────────────

  private function _store_zip(): string|false
  {
    if (empty($_FILES['zip_file']['tmp_name']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
      return false;
    }
    if (strtolower(pathinfo($_FILES['zip_file']['name'], PATHINFO_EXTENSION)) !== 'zip') {
      return false;
    }
    $hash = hash_file('sha256', $_FILES['zip_file']['tmp_name']);
    $dest = '/tmp/provision_deploy_' . $hash . '.zip';
    if (!move_uploaded_file($_FILES['zip_file']['tmp_name'], $dest)) {
      return false;
    }
    return $dest;
  }

  private function _require_not_onboarded(): object
  {
    $this->module('trongate_tokens');
    $token = $this->trongate_tokens->attempt_get_valid_token(2);
    if ($token === false) redirect('customer/login');

    $customer = $this->model->_get_current_customer();
    if ($customer === false) redirect('customer/login');
    if (!empty($customer->onboarded_at)) redirect('customer');

    return $customer;
  }

  private function _require_logged_in(): object
  {
    $this->module('trongate_tokens');
    $token = $this->trongate_tokens->attempt_get_valid_token(2);
    if ($token === false) redirect('customer/login');

    $customer = $this->model->_get_current_customer();
    if ($customer === false) redirect('customer/login');

    return $customer;
  }
}
