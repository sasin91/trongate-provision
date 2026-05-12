<?php

class Provider extends Trongate {

    function index(): void {
        $customer = $this->_require_customer();

        $has_hetzner = $this->model->has_hetzner((int) $customer->id);
        $hetzner     = $has_hetzner ? $this->model->get_hetzner((int) $customer->id) : [];

        $data = [
            'view_module' => 'deployment/provider',
            'view_file'    => 'index',
            'page_title'   => 'Providers',
            'current_email'=> $customer->email,
            'has_hetzner'  => $has_hetzner,
            'hetzner'      => $hetzner,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function connect(): void {
        $customer = $this->_require_customer();

        $data = [
            'view_module' => 'deployment/provider',
            'view_file'     => 'connect',
            'page_title'    => 'Connect Hetzner Cloud',
            'current_email' => $customer->email,
            'form_location' => 'provider/submit_connect',
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function submit_connect(): void {
        $customer = $this->_require_customer();

        $this->validation->set_rules('token', 'API Token', 'required|callback_validate_hetzner_token');

        if ($this->validation->run() !== true) {
            $this->connect();
            return;
        }

        $token = post('token', true);

        $this->module('cloud');
        $hetzner = $this->cloud->hetzner($token);

        $ssh_key_ids = [];
        $key_id = '';
        $label = '';

        $runner_public_key = $this->_runner_public_key();
        if ($runner_public_key === '') {
            $_SESSION['flash_error'] = 'Runner SSH public key not found. Create ' . RUNNER_SSH_KEY . '.pub before connecting Hetzner.';
            redirect('provider/connect');
            return;
        }

        $runner_label = 'provision-runner-' . substr(md5(BASE_URL), 0, 8);
        $runner_key = $hetzner->ensure_ssh_key($runner_label, $runner_public_key);
        $key_id = $runner_key['id'];
        $label = $runner_key['name'];
        $ssh_key_ids[] = $runner_key['id'];

        if (!empty($customer->ssh_public_key)) {
            $customer_label = 'provision-customer-' . substr(md5($customer->email), 0, 8);
            $customer_key = $hetzner->ensure_ssh_key($customer_label, $customer->ssh_public_key);
            $ssh_key_ids[] = $customer_key['id'];
        }

        $this->model->save_hetzner((int) $customer->id, $token, $key_id, $label, $ssh_key_ids);
        $_SESSION['flash_success'] = 'Hetzner Cloud connected.' . ($key_id ? ' SSH key uploaded.' : '');
        redirect('provider');
    }

    function disconnect(): void {
        $customer = $this->_require_customer();

        $this->validation->set_rules('dummy', 'dummy', 'max_length[1]');
        if ($this->validation->run() !== true) {
            redirect('provider');
            return;
        }

        $this->model->delete_hetzner((int) $customer->id);
        $_SESSION['flash_success'] = 'Hetzner Cloud disconnected.';
        redirect('provider');
    }

    // ── Validation callbacks ──────────────────────────────────────

    function validate_hetzner_token(string $token): bool|string {
        if (empty($token)) return 'API Token is required.';

        $this->module('cloud');
        $client = $this->cloud->hetzner($token);

        if (!$client->validate_credentials()) {
            return 'Invalid API token. Please check your Hetzner Cloud API key.';
        }

        return true;
    }

    // ── Auth ─────────────────────────────────────────────────────

    private function _require_customer(): object {
        $this->module('customer');
        $this->customer->_require_onboarded();
        return $this->customer->_require_customer();
    }

    private function _runner_public_key(): string {
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
}
