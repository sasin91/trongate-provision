<?php

class Event_model extends Model {

    function emit(string $event_type, int $customer_id, string $entity_type, int $entity_id, array $payload): void {
        $this->db->insert([
            'event_type'  => $event_type,
            'customer_id' => $customer_id,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'payload'     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ], 'event_log');
    }

    function for_entity(string $entity_type, int $entity_id, int $customer_id, int $limit = 50): array {
        return $this->db->query_bind(
            "SELECT * FROM event_log
             WHERE entity_type = :type AND entity_id = :eid AND customer_id = :cid
             ORDER BY created_at DESC, id DESC LIMIT :lim",
            ['type' => $entity_type, 'eid' => $entity_id, 'cid' => $customer_id, 'lim' => $limit],
            'object'
        ) ?: [];
    }

    function for_customer(int $customer_id, int $limit = 100, string $filter = ''): array {
        if ($filter !== '') {
            return $this->db->query_bind(
                "SELECT * FROM event_log
                 WHERE customer_id = :cid AND entity_type = :filter
                 ORDER BY created_at DESC, id DESC LIMIT :lim",
                ['cid' => $customer_id, 'filter' => $filter, 'lim' => $limit],
                'object'
            ) ?: [];
        }
        return $this->db->query_bind(
            "SELECT * FROM event_log
             WHERE customer_id = :cid
             ORDER BY created_at DESC, id DESC LIMIT :lim",
            ['cid' => $customer_id, 'lim' => $limit],
            'object'
        ) ?: [];
    }

    function recent_for_entity(string $entity_type, int $entity_id, int $customer_id, int $limit = 5): array {
        return $this->for_entity($entity_type, $entity_id, $customer_id, $limit);
    }
}
