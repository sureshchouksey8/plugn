<?php

$cookieValidationKey = require __DIR__ . '/../../common/config/cookie-validation-key.php';

return [
    'components' => [
        'request' => [
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
                'hostname' => 'plugn-redis.0x1cgp.0001.euw2.cache.amazonaws.com',
                'port' => 6379,
                'database' => 3,
            ]
        ],
    ],
];
