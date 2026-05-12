<?php

class Cloud extends Trongate {

    function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        block_url('cloud');
    }

    function hetzner(string $token): Hetzner {
        require_once APPPATH . 'modules/deployment/http/client/Client.php';
        require_once __DIR__ . '/clients/Hetzner.php';
        return new Hetzner($token);
    }
}
