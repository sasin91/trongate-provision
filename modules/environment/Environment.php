<?php

require_once __DIR__ . '/../event/Emits_events.php';

class Environment extends Trongate {

    use Emits_events;

    private array $php_versions = ['8.4', '8.3', '8.2', '8.1', '8.0', '7.4'];

    function index(): void {
        $customer = $this->_require_customer();

        $data = [
            'view_module'   => 'environment',
            'view_file'     => 'index',
            'page_title'    => 'Environments',
            'current_email' => $customer->email,
            'environments'  => $this->model->all((int) $customer->id),
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function create(): void {
        $customer = $this->_require_customer();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validation->set_rules('name',        'name',        'required|max_length[100]');
            $this->validation->set_rules('php_version', 'PHP version', 'required');

            if ($this->validation->run() === true) {
                $name = post('name', true);
                $cfg_patches = $this->_config_patch_variables_from_post();
                $env_id = $this->model->create_with_defaults(
                    (int) $customer->id,
                    $name,
                    post('php_version', true),
                    post('domain', true) ?: null,
                    $cfg_patches,
                );
                if ($env_id === false) {
                    $_SESSION['flash_error'] = 'Environment could not be created.';
                    redirect('environment/create');
                    return;
                }
                $this->module('environment-services');
                $this->services->model->create_defaults_for_environment(
                    (int) $env_id,
                    (int) $customer->id,
                    (array) ($_POST['services'] ?? []),
                    post('domain', true) ?: null,
                );
                $this->_emit('EnvironmentCreated', 'environment', (int) $env_id, [
                    'name'        => $name,
                    'php_version' => post('php_version', true),
                ]);
                $_SESSION['flash_success'] = 'Environment created.';
                redirect('environment');
                return;
            }
        }

        $data = [
            'view_module'   => 'environment',
            'view_file'     => 'create',
            'page_title'    => 'New Environment',
            'current_email' => $customer->email,
            'form_location' => 'environment/create',
            'php_versions'  => $this->php_versions,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function show(): void {
        $id = (int) segment(3);
        $customer = $this->_require_customer();
        $env = $this->model->get($id, (int) $customer->id);
        if ($env === false) { redirect('environment'); }

        $this->module('server');
        $servers = $this->server->model->by_environment($id, (int) $customer->id);

        $this->module('environment-services');
        $services      = $this->services->model->by_environment($id, (int) $customer->id);
        $type_defaults = $this->services->model->get_type_defaults();

        $data = [
            'view_module'   => 'environment',
            'view_file'     => 'show',
            'page_title'    => htmlspecialchars($env->name),
            'current_email' => $customer->email,
            'env'           => $env,
            'servers'       => $servers,
            'services'      => $services,
            'type_defaults' => $type_defaults,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function variables(): void {
        $id = (int) segment(3);
        $customer = $this->_require_customer();
        $env = $this->model->get($id, (int) $customer->id);
        if ($env === false) { redirect('environment'); }

        $data = [
            'view_module'   => 'environment',
            'view_file'     => 'variables',
            'page_title'    => htmlspecialchars($env->name) . ' — Variables',
            'current_email' => $customer->email,
            'env'           => $env,
            'variables'     => $this->model->get_variables($id, (int) $customer->id),
            'form_location' => 'environment/save_variables/' . $id,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function save_variables(): void {
        $id = (int) segment(3);
        $customer = $this->_require_customer();
        $env = $this->model->get($id, (int) $customer->id);
        if ($env === false) { redirect('environment'); }

        $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
        if ($this->validation->run() !== true) {
            redirect('environment/variables/' . $id);
            return;
        }

        $keys   = post('keys')   ?: [];
        $values = post('values') ?: [];
        if (!is_array($keys)) $keys = [];
        if (!is_array($values)) $values = [];

        $vars = [];
        foreach ($keys as $i => $k) {
            $k = trim((string) $k);
            if ($k === '') continue;
            $vars[$k] = (string) ($values[$i] ?? '');
        }

        $this->model->save_variables($id, (int) $customer->id, $vars);
        $this->_emit('EnvironmentVariablesUpdated', 'environment', $id, [
            'var_count' => count($vars),
        ]);
        $_SESSION['flash_success'] = count($vars) . ' variable(s) saved (encrypted).';
        redirect('environment/variables/' . $id);
    }

    function delete(): void {
        $id = (int) segment(3);
        $customer = $this->_require_customer();
        $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
        if ($this->validation->run() !== true) { redirect('environment'); return; }
        $snap = $this->model->get($id, (int) $customer->id);
        if ($snap && !empty($snap->zip_path)) {
            if (file_exists($snap->zip_path)) @unlink($snap->zip_path);
        }
        $this->model->delete($id, (int) $customer->id);
        $this->_emit('EnvironmentDeleted', 'environment', $id, [
            'name' => $snap ? $snap->name : null,
        ]);
        $_SESSION['flash_success'] = 'Environment deleted.';
        redirect('environment');
    }

    private function _config_patch_variables_from_post(): array {
        return array_filter([
            'PROVISION_ENV'          => post('cfg_env', true),
            'PROVISION_WEBSITE_NAME' => post('cfg_website_name', true),
            'PROVISION_OUR_NAME'     => post('cfg_our_name', true),
            'PROVISION_OUR_TELNUM'   => post('cfg_our_telnum', true),
            'PROVISION_OUR_ADDRESS'  => post('cfg_our_address', true),
            'PROVISION_OUR_EMAIL'    => post('cfg_our_email', true),
        ], fn($v) => $v !== '' && $v !== null);
    }

    private function _require_customer(): object {
        $this->module('customer');
        $this->customer->_require_onboarded();
        return $this->customer->_require_customer();
    }
}
