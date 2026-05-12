<?php

trait Emits_events {

    private function _emit(string $event_type, string $entity_type, int $entity_id, array $payload = []): void {
        try {
            $customer_id = (int) ($_SESSION['customer_id'] ?? 0);
            $this->module('deployment-event');
            $this->event->model->emit($event_type, $customer_id, $entity_type, $entity_id, $payload);
        } catch (Throwable) {
            // Event log failure must never break the primary operation
        }
    }
}
