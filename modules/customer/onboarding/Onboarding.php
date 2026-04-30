<?php

class Onboarding extends Trongate
{

  private array $php_versions = ['8.4', '8.3', '8.2', '8.1', '8.0', '7.4'];

  // ── Router ──────────────────────────────────────────────────────
  //  Step 1 → ssh_key
  //  Step 2 → environment
  //  Step 3 → choose_provider     (Manual | Hetzner)
  //  Step 4 → configure provider  (server_manual | server_hetzner)
  //  Step 5 → provision_server
  //  Step 6 → dns_ssl
  //  Step 7 → register_deployment
  //  Step 8 → deploy_app          (mark onboarded after successful deploy)

  function index(): void
  {
    $customer = $this->_require_not_onboarded();

    if (empty($customer->ssh_public_key)) {
      redirect('customer-onboarding/ssh_key');
    }

    if ($this->model->first_environment((int) $customer->id) === false) {
      redirect('customer-onboarding/environment');
    }

    $choice = $this->_onboarding_provider($customer);

    if ($choice === null) {
      redirect('customer-onboarding/choose_provider');
    }

    $server = $this->model->first_server((int) $customer->id);
    if ($server === false) {
      if ($choice === 'manual') {
        redirect('customer-onboarding/server_manual');
      }

      $this->module('provider');
      if (!$this->provider->model->has_hetzner((int) $customer->id)) {
        redirect('customer-onboarding/server_hetzner');
      }
      redirect('customer-onboarding/configure_hetzner_server');
    }

    $_SESSION['onboarding_server_id'] = (int) $server->id;
    if ($server->status !== 'active') {
      redirect('customer-onboarding/provision_server');
    }

    if (empty($_SESSION['onboarding_dns_ssl_seen'])) {
      redirect('customer-onboarding/dns_ssl');
    }

    $deployment = $this->model->first_deployment((int) $customer->id);
    if ($deployment === false) {
      redirect('customer-onboarding/register_deployment');
    }

    $_SESSION['onboarding_deployment_id'] = (int) $deployment->id;
    redirect('customer-onboarding/deploy_app');
  }

  // ── Step 1: SSH Key ─────────────────────────────────────────────

  function ssh_key(): void
  {
    $customer = $this->_require_not_onboarded();

    if (!$this->_is_post()) {
      $this->view('ssh_key');
      return;
    }

    $this->validation->set_rules('public_key', 'SSH Public Key', 'required|callback_validate_ssh_key');

    if ($this->validation->run() !== true) {
      $this->view('ssh_key');
      return;
    }

    $this->model->save_ssh_key((int) $customer->id, $this->_normalize_ssh_public_key((string) post('public_key')));
    redirect('customer-onboarding/environment');
  }

  // ── Step 2: Environment ─────────────────────────────────────────

