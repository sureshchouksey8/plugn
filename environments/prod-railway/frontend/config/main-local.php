<?php

$cookieValidationKey = require __DIR__ . '/../../common/config/cookie-validation-key.php';

return [
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => $cookieValidationKey('frontend'),
        ],
        'urlManager' => [
            'class' => 'yii\web\UrlManager',
            'baseUrl' => 'https://dashboard.plugn.io',
            'enablePrettyUrl' => true,
            'showScriptName' => false,
        ],
        'session' => [
            // Use Redis as a cache
            'class' => 'yii\redis\Session',
            'redis' => [
                'class' => 'yii\redis\Connection',
                'hostname' => 'redis-xkt_.railway.internal',
                'username' => 'default',
                'password' => 'BGtjhtRKQJvAirawTCZjYrjwRrQAGFBS',
                'port' => 6379,
                'database' => 0,
            ]
        ],
    ],
];
