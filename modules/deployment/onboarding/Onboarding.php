<?php

require_once __DIR__ . '/../event/Emits_events.php';

class Deployment_onboarding extends Trongate
{
    use Emits_events;

    private array $php_versions = ['8.4', '8.3', '8.2', '8.1', '8.0', '7.4'];
    private const ZIP_RETENTION_SECONDS = 604800;

    // ── Router ──────────────────────────────────────────────────────
    //  Step 1 → environment
    //  Step 2 → server      (manual IP or Hetzner)
    //  Step 3 → provision   (display LAMP script, user runs it)
    //  Step 4 → deployment  (git / zip form)
    //  Step 5 → deploy      (stream execution)

    function index(): void
    {
        $this->_require_auth();
        redirect('deployment-onboarding/environment');
    }

    // ── Step 1: Environment ─────────────────────────────────────────

    function environment(): void
    {
        $this->_require_auth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validation->set_rules('name',        'Environment name', 'required|max_length[100]');
            $this->validation->set_rules('php_version', 'PHP version',      'required');

            if ($this->validation->run() === true) {
                $allowed_php_versions = ['8.0', '8.1', '8.2', '8.3', '8.4'];
                if (!in_array(post('php_version', true), $allowed_php_versions, true)) {
                    $_SESSION['flash_error'] = 'Invalid PHP version.';
                } else {
                    $cfg_patches = array_filter([
                        'PROVISION_ENV'          => post('cfg_env', true),
                        'PROVISION_WEBSITE_NAME' => post('cfg_website_name', true),
                        'PROVISION_OUR_NAME'     => post('cfg_our_name', true),
                        'PROVISION_OUR_TELNUM'   => post('cfg_our_telnum', true),
                        'PROVISION_OUR_ADDRESS'  => post('cfg_our_address', true),
                        'PROVISION_OUR_EMAIL'    => post('cfg_our_email', true),
                    ], fn($v) => $v !== '' && $v !== null);

                    $this->module('deployment-environment');
                    $env_id = $this->environment->model->create_with_defaults(
                        post('name', true),
                        post('php_version', true),
                        post('domain', true) ?: null,
                        $cfg_patches,
                    );

                    if ($env_id === false) {
                        $_SESSION['flash_error'] = 'Environment could not be created.';
                        $this->_render_wizard_view('environment', ['php_versions' => $this->php_versions], 1, 'login', 'Sign out');
                        return;
                    }

                    $this->module('deployment-services');
                    $this->services->model->create_defaults_for_environment(
                        (int) $env_id,
                        (array) ($_POST['services'] ?? []),
                        post('domain', true) ?: null,
                    );

                    $_SESSION['onboarding_env_id'] = (int) $env_id;
                    redirect('deployment-onboarding/server');
                    return;
                }
            }
        }