  function environment(): void
  {
    $customer = $this->_require_not_onboarded();

    if (empty($customer->ssh_public_key)) {
      redirect('customer-onboarding/ssh_key');
    }

    if (!$this->_is_post()) {
      $this->view('environment', ['php_versions' => $this->php_versions]);
      return;
    }

    $this->validation->set_rules('name',        'Environment name', 'required|max_length[100]');
    $this->validation->set_rules('php_version', 'PHP version',      'required');

    if ($this->validation->run() !== true) {
      $this->view('environment', ['php_versions' => $this->php_versions]);
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

    if (!$this->_is_post()) {
      $this->view('choose_provider');
      return;
    }

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

    $this->model->save_onboarding_provider((int) $customer->id, $choice);
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

    if ($this->_onboarding_provider($customer) !== 'manual') {
      redirect('customer-onboarding/choose_provider');
    }

    $env = $this->model->first_environment((int) $customer->id);
    if ($env === false) {
      redirect('customer-onboarding/environment');
    }

    if ($this->_is_post()) {
      $this->validation->set_rules('name',       'Server name', 'required|max_length[100]');
      $this->validation->set_rules('ip_address', 'IP address',  'required|max_length[45]');

      if ($this->validation->run() !== true) {
        $this->view('server_manual', [
          'env' => $env,
        ]);
        return;
      }

      $server_id = (int) $this->model->create_server([
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
      $_SESSION['onboarding_server_id'] = $server_id;

      redirect('customer-onboarding/provision_server');
      return;
    }

    $this->view('server_manual', [
      'env' => $env,
    ]);
  }

  // ── Step 7: Create deployment ──────────────────────────────────

  function register_deployment(): void
  {
    $customer = $this->_require_not_onboarded();
    $env = $this->model->first_environment((int) $customer->id);
    if ($env === false) {
      redirect('customer-onboarding/environment');
    }

    if ($this->_is_post()) {
      // Validate source fields before touching any server resources
      $source_type = post('source_type', true) === 'zip' ? 'zip' : 'git';
      if ($source_type === 'git') {
        $this->validation->set_rules('repo_url', 'repo URL', 'required|max_length[500]');
        $this->validation->set_rules('branch',   'branch',   'required|max_length[100]');
      }

      if ($this->validation->run() !== true) {
        $this->_show_register_deployment($customer, $env);
        return;
      }

      $zip_path = null;
      if ($source_type === 'zip') {
        $zip_path = $this->_store_zip();
        if ($zip_path === false) {
          $_SESSION['form_submission_errors'][] = ['Zip upload failed or file missing.'];
          $this->_show_register_deployment($customer, $env);
          return;
        }
      }

      $server = $this->model->first_server((int) $customer->id);
      if ($server === false) {
        redirect('customer-onboarding/choose_provider');
        return;
      }
      if ($server->status !== 'active') {
        $_SESSION['onboarding_server_id'] = (int) $server->id;
        redirect('customer-onboarding/provision_server');
        return;
      }
      if (empty($_SESSION['onboarding_dns_ssl_seen'])) {
        $_SESSION['onboarding_server_id'] = (int) $server->id;
        redirect('customer-onboarding/dns_ssl');
        return;
      }

      $server_id = (int) $server->id;

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

      $_SESSION['onboarding_server_id']     = $server_id;
      $_SESSION['onboarding_deployment_id'] = (int) $id;
      redirect('customer-onboarding/deploy_app');
      return;
    }

    $this->_show_register_deployment($customer, $env);
  }

  function _show_register_deployment(object $customer, object $env): void
  {
    $server = $this->model->first_server((int) $customer->id);
    if ($server === false) {
      redirect('customer-onboarding/choose_provider');
    }
    if ($server->status !== 'active') {
      $_SESSION['onboarding_server_id'] = (int) $server->id;
      redirect('customer-onboarding/provision_server');
    }
    if (empty($_SESSION['onboarding_dns_ssl_seen'])) {
      $_SESSION['onboarding_server_id'] = (int) $server->id;
      redirect('customer-onboarding/dns_ssl');
    }

    $this->view('register_deployment', [
      'provider' => $server->provider ?? 'manual',
      'env'      => $env,
      'server'   => $server,
    ]);
  }

  // ── Step 5b: Hetzner ────────────────────────────────────────────

  function server_hetzner(): void
  {
    $customer = $this->_require_not_onboarded();

    if ($this->_onboarding_provider($customer) !== 'hetzner') {
      redirect('customer-onboarding/choose_provider');
    }

    if (empty($customer->ssh_public_key)) {
      redirect('customer-onboarding/ssh_key');
    }

    if ($this->_is_post()) {
      $this->validation->set_rules('token', 'API Token', 'required|callback_validate_hetzner_token');

      if ($this->validation->run() !== true) {
        $this->_show_server_hetzner($customer);
        return;
      }

      $token = post('token', true);

      $this->module('cloud');
      $h = $this->cloud->hetzner($token);

      $ssh_key_ids = [];
      $key_id = '';
      $label = '';

      $runner_public_key = $this->_runner_public_key();
      if ($runner_public_key === '') {
        $_SESSION['form_submission_errors'][] = [
          'Runner SSH public key not found. Create ' . RUNNER_SSH_KEY . '.pub before connecting Hetzner.',
        ];
        $this->_show_server_hetzner($customer);
        return;
      }

      $runner_label = 'provision-runner-' . substr(md5(BASE_URL), 0, 8);
      $runner_key = $h->ensure_ssh_key($runner_label, $runner_public_key);
      $key_id = $runner_key['id'];
      $label = $runner_key['name'];
      $ssh_key_ids[] = $runner_key['id'];

      if (!empty($customer->ssh_public_key)) {
        $customer_label = 'provision-customer-' . substr(md5($customer->email), 0, 8);
        $customer_key = $h->ensure_ssh_key($customer_label, $customer->ssh_public_key);
        $ssh_key_ids[] = $customer_key['id'];
      }

      $this->module('provider');
      $this->provider->model->save_hetzner((int) $customer->id, $token, $key_id, $label, $ssh_key_ids);

      redirect('customer-onboarding/configure_hetzner_server');
      return;
    }

    $this->_show_server_hetzner($customer);
  }

  function _show_server_hetzner(object $customer): void
  {
    $this->module('provider');
    $creds = $this->provider->model->get_hetzner((int) $customer->id);

    $this->view('server_hetzner', [
      'existing_token' => $creds['token'] ?? '',
    ]);
  }

  function configure_hetzner_server(): void
  {
    $customer = $this->_require_not_onboarded();

    if ($this->_onboarding_provider($customer) !== 'hetzner') {
      redirect('customer-onboarding/choose_provider');
    }

    $env = $this->model->first_environment((int) $customer->id);
    if ($env === false) {
      redirect('customer-onboarding/environment');
    }

    $this->module('provider');
    if (!$this->provider->model->has_hetzner((int) $customer->id)) {
      redirect('customer-onboarding/server_hetzner');
    }

    if ($this->_is_post()) {
      $server_id = match (post('provider', true)) {
        'hetzner'        => $this->_try_hetzner_new($customer, $env),
        'hetzner_import' => $this->_try_hetzner_import($customer, $env),
        default          => null,
      };

      if ($server_id === null) {
        $this->_show_configure_hetzner_server($customer, $env);
        return;
      }

      $_SESSION['onboarding_server_id'] = (int) $server_id;
      redirect('customer-onboarding/provision_server');
      return;
    }

    $this->_show_configure_hetzner_server($customer, $env);
  }

  function _show_configure_hetzner_server(object $customer, object $env): void
  {
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

    $this->view('configure_hetzner_server', [
      'env'          => $env,
      'regions'      => $regions,
      'server_types' => $server_types,
      'importable'   => $importable,
    ]);
  }

  function _try_hetzner_new(object $customer, object $env): ?int
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

    $ssh_key_ids = $this->_hetzner_ssh_key_ids($creds);

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
      'ipv6_address'   => $result['ipv6'] ?? null,
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

  function _try_hetzner_import(object $customer, object $env): ?int
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
      'ipv6_address'   => $remote['ipv6'] ?? null,
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

  function _hetzner_client(int $customer_id): Hetzner
  {
    $this->module('provider');
    $creds = $this->provider->model->get_hetzner($customer_id);
    if (empty($creds['token'])) {
      throw new RuntimeException('Hetzner token not configured.');
    }
    $this->module('cloud');
    return $this->cloud->hetzner($creds['token']);
  }

  function _hetzner_ssh_key_ids(array $creds): array
  {
    $ids = $creds['ssh_key_ids'] ?? [];
    if (!is_array($ids)) {
      $ids = [];
    }
    if (!empty($creds['ssh_key_id'])) {
      array_unshift($ids, $creds['ssh_key_id']);
    }
    $normalized = [];
    foreach ($ids as $id) {
      $id = trim((string) $id);
      if ($id === '' || in_array($id, $normalized, true)) {
        continue;
      }
      $normalized[] = $id;
    }
    return $normalized;
  }

  function _runner_public_key(): string
  {
    if (!defined('RUNNER_SSH_KEY') || RUNNER_SSH_KEY === '') {
      return '';
    }
    $path = RUNNER_SSH_KEY . '.pub';
    if (is_readable($path)) {
      return trim((string) file_get_contents($path));
    }
    exec('ssh-keygen -y -f ' . escapeshellarg(RUNNER_SSH_KEY) . ' 2>/dev/null', $out, $code);
    return $code === 0 ? trim(implode("\n", $out)) : '';
  }

  // ── Step 5: Provision server ────────────────────────────────────

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

  // ── Step 6: DNS & SSL ───────────────────────────────────────────

  function dns_ssl(): void
  {
    $customer = $this->_require_logged_in();
    $server = $this->_onboarding_server($customer);

    if ($server === false) {
      redirect('customer');
      return;
    }
    if ($server->status !== 'active') {
      redirect('customer-onboarding/provision_server');
      return;
    }

    if ($this->_is_post()) {
      $action = post('action', true) === 'enable_ssl' ? 'enable_ssl' : 'skip';
      $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
      if ($this->validation->run() !== true) {
        $this->_show_dns_ssl($server);
        return;
      }

      if ($action === 'skip') {
        $_SESSION['onboarding_dns_ssl_seen'] = 1;
        unset($_SESSION['onboarding_ssl_retryable_failure']);
        redirect('customer-onboarding/register_deployment');
        return;
      }

      $error = $this->_ssl_preflight_error($server);
      if ($error !== '') {
        $_SESSION['form_submission_errors'][] = [$error];
        $this->_show_dns_ssl($server);
        return;
      }

      $script = $this->_render_certbot_script($server);
      if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
      }

      [$exit_code, $log] = $this->_run_remote_bash($server, $script);

      if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
      }

      if ($exit_code !== 0) {
        $_SESSION['onboarding_ssl_retryable_failure'] = 1;
        $_SESSION['form_submission_errors'][] = [
          $this->_ssl_failure_message($log, $exit_code),
        ];
        $this->_show_dns_ssl($server);
        return;
      }

      $_SESSION['onboarding_dns_ssl_seen'] = 1;
      unset($_SESSION['onboarding_ssl_retryable_failure']);
      $_SESSION['flash_success'] = 'SSL enabled for ' . trim((string) $server->domain) . '.';
      redirect('customer-onboarding/register_deployment');
      return;
    }

    $this->_show_dns_ssl($server);
  }

  function _show_dns_ssl(object $server): void
  {
    $this->view('dns_ssl', [
      'server' => $server,
      'domain' => trim((string) ($server->domain ?? '')),
      'can_enable_ssl' => $this->_can_enable_ssl($server),
      'ssl_error' => $this->_ssl_preflight_error($server),
      'ssl_retryable_failure' => !empty($_SESSION['onboarding_ssl_retryable_failure']),
    ]);
  }

  // ── Step 8: Deploy app ──────────────────────────────────────────

  function deploy_app(): void
  {
    $customer      = $this->_require_logged_in();
    if (empty($_SESSION['onboarding_dns_ssl_seen'])) {
      redirect('customer-onboarding/dns_ssl');
      return;
    }

    $deployment_id = (int) ($_SESSION['onboarding_deployment_id'] ?? 0);
    if ($deployment_id === 0) {
      $deployment = $this->model->first_deployment((int) $customer->id);
      if ($deployment === false) {
        redirect('customer-onboarding/register_deployment');
        return;
      }
      $deployment_id = (int) $deployment->id;
      $_SESSION['onboarding_deployment_id'] = $deployment_id;
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

  function complete(): void
  {
    $customer = $this->_require_logged_in();
    $deployment_id = (int) ($_SESSION['onboarding_deployment_id'] ?? 0);
    if ($deployment_id === 0) {
      http_response_code(400);
      return;
    }

    $this->module('deployment');
    $deployment = $this->deployment->model->get($deployment_id, (int) $customer->id);
    if ($deployment === false || $deployment->status !== 'success') {
      http_response_code(409);
      return;
    }

    $this->model->mark_onboarded((int) $customer->id);
    unset($_SESSION['onboarding_provider']);
    unset($_SESSION['onboarding_server_id']);
    unset($_SESSION['onboarding_deployment_id']);
    unset($_SESSION['onboarding_dns_ssl_seen']);
    unset($_SESSION['onboarding_ssl_retryable_failure']);

    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
  }

  // ── Validation callbacks ────────────────────────────────────────

  function validate_ssh_key(string $public_key): bool|string
  {
    $public_key = $this->_normalize_ssh_public_key($public_key);
    if (empty($public_key)) return 'SSH Public Key is required.';

    $valid_types = ['ssh-rsa', 'ssh-ed25519', 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521'];
    $parts = explode(' ', $public_key, 3);
    $type = $parts[0] ?? '';

    if (!in_array($type, $valid_types, true)) {
      return 'Invalid format. Must start with ssh-rsa, ssh-ed25519, or ecdsa-sha2-*.';
    }

    if (count($parts) < 2) return 'Invalid structure. Expected: [type] [base64-data] [optional-comment]';
    if (!preg_match('/^[A-Za-z0-9+\/]+=*$/', $parts[1])) return 'Invalid key data — not valid base64.';

    $decoded = base64_decode($parts[1], true);
    if ($decoded === false || $decoded === '') return 'Invalid key data — not valid base64.';

    $blob_type = $this->_ssh_key_blob_type($decoded);
    if ($blob_type !== $type) {
      return 'Invalid key data. The encoded key does not match the declared key type.';
    }

    return true;
  }

  function _normalize_ssh_public_key(string $public_key): string
  {
    $parts = preg_split('/\s+/', trim($public_key));
    if ($parts === false) {
      return '';
    }
    $parts = array_values(array_filter($parts, static fn($part) => $part !== ''));
    if (count($parts) < 3) {
      return implode(' ', $parts);
    }
    return $parts[0] . ' ' . $parts[1] . ' ' . implode(' ', array_slice($parts, 2));
  }

  function _ssh_key_blob_type(string $decoded): string
  {
    if (strlen($decoded) < 4) {
      return '';
    }
    $length = unpack('N', substr($decoded, 0, 4))[1] ?? 0;
    if (!is_int($length) || $length <= 0 || strlen($decoded) < 4 + $length) {
      return '';
    }
    return substr($decoded, 4, $length);
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

  function _store_zip(): string|false
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

  function _is_post(): bool
  {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
  }

  function _onboarding_server(object $customer): object|false
  {
    $server_id = (int) ($_SESSION['onboarding_server_id'] ?? 0);
    if ($server_id === 0) {
      $server = $this->model->first_server((int) $customer->id);
      if ($server === false) {
        return false;
      }
      $server_id = (int) $server->id;
      $_SESSION['onboarding_server_id'] = $server_id;
    }
    $this->module('server');
    return $this->server->model->get($server_id, (int) $customer->id);
  }

  function _can_enable_ssl(object $server): bool
  {
    return $this->_ssl_preflight_error($server) === '';
  }

  function _ssl_preflight_error(object $server): string
  {
    $domain = trim((string) ($server->domain ?? ''));
    $email = trim((string) ($server->customer_email ?? ''));
    $user = $server->ssh_user ?: 'root';

    if (!defined('RUNNER_SSH_KEY') || !RUNNER_SSH_KEY) {
      return 'RUNNER_SSH_KEY is not configured.';
    }
    if (!filter_var($server->ip_address, FILTER_VALIDATE_IP)) {
      return 'Invalid server IP address.';
    }
    if (!preg_match('/^[a-z_][a-z0-9_.-]{0,31}$/i', $user)) {
      return 'Invalid SSH user.';
    }
    if (!$this->_valid_domain($domain)) {
      return 'Configure a valid environment domain before enabling SSL.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return 'Configure a valid customer email before enabling SSL.';
    }

    return '';
  }

  function _render_certbot_script(object $server): string
  {
    return (string) $this->view(
      'scripts/certbot_script',
      [
        'view_module' => 'server',
        'server' => $server,
      ],
      true,
    );
  }

  function _run_remote_bash(object $server, string $script): array
  {
    $user = $server->ssh_user ?: 'root';
    $port = (int) ($server->ssh_port ?: 22);
    $timeout = RUNNER_SCRIPT_TIMEOUT;
    $cmd =
      'ssh' .
      ' -i ' .
      escapeshellarg(RUNNER_SSH_KEY) .
      ' -o StrictHostKeyChecking=no' .
      ' -o UserKnownHostsFile=/dev/null' .
      ' -o LogLevel=ERROR' .
      ' -o BatchMode=yes' .
      ' -o ConnectTimeout=15' .
      ' -o ServerAliveInterval=30' .
      ' -o ServerAliveCountMax=3' .
      ' -p ' .
      $port .
      ' ' .
      escapeshellarg("{$user}@{$server->ip_address}") .
      " 'timeout {$timeout} bash -s' 2>&1";

    $proc = proc_open(
      $cmd,
      [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']],
      $pipes,
    );
    if (!is_resource($proc)) {
      return [1, 'Failed to open SSH connection.'];
    }

    fwrite($pipes[0], $script);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    return [proc_close($proc), trim($output)];
  }

  function _ssl_failure_message(string $log, int $exit_code): string
  {
    $message = trim($log);

    if ($exit_code === 124) {
      return 'SSL setup failed: the remote setup timed out before finishing. Check whether apt, Apache, or certbot is still running on the server, then try again.';
    }

    if (preg_match('/SUDO_DENIED_COMMAND=([^\r\n]+)/', $message, $matches)) {
      return 'SSL setup failed: passwordless sudo is missing for `' . trim($matches[1]) . '`. Add that command to /etc/sudoers.d/provision, then try again.';
    }

    if (stripos($message, 'requires root privileges') !== false ||
      (stripos($message, 'permission denied') !== false && stripos($message, 'apt') !== false)
    ) {
      return 'SSL setup failed: the SSH user needs root privileges to install certbot. Connect as root or configure passwordless sudo, then try again.';
    }

    if ($exit_code === 13) {
      return 'SSL setup failed: sudo rejected one of the required commands. Details: ' . $this->_tail_text($message ?: 'No remote sudo output was captured.', 800);
    }

    if (stripos($message, 'Timed out waiting for apt/dpkg locks') !== false ||
      $exit_code === 75 ||
      stripos($message, 'Could not get lock') !== false ||
      stripos($message, 'Unable to lock directory') !== false
    ) {
      return 'SSL setup failed: another apt/dpkg process is still running on the server. Wait a few minutes, then try again.';
    }

    if ($message === '') {
      return 'SSL setup failed with exit code ' . $exit_code . ' before producing output. Retry once; if it repeats, run certbot manually on the server to see the underlying error.';
    }

    return 'SSL setup failed: ' . substr($message ?: 'certbot failed.', 0, 500);
  }

  function _tail_text(string $message, int $length): string
  {
    return strlen($message) > $length ? '...' . substr($message, -$length) : $message;
  }

  function _valid_domain(string $domain): bool
  {
    if ($domain === '' || strlen($domain) > 253) {
      return false;
    }
    return (bool) preg_match(
      '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
      $domain,
    );
  }

  function _require_not_onboarded(): object
  {
    $this->module('trongate_tokens');
    $token = $this->trongate_tokens->attempt_get_valid_token(2);
    if ($token === false) redirect('customer/login');

    $customer = $this->model->_get_current_customer();
    if ($customer === false) redirect('customer/login');
    if (!empty($customer->onboarded_at)) redirect('customer');

    return $customer;
  }

  function _onboarding_provider(object $customer): ?string
  {
    $choice = $customer->onboarding_provider ?? null;

    if (in_array($choice, ['manual', 'hetzner'], true)) {
      $_SESSION['onboarding_provider'] = $choice;
      return $choice;
    }

    $choice = $_SESSION['onboarding_provider'] ?? null;
    if (in_array($choice, ['manual', 'hetzner'], true)) {
      $this->model->save_onboarding_provider((int) $customer->id, $choice);
      return $choice;
    }

    return null;
  }

  function _require_logged_in(): object
  {
    $this->module('trongate_tokens');
    $token = $this->trongate_tokens->attempt_get_valid_token(2);
    if ($token === false) redirect('customer/login');

    $customer = $this->model->_get_current_customer();
    if ($customer === false) redirect('customer/login');

    return $customer;
  }
}
