<?php

require_once __DIR__ . '/../customer/traits/Current_customer.php';

class Provider_model extends Model {
    use Current_customer;

    function has_hetzner(int $customer_id): bool {
        $this->module('secrets');
        return $this->secrets->exists('customer', $customer_id, 'hetzner');
    }

    function get_hetzner(int $customer_id): array {
        $this->module('secrets');
        return $this->secrets->get('customer', $customer_id, 'hetzner');
    }

    function save_hetzner(
        int $customer_id,
        string $token,
        string $ssh_key_id,
        string $ssh_key_label,
        array $ssh_key_ids = []
    ): void {
        $this->module('secrets');
        $this->secrets->save('customer', $customer_id, 'hetzner', [
            'token'         => $token,
            'ssh_key_id'    => $ssh_key_id,
            'ssh_key_label' => $ssh_key_label,
            'ssh_key_ids'   => array_values(array_unique(array_filter($ssh_key_ids))),
        ]);
    }

    function delete_hetzner(int $customer_id): void {
        $this->module('secrets');
        $this->secrets->delete('customer', $customer_id, 'hetzner');
    }
}
