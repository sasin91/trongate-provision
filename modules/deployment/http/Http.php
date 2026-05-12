<?php
class Http extends Trongate {

    /**
     * Decode the raw JSON request body.
     * Returns [] on empty input, parse error, or non-object root.
     */
    public function json_request(): array {
        $raw     = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Send a JSON response (sets header + echoes encoded payload).
     */
    public function json_response(array $payload, int $status = 200, int $flags = 0): void {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, $flags);
    }
}
