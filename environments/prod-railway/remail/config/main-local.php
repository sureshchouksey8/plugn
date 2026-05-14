<?php

$cookieValidationKey = require __DIR__ . '/../../common/config/cookie-validation-key.php';

$config = [
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => $cookieValidationKey('remail'),
        ],
    ],
];

return $config;
