<?php

class Provider extends Trongate {

    function index(): void {
        $customer = $this->_require_customer();

        $has_hetzner = $this->model->has_hetzner((int) $customer->id);
        $hetzner     = $has_hetzner ? $this->model->get_hetzner((int) $customer->id) : [];

        $data = [
            'view_module'  => 'provider',
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
            'view_module'   => 'provider',
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

        // Upload customer SSH key to Hetzner
        $label  = 'provision-' . substr(md5($customer->email), 0, 8);
        $key_id = '';

        if (!empty($customer->ssh_public_key)) {
            try {
                $key_id = $hetzner->upload_ssh_key($label, $customer->ssh_public_key);
            } catch (Client_Error $e) {
                if ($e->getCode() === 409) {
                    // Key already exists — find it by fingerprint
                    foreach ($hetzner->list_ssh_keys() as $key) {
                        $stored = preg_split('/\s+/', trim($key['public_key']));
                        $input  = preg_split('/\s+/', trim($customer->ssh_public_key));
                        if (isset($stored[1], $input[1]) && $stored[1] === $input[1]) {
                            $key_id = (string) $key['id'];
                            $label  = $key['name'];
                            break;
                        }
                    }
                } else {
                    throw $e;
                }
            }
        }

        $this->model->save_hetzner((int) $customer->id, $token, $key_id, $label);
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
}
