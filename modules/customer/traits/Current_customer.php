<?php

trait Current_customer {

    function _get_current_customer(): object|false {
        $customer_id = $_SESSION['customer_id'] ?? null;

        if ($customer_id === null) {
            $this->module('trongate_tokens');
            $trongate_user_id = $this->trongate_tokens->get_user_id();

            if ($trongate_user_id === false) {
                return false;
            }

            $rows = $this->db->query_bind(
                "SELECT id FROM customer WHERE active = 1 AND trongate_user_id = :trongate_user_id LIMIT 1",
                ['trongate_user_id' => $trongate_user_id],
                'object'
            );

            if (empty($rows)) {
                return false;
            }

            $customer_id = (int) $rows[0]->id;
            $_SESSION['customer_id'] = $customer_id;
        }

        $rows = $this->db->query_bind(
            "SELECT * FROM customer WHERE id = :id AND active = 1 LIMIT 1",
            ['id' => $customer_id],
            'object'
        );

        return $rows[0] ?? false;
    }
}