        $this->_render_wizard_view('environment', ['php_versions' => $this->php_versions], 1, 'login', 'Sign out');
    }

    // ── Step 2: Server ──────────────────────────────────────────────

    function server(): void
    {
        $this->_require_auth();

        $env_id = (int) ($_SESSION['onboarding_env_id'] ?? 0);
        if ($env_id === 0) {
            redirect('deployment-onboarding/environment');
            return;
        }

        $this->module('deployment-environment');
        $env = $this->environment->model->get($env_id);
        if ($env === false) {
            unset($_SESSION['onboarding_env_id']);
            redirect('deployment-onboarding/environment');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $provider = post('provider', true) ?: 'manual';

            $server_id = match ($provider) {
                'hetzner'        => $this->_try_server_hetzner($env),
                'hetzner_import' => $this->_try_server_hetzner_import($env),
                default          => $this->_try_server_manual($env),
            };

            if ($server_id !== null) {
                $_SESSION['onboarding_server_id'] = $server_id;
                redirect('deployment-onboarding/provision');
                return;
            }
        }

        $customer_id = $this->_get_current_customer_id();

        $this->module('deployment-provider');
        $has_hetzner   = $this->provider->model->has_hetzner($customer_id);
        $hetzner_regions = [];
        $hetzner_types   = [];
        $hetzner_servers = [];

        if ($has_hetzner) {
            try {
                $h              = $this->_hetzner_client($customer_id);
                $hetzner_regions = $h->list_regions();
                $hetzner_types   = $h->list_server_types();
                $all_servers     = $h->list_servers();
                $this->module('deployment-server');
                $tracked         = $this->server->model->tracked_hetzner_ids();
                $hetzner_servers = array_values(array_filter($all_servers, fn($s) => !in_array($s['id'], $tracked)));
            } catch (Throwable $e) {
                $has_hetzner = false;
                $_SESSION['flash_error'] = 'Could not load Hetzner data: ' . $e->getMessage();
            }
        }

        $this->_render_wizard_view('server', [
            'env'             => $env,
            'has_hetzner'     => $has_hetzner,
            'hetzner_regions' => $hetzner_regions,
            'hetzner_types'   => $hetzner_types,
            'hetzner_servers' => $hetzner_servers,
        ], 2, 'deployment-onboarding/environment', '← Back');
    }

    private function _try_server_manual(object $env): ?int
    {
        $this->validation->set_rules('name',       'Server name', 'required|max_length[100]');
        $this->validation->set_rules('ip_address', 'IP address',  'required|max_length[45]');

        if ($this->validation->run() !== true) {
            return null;
        }

        $id = (int) $this->db->insert([
            'environment_id' => (int) $env->id,
            'name'           => post('name', true),
            'ip_address'     => post('ip_address', true),
            'ssh_user'       => 'root',
            'ssh_port'       => 22,
            'provider'       => 'manual',
            'status'         => 'pending',
        ], 'server');

        $this->db->query_bind(
            "UPDATE service SET host = :ip WHERE environment_id = :env_id AND (host IS NULL OR host = '')",
            ['ip' => post('ip_address', true), 'env_id' => (int) $env->id],
        );

        return $id;
    }

    private function _try_server_hetzner(object $env): ?int
    {
        $this->validation->set_rules('name',        'Server name', 'required|max_length[100]');
        $this->validation->set_rules('region',      'Region',      'required');
        $this->validation->set_rules('server_type', 'Server type', 'required');

        if ($this->validation->run() !== true) {
            return null;
        }

        $customer_id = $this->_get_current_customer_id();

        $this->module('deployment-provider');
        if (!$this->provider->model->has_hetzner($customer_id)) {
            $_SESSION['flash_error'] = 'Hetzner not connected. Connect Hetzner via Settings first.';
            return null;
        }

        $h      = $this->_hetzner_client($customer_id);
        $creds  = $this->provider->model->get_hetzner($customer_id);
        $name   = post('name', true);
        $region = post('region', true);
        $type   = post('server_type', true);

        $runner_public_key = $this->_runner_public_key();
        if ($runner_public_key === '') {
            $_SESSION['flash_error'] = 'Runner SSH public key not found. Create ' . RUNNER_SSH_KEY . '.pub before provisioning Hetzner servers.';
            return null;
        }

        $runner_key  = $h->ensure_ssh_key('provision-runner-' . substr(md5(BASE_URL), 0, 8), $runner_public_key);
        $ssh_key_ids = $this->_hetzner_ssh_key_ids($creds);
        $ssh_key_ids[] = $runner_key['id'];
        $ssh_key_ids = array_values(array_unique(array_filter($ssh_key_ids)));

        $this->provider->model->save_hetzner(
            $customer_id,
            $creds['token'],
            $creds['ssh_key_id'] ?? $runner_key['id'],
            $creds['ssh_key_label'] ?? $runner_key['name'],
            $ssh_key_ids,
        );

        $result = $h->create_server(
            name:      preg_replace('/[^a-z0-9-]/', '-', strtolower($name)),
            type:      $type,
            region:    $region,
            ssh_keys:  $ssh_key_ids,
            labels:    ['managed_by' => 'provision'],
            user_data: '',
            image:     'ubuntu-22.04',
        );

        $id = (int) $this->db->insert([
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
        ], 'server');

        $this->db->query_bind(
            "UPDATE service SET host = :ip WHERE environment_id = :env_id AND (host IS NULL OR host = '')",
            ['ip' => $result['ipv4'], 'env_id' => (int) $env->id],
        );

        return $id;
    }

    private function _try_server_hetzner_import(object $env): ?int
    {
        $this->validation->set_rules('name',       'Server name', 'required|max_length[100]');
        $this->validation->set_rules('hetzner_id', 'Server',      'required');

        if ($this->validation->run() !== true) {
            return null;
        }

        $customer_id = $this->_get_current_customer_id();

        $this->module('deployment-provider');
        if (!$this->provider->model->has_hetzner($customer_id)) {
            $_SESSION['flash_error'] = 'Hetzner not connected. Connect Hetzner via Settings first.';
            return null;
        }

        $h           = $this->_hetzner_client($customer_id);
        $provider_id = (string)(int) post('hetzner_id', true);
        $remote      = $h->get_server($provider_id);

        if (!$remote) {
            $_SESSION['form_submission_errors'][] = ['Server not found in your Hetzner account.'];
            return null;
        }

        $id = (int) $this->db->insert([
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
        ], 'server');

        $this->db->query_bind(
            "UPDATE service SET host = :ip WHERE environment_id = :env_id AND (host IS NULL OR host = '')",
            ['ip' => $remote['ip'], 'env_id' => (int) $env->id],
        );

        return $id;
    }

    // ── Step 3: Provision ───────────────────────────────────────────

    function provision(): void
    {
        $this->_require_auth();

        $env_id = (int) ($_SESSION['onboarding_env_id'] ?? 0);
        if ($env_id === 0) {
            redirect('deployment-onboarding/environment');
            return;
        }

        $server_id = (int) ($_SESSION['onboarding_server_id'] ?? 0);
        if ($server_id === 0) {
            redirect('deployment-onboarding/server');
            return;
        }

        $this->module('deployment-server');
        $server = $this->server->model->get($server_id);
        if ($server === false) {
            unset($_SESSION['onboarding_server_id']);
            redirect('deployment-onboarding/server');
            return;
        }

        $lamp_script = $this->_generate_lamp_script($server);

        $_server_ipv6 = trim((string) ($server->ipv6_address ?? ''));
        $_ipv6_part   = $_server_ipv6 !== '' ? ', ' . $_server_ipv6 : '';

        $this->_render_wizard_view('provision', [
            'server'        => $server,
            'lamp_script'   => $lamp_script,
            'stream_url'    => BASE_URL . 'server/stream/' . $server_id,
            'server_active' => $server->status === 'active',
        ], 3, 'deployment-onboarding/server', '← Back',
            subheading: 'Installing the LAMP stack on ' . $server->name . ' (' . $server->ip_address . $_ipv6_part . '). This takes a few minutes.');
    }

    // ── Step 4: Deployment ──────────────────────────────────────────

    function deployment(): void
    {
        $this->_require_auth();

        $server_id = (int) ($_SESSION['onboarding_server_id'] ?? 0);
        $env_id    = (int) ($_SESSION['onboarding_env_id']    ?? 0);

        if ($server_id === 0) {
            redirect('deployment-onboarding/server');
            return;
        }
        if ($env_id === 0) {
            redirect('deployment-onboarding/environment');
            return;
        }

        $this->module('deployment-server');
        $server = $this->server->model->get($server_id);
        if ($server === false) {
            redirect('deployment-onboarding/server');
            return;
        }

        $this->module('deployment-environment');
        $env = $this->environment->model->get($env_id);
        if ($env === false) {
            redirect('deployment-onboarding/environment');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $source_type = post('source_type', true) === 'zip' ? 'zip' : 'git';

            if ($source_type === 'git') {
                $this->validation->set_rules('repo_url', 'Repo URL', 'required|max_length[500]');
                $this->validation->set_rules('branch',   'Branch',   'required|max_length[100]');
            }

            if ($this->validation->run() === true) {
                $zip_path = null;
                if ($source_type === 'zip') {
                    $zip_path = $this->_store_zip();
                    if ($zip_path === false) {
                        $_SESSION['form_submission_errors'][] = ['Zip upload failed or file missing.'];
                        $this->_render_wizard_view('deployment', ['env' => $env, 'server' => $server], 4, 'deployment-onboarding/provision', '← Back');
                        return;
                    }
                }

                $dep_id = (int) $this->db->insert([
                    'server_id'      => $server_id,
                    'environment_id' => $env_id,
                    'source_type'    => $source_type,
                    'repo_url'       => $source_type === 'git' ? post('repo_url', true) : null,
                    'branch'         => $source_type === 'git' ? post('branch', true)   : null,
                    'zip_path'       => $zip_path,
                    'status'         => 'script_ready',
                ], 'deployment');

                $_SESSION['onboarding_deployment_id'] = $dep_id;
                redirect('deployment-onboarding/deploy');
                return;
            }
        }

        $this->_render_wizard_view('deployment', ['env' => $env, 'server' => $server], 4, 'deployment-onboarding/provision', '← Back');
    }

    // ── Step 5: Deploy ──────────────────────────────────────────────

    function deploy(): void
    {
        $this->_require_auth();

        $dep_id = (int) ($_SESSION['onboarding_deployment_id'] ?? 0);
        if ($dep_id === 0) {
            redirect('deployment-onboarding/deployment');
            return;
        }

        $this->module('deployment');
        $deployment = $this->deployment->model->get($dep_id);
        if ($deployment === false) {
            unset($_SESSION['onboarding_deployment_id']);
            redirect('deployment-onboarding/deployment');
            return;
        }

        $this->_render_wizard_view('deploy', [
            'deployment' => $deployment,
            'stream_url' => BASE_URL . 'deployment/stream/' . $dep_id,
        ], 5, 'deployment', 'Go to Dashboard', '',
            subheading: 'Running deployment #' . $dep_id . ' on ' . $deployment->server_name . '.');
    }

    // ── Hetzner helpers ─────────────────────────────────────────────

    private function _hetzner_client(int $customer_id): Hetzner
    {
        $this->module('deployment-provider');
        $creds = $this->provider->model->get_hetzner($customer_id);
        if (empty($creds['token'])) {
            throw new RuntimeException('Hetzner token not configured.');
        }
        $this->module('cloud');
        return $this->cloud->hetzner($creds['token']);
    }

    private function _hetzner_ssh_key_ids(array $creds): array
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

    private function _runner_public_key(): string
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

    // ── LAMP script generation ──────────────────────────────────────

    private function _generate_lamp_script(object $server): string
    {
        return (string) $this->view(
            'scripts/lamp_script',
            [
                'view_module' => 'deployment/server',
                'server'      => $server,
            ],
            true,
        );
    }

    // ── Zip helpers ─────────────────────────────────────────────────

    private function _store_zip(): string|false
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
        $this->module('deployment-storage');
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
        $this->module('deployment-storage');
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

    // ── Auth & helpers ──────────────────────────────────────────────

    private function _require_auth(): void
    {
        $this->module('trongate_tokens');
        if (!$this->trongate_tokens->_attempt_get_valid_token(1)) {
            redirect('login');
        }
    }

    private function _get_current_customer_id(): int
    {
        $this->module('customer');
        $customer = $this->customer->model->_get_current_customer();
        return $customer !== false ? (int) $customer->id : 0;
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
    private function _render_wizard_view(
        string $view_file,
        array $data,
        int $step,
        string $back_url,
        string $back_text,
        string $card_class = '',
        ?string $subheading = null,
    ): void {
        static $meta = [
            'environment' => ['First Environment — Provision Setup',    '&#9670; Your First Environment',  "An environment defines your app's runtime. Source code and git details are set per deployment.",        ''],
            'server'      => ['Add Server — Provision Setup',           '&#9646; Add a Server',            'Choose your server provider and enter the connection details.',                                       'onboarding-card--wide'],
            'provision'   => ['Provision Server — Provision Setup',     '&#9881; Provision Server',        '',                                                                                                    ''],
            'deployment'  => ['Configure Deployment — Provision Setup', '&#9654; Configure Deployment',   'Confirm the deployment source. Provision will run the deployment script on the next step.',           'onboarding-card--standard'],
            'deploy'      => ['Deploying App — Provision Setup',        '&#10148; Deploying App',          '',                                                                                                    ''],
        ];

        [$title, $heading, $default_subheading, $default_card_class] = $meta[$view_file] ?? [$view_file, $view_file, '', ''];

        $data = array_merge($data, [
            'view_module'       => 'deployment/onboarding',
            'view_file'         => $view_file,
            'wizard_title'      => $title,
            'wizard_heading'    => $heading,
            'wizard_subheading' => $subheading ?? $default_subheading,
            'wizard_css'        => 'deployment-onboarding_module/css/onboarding.css',
            'wizard_card_class' => $card_class !== '' ? $card_class : $default_card_class,
            'wizard_step_num'   => $step,
            'wizard_step_total' => 5,
            'wizard_back_url'   => $back_url,
            'wizard_back_text'  => $back_text,
            'wizard_js'         => 'deployment-onboarding_module/js/onboarding.js',
        ]);

        $this->module('templates');
        $this->templates->wizard($data);
    }
}
