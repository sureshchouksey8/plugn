<?php

$cookieValidationKey = require __DIR__ . '/../../common/config/cookie-validation-key.php';

return [
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
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
