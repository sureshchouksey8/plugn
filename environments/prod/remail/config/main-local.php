<?php

$cookieValidationKey = require __DIR__ . '/../../common/config/cookie-validation-key.php';

$config = [
    'components' => [
        'request' => [
            'cookieValidationKey' => $cookieValidationKey('remail'),
        ],
    ],
];

return $config;
