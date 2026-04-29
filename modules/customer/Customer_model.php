<?php

require_once __DIR__ . '/traits/Current_customer.php';

class Customer_model extends Model {
    use Current_customer;

    private string $table = 'customer';
    private int $max_failed_attempts = 3;
    private int $block_seconds = 900;

    function _find_one(string $column, $value): object|false {
        $rows = $this->db->query_bind(
            "SELECT * FROM {$this->table} WHERE {$column} = :{$column} LIMIT 1",
            [$column => $value],
            'object'
        );
        return $rows[0] ?? false;
    }

    function email_exists(string $email): bool {
        $rows = $this->db->query_bind(
            "SELECT 1 FROM {$this->table} WHERE email = :email LIMIT 1",
            ['email' => $email],
            'column'
        );
        return (bool) $rows;
    }

    function validate_credentials(string $email, string $password): bool {
        $user = $this->_find_one('email', $email);
        if ($user === false || empty($user->password)) return false;
        return password_verify($password, $user->password) && (int) $user->active === 1;
    }

    function remove_expired_restrictions(): void {
        $rows = $this->db->query_bind(
            "SELECT id FROM {$this->table} WHERE login_blocked_until > 1000 AND login_blocked_until < :now",
            ['now' => time()],
            'object'
        );
        foreach ($rows as $row) {
            $this->db->update((int) $row->id, [
                'failed_login_attempts' => 0,
                'last_failed_attempt'   => 0,
                'login_blocked_until'   => 0,
                'failed_login_ip'       => '',
            ], $this->table);
        }
    }

    function is_login_attempt_allowed(): bool {
        $rows = $this->db->query_bind(
            "SELECT id FROM {$this->table} WHERE failed_login_ip = :ip AND failed_login_attempts >= :max AND login_blocked_until > :now LIMIT 1",
            ['ip' => ip_address(), 'max' => $this->max_failed_attempts, 'now' => time()],
            'object'
        );
        return empty($rows);
    }

    function increment_failed_login_attempts(string $email): bool {
        $user = $this->_find_one('email', $email);
        if ($user === false) return false;

        $attempts = (int) $user->failed_login_attempts + 1;
        $data = [
            'failed_login_attempts' => $attempts,
            'last_failed_attempt'   => time(),
            'failed_login_ip'       => ip_address(),
        ];

        if ($attempts >= $this->max_failed_attempts) {
            $data['login_blocked_until'] = time() + $this->block_seconds;
        }

        $this->db->update((int) $user->id, $data, $this->table);
        return $attempts >= $this->max_failed_attempts;
    }

    function log_user_in(string $email, int $remember = 0): string|false {
        $this->module('trongate_tokens');
        $user = $this->_find_one('email', $email);
        if ($user === false) return false;

        $token_data = ['user_id' => (int) $user->trongate_user_id];

        if ($remember) {
            $token_data['expiry_date'] = time() + 86400 * 30;
            $token = $this->trongate_tokens->generate_token($token_data);
            setcookie('trongatetoken', $token, $token_data['expiry_date'], '/');
        } else {
            $token = $this->trongate_tokens->generate_token($token_data);
            $_SESSION['trongatetoken'] = $token;
        }

        return $token;
    }

    function after_login_tasks(string $email): void {
        $user = $this->_find_one('email', $email);
        if ($user === false) return;

        $this->db->update((int) $user->id, [
            'failed_login_attempts' => 0,
            'last_failed_attempt'   => 0,
            'login_blocked_until'   => 0,
            'failed_login_ip'       => '',
        ], $this->table);
    }

    function create_account(array $data): int|false {
        $trongate_user_id = $this->db->insert(
            ['code' => make_rand_str(32), 'user_level_id' => 2],
            'trongate_users'
        );
        if (!$trongate_user_id) return false;

        return $this->db->insert([
            'trongate_user_id' => $trongate_user_id,
            'email'            => $data['email'],
            'password'         => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 11]),
            'active'           => 1,
        ], $this->table);
    }

    function count_environments(int $customer_id): int {
        $rows = $this->db->query_bind(
            "SELECT COUNT(*) as n FROM environment WHERE customer_id = :id",
            ['id' => $customer_id],
            'object'
        );
        return (int) ($rows[0]->n ?? 0);
    }

    function count_servers(int $customer_id): int {
        $rows = $this->db->query_bind(
            "SELECT COUNT(*) as n FROM server WHERE customer_id = :id",
            ['id' => $customer_id],
            'object'
        );
        return (int) ($rows[0]->n ?? 0);
    }

    function count_deployments(int $customer_id): int {
        $rows = $this->db->query_bind(
            "SELECT COUNT(*) as n FROM deployment WHERE customer_id = :id",
            ['id' => $customer_id],
            'object'
        );
        return (int) ($rows[0]->n ?? 0);
    }

    function recent_deployments(int $customer_id, int $limit = 5): array {
        return $this->db->query_bind(
            "SELECT d.*, s.name as server_name, s.ip_address
             FROM deployment d
             JOIN server s ON d.server_id = s.id
             WHERE d.customer_id = :id
             ORDER BY d.created_at DESC LIMIT :lim",
            ['id' => $customer_id, 'lim' => $limit],
            'object'
        );
    }
}
