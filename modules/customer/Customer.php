<?php

require_once __DIR__ . '/../event/Emits_events.php';

class Customer extends Trongate {

    use Emits_events;
    const CUSTOMER_LEVEL = 2;

    private ?object $auth_customer = null;
    private $auth_token = null;

    function index(): void {
        $this->_require_onboarded();
        $customer = $this->_require_customer();

        $this->module('server');
        $servers = $this->server->model->all_with_health((int) $customer->id);

        $data = [
            'view_module'   => 'customer',
            'view_file'     => 'dashboard',
            'page_title'    => 'Dashboard',
            'current_email' => $customer->email,
            'servers'       => $servers,
        ];

        $this->module('templates');
        $this->templates->customer($data);
    }

    function login(): void {
        $this->module('trongate_tokens');
        $this->trongate_tokens->destroy();
        $this->model->remove_expired_restrictions();

        if (!$this->model->is_login_attempt_allowed()) {
            redirect('customer/not_allowed');
        }

        $data['form_location'] = 'customer/submit_login';
        $this->view('login', $data);
    }

    function submit_login(): void {
        $this->validation->set_rules('email',    'email',    'required|valid_email|callback_login_check');
        $this->validation->set_rules('password', 'password', 'required|min_length[5]');

        $email = post('email', true);

        if ($this->validation->run() === true) {
            $remember = (int)(bool) post('remember', true);
            $this->_do_login($email, $remember);
        } else {
            $blocked = $this->model->increment_failed_login_attempts($email);
            $this->_emit('CustomerLoginFailed', 'customer', 0, [
                'email' => $email,
                'ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            if ($blocked) {
                unset($_SESSION['form_submission_errors']);
                redirect('customer/not_allowed');
            }
            $this->login();
        }
    }

    function login_check(string $email): bool|string {
        $password = post('password');
        return $this->model->validate_credentials($email, $password)
            ? true
            : 'Incorrect email or password.';
    }

    function register(): void {
        $data['form_location'] = 'customer/submit_register';
        $this->view('register', $data);
    }

    function submit_register(): void {
        $this->validation->set_rules('email',            'email',           'required|valid_email|max_length[255]|callback_email_check');
        $this->validation->set_rules('password',         'password',        'required|min_length[6]|max_length[60]');
        $this->validation->set_rules('password_confirm', 'repeat password', 'required|matches[password]');

        if ($this->validation->run() === true) {
            $this->model->create_account([
                'email'    => post('email', true),
                'password' => post('password'),
            ]);
            $this->_do_login(post('email', true), 0);
        } else {
            $this->register();
        }
    }

    function email_check(string $email): bool|string {
        return $this->model->email_exists($email) ? 'That email is already registered.' : true;
    }

    function not_allowed(): void {
        http_response_code(403);
        echo '<p style="font-family:sans-serif;padding:2rem">Too many failed login attempts. Please wait 15 minutes before trying again.</p>';
    }

    function logout(): void {
        $this->module('trongate_tokens');
        $this->trongate_tokens->destroy();
        unset($_SESSION['customer_id']);
        redirect('customer/login');
    }

    private function _do_login(string $email, int $remember): void {
        $token = $this->model->log_user_in($email, $remember);
        if ($token === false) {
            http_response_code(500);
            die('Login error.');
        }
        $customer = $this->model->_find_one('email', $email);
        if ($customer !== false) {
            $_SESSION['customer_id'] = (int) $customer->id;
            $this->_emit('CustomerLoggedIn', 'customer', (int) $customer->id, [
                'remember' => (bool) $remember,
            ]);
        }
        $this->model->after_login_tasks($email);
        redirect('customer');
    }

    function intercept(): void {
        if (strpos(ASSUMED_URL, MODULE_ASSETS_TRIGGER) !== false) {
            return;
        }

        $module = SEGMENTS[1] ?? '';
        $method = SEGMENTS[2] ?? DEFAULT_METHOD;
        $method = remove_query_string($method);

        switch ($module) {
            case 'customer-onboarding':
                return;
            case 'customer':
                if (in_array($method, ['login', 'submit_login', 'register', 'submit_register', 'logout', 'not_allowed'], true)) {
                    return;
                }
                break;
            case 'server':
                if (in_array($method, ['stream','server_types_options'], true)) {
                    return;
                }
                break;
            case 'deployment':
                if ($method === 'stream') {
                    return;
                }
                break;
        }

        $this->module('trongate_tokens');
        $token = $this->trongate_tokens->attempt_get_valid_token(self::CUSTOMER_LEVEL);
        if ($token === false) return;

        $customer = $this->model->_get_current_customer();
        if ($customer === false) return;

        $this->auth_customer = $customer;
        $this->auth_token = $token;
        $this->_require_onboarded();
    }

    function _require_auth(): array {
        if ($this->auth_customer !== null && $this->auth_token !== null) {
            return [$this->auth_customer, $this->auth_token];
        }

        $this->module('trongate_tokens');
        $token = $this->trongate_tokens->attempt_get_valid_token(self::CUSTOMER_LEVEL);
        if ($token === false) {
            redirect('customer/login');
        }
        $customer = $this->model->_get_current_customer();
        if ($customer === false) {
            redirect('customer/login');
        }

        $this->auth_customer = $customer;
        $this->auth_token = $token;

        return [$customer, $token];
    }

    function _require_customer(): object {
        return $this->_require_auth()[0];
    }

    function _require_onboarded(): void {
        $customer = $this->_require_customer();
        if (empty($customer->onboarded_at)) {
            redirect('customer-onboarding');
        }
    }
}
