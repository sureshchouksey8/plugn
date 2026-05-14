<?php

$cookieValidationKey = require __DIR__ . '/../../common/config/cookie-validation-key.php';

$config = [
    'components' => [
        'request' => [
            'cookieValidationKey' => $cookieValidationKey('crm'),
        ],
    ],
];

if (YII_DEBUG) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        'allowedIPs' => ['*'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
