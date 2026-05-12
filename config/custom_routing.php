<?php
$routes = [
    'tg-admin'              => 'trongate_administrators/login',
    'tg-admin/submit_login' => 'trongate_administrators/submit_login',
    'login'                 => 'customer/login',
    'register'              => 'customer/register',
    'logout'                => 'customer/logout',
    'onboarding'            => 'customer-onboarding',
    'environment'           => 'deployment-environment',
    'server'                => 'deployment-server',
    'environment-services'  => 'deployment-services',
    'server-health'         => 'deployment-health',
];
define('CUSTOM_ROUTES', $routes);