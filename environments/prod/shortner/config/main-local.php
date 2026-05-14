<?php
$cookieValidationKey = require __DIR__ . '/../../common/config/cookie-validation-key.php';

return [
    'components' => [
        'request' => [
            'cookieValidationKey' => $cookieValidationKey('shortner'),
        ],
         'urlManager' => [
            'class' => 'yii\web\UrlManager',
            'baseUrl' => 'https://admin.plugn.io',
            'enablePrettyUrl' => true,
            'showScriptName' => false,
        ],
    ],
];
