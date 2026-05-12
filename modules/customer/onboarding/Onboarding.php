<?php

class Onboarding extends Trongate
{

  private array $php_versions = ['8.4', '8.3', '8.2', '8.1', '8.0', '7.4'];
  private const ZIP_RETENTION_SECONDS = 604800;

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

    $this->_remember_onboarding_server($customer, (int) $server->id);
    if ($server->status !== 'active') {
      redirect('customer-onboarding/provision_server');
    }

    if (empty($customer->onboarding_dns_ssl_seen)) {
      redirect('customer-onboarding/dns_ssl');
    }

    $deployment = $this->model->first_deployment((int) $customer->id);
    if ($deployment === false) {
      redirect('customer-onboarding/register_deployment');
    }

    redirect('customer-onboarding/deploy_app');
  }

  // ── Step 1: SSH Key ─────────────────────────────────────────────

  function ssh_key(): void
  {
    $customer = $this->_require_not_onboarded();

    if (!$this->_is_post()) {
      $this->_render_wizard_view('ssh_key', [], 1, 'customer/logout', 'Sign out');
      return;
    }

    $this->validation->set_rules('public_key', 'SSH Public Key', 'required|callback_validate_ssh_key');

    if ($this->validation->run() !== true) {
      $this->_render_wizard_view('ssh_key', [], 1, 'customer/logout', 'Sign out');
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
      $this->_render_wizard_view('environment', ['php_versions' => $this->php_versions], 2, 'customer-onboarding/ssh_key', '← Back');
      return;
    }

    $this->validation->set_rules('name',        'Environment name', 'required|max_length[100]');
    $this->validation->set_rules('php_version', 'PHP version',      'required');

    if ($this->validation->run() !== true) {
      $this->_render_wizard_view('environment', ['php_versions' => $this->php_versions], 2, 'customer-onboarding/ssh_key', '← Back');
      return;
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
    $env_id = $this->environment->model->create_with_defaults(
      post('name', true),
      post('php_version', true),
      post('domain', true) ?: null,
      $cfg_patches,
    );
    if ($env_id === false) {
      $_SESSION['flash_error'] = 'Environment could not be created.';
      $this->_render_wizard_view('environment', ['php_versions' => $this->php_versions], 2, 'customer-onboarding/ssh_key', '← Back');
      return;
    }

    $this->module('environment-services');
    $this->services->model->create_defaults_for_environment(
      (int) $env_id,
      (array) ($_POST['services'] ?? []),
      post('domain', true) ?: null,
    );

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
      $this->_render_wizard_view('choose_provider', [], 3, 'customer-onboarding/environment', '← Back');
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
        $this->_render_wizard_view('server_manual', ['env' => $env], 4, 'customer-onboarding/choose_provider', '← Back');
        return;
      }

      $server_id = (int) $this->model->create_server([
        'environment_id' => (int) $env->id,
        'name'           => post('name', true),
        'ip_address'     => post('ip_address', true),
        'ssh_user'       => 'root',
        'ssh_port'       => 22,
        'provider'       => 'manual',
        'status'         => 'pending',
      ]);

      $this->model->backfill_service_hosts((int) $env->id, post('ip_address', true));
      $this->_remember_onboarding_server($customer, $server_id);

      redirect('customer-onboarding/provision_server');
      return;
    }

    $this->_render_wizard_view('server_manual', ['env' => $env], 4, 'customer-onboarding/choose_provider', '← Back');
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
        $this->_remember_onboarding_server($customer, (int) $server->id);
        redirect('customer-onboarding/provision_server');
        return;
      }
      if (empty($customer->onboarding_dns_ssl_seen)) {
        $this->_remember_onboarding_server($customer, (int) $server->id);
        redirect('customer-onboarding/dns_ssl');
        return;
      }

      $server_id = (int) $server->id;

      $this->model->create_deployment([
        'server_id'      => $server_id,
        'environment_id' => (int) $env->id,
        'source_type'    => $source_type,
        'repo_url'       => $source_type === 'git' ? post('repo_url', true) : null,
        'branch'         => $source_type === 'git' ? post('branch', true)   : null,
        'zip_path'       => $zip_path,
        'status'         => 'script_ready',
      ]);

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
      $this->_remember_onboarding_server($customer, (int) $server->id);
      redirect('customer-onboarding/provision_server');
    }
    if (empty($customer->onboarding_dns_ssl_seen)) {
      $this->_remember_onboarding_server($customer, (int) $server->id);
      redirect('customer-onboarding/dns_ssl');
    }

    $this->_render_wizard_view('register_deployment', [
      'provider' => $server->provider ?? 'manual',
      'env'      => $env,
      'server'   => $server,
    ], 7, 'customer-onboarding/dns_ssl', '← Back');
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

    $this->_render_wizard_view('server_hetzner', [
      'existing_token' => $creds['token'] ?? '',
    ], 4, 'customer-onboarding/choose_provider', '← Back');
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

      $this->_remember_onboarding_server($customer, (int) $server_id);
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
      $tracked    = $this->server->model->tracked_hetzner_ids();
      $importable = array_values(array_filter($all_servers, fn($s) => !in_array($s['id'], $tracked)));
    } catch (Throwable $e) {
      $_SESSION['flash_error'] = 'Could not load Hetzner data: ' . $e->getMessage();
      redirect('customer-onboarding/server_hetzner');
    }

    $this->_render_wizard_view('configure_hetzner_server', [
      'env'          => $env,
      'regions'      => $regions,
      'server_types' => $server_types,
      'importable'   => $importable,
    ], 4, 'customer-onboarding/server_hetzner', '← Back', 'onboarding-card--wide');
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
    $server = $this->_onboarding_server($customer);
    if ($server === false) {
      redirect('customer');
      return;
    }
    $_server_ipv6 = trim((string) ($server->ipv6_address ?? ''));
    $_ipv6_part   = $_server_ipv6 !== '' ? ', ' . $_server_ipv6 : '';
    $this->_render_wizard_view('provision_server', [
      'server'        => $server,
      'stream_url'    => BASE_URL . 'server/stream/' . (int) $server->id,
      'server_active' => $server->status === 'active',
    ], 5, 'customer', 'Skip to Dashboard', '',
      subheading: 'Installing the LAMP stack on ' . $server->name . ' (' . $server->ip_address . $_ipv6_part . '). This takes a few minutes.');
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
        $this->_mark_dns_ssl_seen($customer);
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

      $this->_mark_dns_ssl_seen($customer);
      unset($_SESSION['onboarding_ssl_retryable_failure']);
      $_SESSION['flash_success'] = 'SSL enabled for ' . trim((string) $server->domain) . '.';
      redirect('customer-onboarding/register_deployment');
      return;
    }

    $this->_show_dns_ssl($server);
  }

  function _show_dns_ssl(object $server): void
  {
    $this->_render_wizard_view('dns_ssl', [
      'server' => $server,
      'domain' => trim((string) ($server->domain ?? '')),
      'can_enable_ssl' => $this->_can_enable_ssl($server),
      'ssl_error' => $this->_ssl_preflight_error($server),
      'ssl_retryable_failure' => !empty($_SESSION['onboarding_ssl_retryable_failure']),
      'ssl_stream_url' => BASE_URL . 'customer-onboarding/ssl_stream',
    ], 6, 'customer-onboarding/provision_server', '← Back', 'onboarding-card--large');
  }

  function ssl_stream(): void
  {
    $this->module('stream');
    $this->stream->start();
    $emit = function (string $line, string $event = ''): void {
      $this->stream->emit($line, $event);
    };

    $customer = $this->_require_logged_in();
    $server = $this->_onboarding_server($customer);

    if ($server === false) {
      $emit('Server not found.');
      $this->stream->done(['status' => 'failed']);
      return;
    }

    if ($server->status !== 'active') {
      $emit('Provision the server before enabling SSL.');
      $this->stream->done(['status' => 'failed']);
      return;
    }

    $error = $this->_ssl_preflight_error($server);
    if ($error !== '') {
      $emit($error);
      $this->stream->done(['status' => 'failed']);
      return;
    }

    $this->stream->prepare_long_running();

    $emit('Starting SSL setup for ' . trim((string) $server->domain) . '...');
    [$exit_code, $log] = $this->_run_remote_bash_stream(
      $server,
      $this->_render_certbot_script($server),
      $emit,
    );

    if ($exit_code !== 0) {
      $emit($this->_ssl_failure_message($log, $exit_code));
      $this->stream->done(['status' => 'failed']);
      return;
    }

    $this->_mark_dns_ssl_seen($customer);
    $emit('SSL setup complete.');
    $this->stream->done(['status' => 'success']);
  }

  // ── Step 8: Deploy app ──────────────────────────────────────────

  function deploy_app(): void
  {
    $customer      = $this->_require_logged_in();
    if (empty($customer->onboarding_dns_ssl_seen)) {
      redirect('customer-onboarding/dns_ssl');
      return;
    }

    $deployment = $this->model->first_deployment((int) $customer->id);
    if ($deployment === false) {
      redirect('customer-onboarding/register_deployment');
      return;
    }

    $this->module('deployment');
    $deployment = $this->deployment->model->get((int) $deployment->id);
    if ($deployment === false) {
      redirect('customer');
      return;
    }
    $this->_render_wizard_view('deploy_app', [
      'deployment' => $deployment,
      'stream_url' => BASE_URL . 'deployment/stream/' . (int) $deployment->id,
    ], 8, 'customer', 'Go to Dashboard', '',
      subheading: 'Running deployment #' . (int) $deployment->id . ' on ' . $deployment->server_name . '.');
  }

  function complete(): void
  {
    $customer = $this->_require_logged_in();
    $deployment = $this->model->first_deployment((int) $customer->id);
    if ($deployment === false) {
      http_response_code(400);
      return;
    }

    $this->module('deployment');
    $deployment = $this->deployment->model->get((int) $deployment->id);
    if ($deployment === false || !in_array($deployment->status, ['staged', 'success'], true)) {
      http_response_code(409);
      return;
    }

    $this->model->mark_onboarded((int) $customer->id);
    unset($_SESSION['onboarding_ssl_retryable_failure']);

    $this->module('http');
    $this->http->json_response(['ok' => true]);
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
      return 'Invalid API token. Verify it has Read & Write access in your Hetzner project.';
    }
    return true;
  }

  // ── Auth guard ──────────────────────────────────────────────────

  function _store_zip(): string|false
  {
    $this->_prune_zip_storage();

    if (empty($_FILES['zip_file']['tmp_name']) || $_FILES['zip_file']['error'] !== UPLOAD_ERR_OK) {
      return false;
    }
    if (strtolower(pathinfo($_FILES['zip_file']['name'], PATHINFO_EXTENSION)) !== 'zip') {
      return false;
    }
    $hash = hash_file('sha256', $_FILES['zip_file']['tmp_name']);
    if ($hash === false) {
      return false;
    }
    $this->module('storage');
    $dir = $this->storage->ensure_dir('deploy_zips');
    if ($dir === false) {
      return false;
    }
    $dest = $dir . DIRECTORY_SEPARATOR . 'provision_deploy_' . $hash . '.zip';
    if (!move_uploaded_file($_FILES['zip_file']['tmp_name'], $dest)) {
      return false;
    }
    return $dest;
  }

  private function _prune_zip_storage(): void
  {
    $this->module('storage');
    if (!is_dir($this->storage->path('deploy_zips'))) {
      return;
    }

    $keep = [];
    $rows = $this->db->query_bind(
      "SELECT zip_path FROM deployment WHERE zip_path IS NOT NULL AND status IN ('script_ready','running','failed')",
      [],
      'object',
    );
    foreach ($rows as $row) {
      $keep[(string) $row->zip_path] = true;
    }

    foreach ($this->storage->glob('deploy_zips/*.zip') as $file) {
      if (isset($keep[$file])) {
        continue;
      }
      $mtime = filemtime($file);
      if ($mtime !== false && time() - $mtime > self::ZIP_RETENTION_SECONDS) {
        @unlink($file);
      }
    }
  }

  function _is_post(): bool
  {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
  }

  function _onboarding_server(object $customer): object|false
  {
    $server_id = (int) ($customer->onboarding_server_id ?? 0);
    if ($server_id === 0) {
      $server = $this->model->first_server((int) $customer->id);
      if ($server === false) {
        return false;
      }
      $server_id = (int) $server->id;
      $this->_remember_onboarding_server($customer, $server_id);
    }
    $this->module('server');

    return $this->server->model->get($server_id);
  }

  function _remember_onboarding_server(object $customer, int $server_id): void
  {
    if ((int) ($customer->onboarding_server_id ?? 0) === $server_id) {
      return;
    }

    $this->model->save_onboarding_server((int) $customer->id, $server_id);
    $customer->onboarding_server_id = $server_id;
  }

  function _mark_dns_ssl_seen(object $customer): void
  {
    if (!empty($customer->onboarding_dns_ssl_seen)) {
      return;
    }

    $this->model->mark_dns_ssl_seen((int) $customer->id);
    $customer->onboarding_dns_ssl_seen = 1;
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

    $this->module('ssh');
    $log = '';
    $exit_code = $this->ssh->execute_script(
      $server->ip_address,
      $user,
      $port,
      $script,
      function (string $line) use (&$log): void {
        $log .= $line . "\n";
      },
      null,
      RUNNER_SCRIPT_TIMEOUT,
    );

    return [$exit_code, trim($log)];
  }

  function _run_remote_bash_stream(object $server, string $script, callable $emit): array
  {
    $user = $server->ssh_user ?: 'root';
    $port = (int) ($server->ssh_port ?: 22);

    $this->module('ssh');
    $log = '';
    $exit_code = $this->ssh->execute_script(
      $server->ip_address,
      $user,
      $port,
      $script,
      function (string $line) use (&$log, $emit): void {
        $log .= $line . "\n";
        $emit($line);
      },
      fn() => $this->stream->ping('ssl-ping'),
      RUNNER_SCRIPT_TIMEOUT,
    );

    return [$exit_code, trim($log)];
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

  // ── Wizard rendering ────────────────────────────────────────────

  /**
   * Render an onboarding partial view inside the shared wizard template.
   *
   * @param string      $view_file   Partial view filename (without .php)
   * @param array       $data        Data to pass to the partial view
   * @param int         $step        Current step number
   * @param string      $back_url    Back/cancel link href
   * @param string      $back_text   Back/cancel link label
   * @param string      $card_class  Extra CSS class(es) for .onboarding-card (default '')
   * @param string|null $subheading  Override subheading (null = use lookup table default)
   */
  function _render_wizard_view(
    string $view_file,
    array $data,
    int $step,
    string $back_url,
    string $back_text,
    string $card_class = '',
    ?string $subheading = null,
  ): void {
    static $meta = [
      'ssh_key'                  => ['SSH Key — Provision Setup',                    '&#128273; Add Your SSH Key',         "Your public key will be embedded into every LAMP setup script, so you can SSH into your servers the moment they're provisioned.", ''],
      'environment'              => ['First Environment — Provision Setup',           '&#9670; Your First Environment',     "An environment defines your app's runtime and infrastructure. Source code and git details are set per deployment.",            ''],
      'choose_provider'          => ['Choose Provider — Provision Setup',             'How will you provision servers?',    'Choose how you want to add your first server. You can use both methods later from the dashboard.',                          'onboarding-card--standard'],
      'server_manual'            => ['Add Server — Provision Setup',                  '&#9646; Your Server',                "Enter your server's details. Provision will generate a LAMP setup script you can run via SSH.",                             'onboarding-card--standard'],
      'server_hetzner'           => ['Connect Hetzner — Provision Setup',             '&#9729; Connect Hetzner Cloud',      'Enter your API token. Provision will validate it and upload your SSH key so servers are accessible the moment they boot.',   'onboarding-card--standard'],
      'configure_hetzner_server' => ['Configure Hetzner Server — Provision Setup',   '&#9729; Configure Hetzner Server',   'Select or create the server that Provision will prepare for this environment.',                                             'onboarding-card--wide'],
      'provision_server'         => ['Provisioning Server — Provision Setup',         '&#9881; Provisioning Server',        '',                                                                                                                         ''],
      'dns_ssl'                  => ['DNS &amp; SSL — Provision Setup',               '&#128274; DNS &amp; SSL',            "Point your domain at the provisioned server, then optionally run Let's Encrypt before registering the deployment.",         'onboarding-card--large'],
      'register_deployment'      => ['Create Deployment — Provision Setup',           '&#9654; Create Deployment',          'Confirm your provisioned server and app source. Provision will run the deployment script next.',                             'onboarding-card--standard'],
      'deploy_app'               => ['Deploying App — Provision Setup',               '&#10148; Deploying App',             '',                                                                                                                         ''],
    ];

    [$title, $heading, $default_subheading, $default_card_class] = $meta[$view_file] ?? [$view_file, $view_file, '', ''];

    $data = array_merge($data, [
      'view_module'      => 'customer/onboarding',
      'view_file'        => $view_file,
      'wizard_title'     => $title,
      'wizard_heading'   => $heading,
      'wizard_subheading'=> $subheading ?? $default_subheading,
      'wizard_css'       => 'customer-onboarding_module/css/onboarding.css',
      'wizard_card_class'=> $card_class !== '' ? $card_class : $default_card_class,
      'wizard_step_num'  => $step,
      'wizard_step_total'=> 8,
      'wizard_back_url'  => $back_url,
      'wizard_back_text' => $back_text,
      'wizard_js'        => 'customer-onboarding_module/js/onboarding.js',
    ]);

    $this->module('templates');
    $this->templates->wizard($data);
  }
}
