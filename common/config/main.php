<?php

$env = function ($name, $default = null) {
    $value = getenv($name);
    return $value === false ? $default : $value;
};

return [
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
    ],
    'name' => 'Plugn',
    'vendorPath' => dirname(dirname(__DIR__)) . '/vendor',
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        /*   'redis' => [
               'class' => 'yii\redis\Connection',
               'hostname' => 'localhost',
               'port' => 6379,
               'database' => 0,
           ],
           'cache' => [
               'class' => 'yii\redis\Cache',
               'redis' => [
                   'hostname' => 'localhost',
                   'port' => 6379,
                   'database' => 0,
               ]
           ],*/
        'formatter' => [
        'thousandSeparator' => ',',
        'decimalSeparator' => '.',
            'defaultTimeZone' => 'Asia/Kuwait',
            'timeZone' => 'Asia/Kuwait',
            'timeFormat' => 'h:i:s'
        ],
        'i18n' => [
            'translations' => [
                'api' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@common/messages',
                    'sourceLanguage' => 'en',
                ],
                '*' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@common/messages',
                    'sourceLanguage' => 'en',
                ],
                'app' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@common/messages',
                    'sourceLanguage' => 'en',
                ],
                'yii' => [
                    'class' => 'yii\i18n\PhpMessageSource',
                    'basePath' => '@common/messages',
                    'sourceLanguage' => 'en',
                ],
            ],
        ],
        'cloudinaryManager' => [
            'class' => 'common\components\CloudinaryManager',
            'cloud_name' => $env('CLOUDINARY_CLOUD_NAME', 'plugn'),
            'api_key' => $env('CLOUDINARY_API_KEY'),
            'api_secret' => $env('CLOUDINARY_API_SECRET')
        ],
        'config' => [
            'class' => 'common\components\Config'
        ],
        'zapier' => [
            'class' => 'common\components\Zapier'
        ],
        "applePay" => [
            'class' => 'common\components\ApplePay',
        ],
        'ipstack' => [
            'class' => 'common\components\Ipstack',
            'accessKey' => $env('IPSTACK_ACCESS_KEY')
            //fac3c2117d877e078e3e8fa7839d8204
        ],
        'reCaptcha' => [
            'class' => 'common\components\ReCaptcha',
            'secretKey' => $env('RECAPTCHA_SECRET_KEY'),
        ],
        'auth0' => [
            'class' => 'common\components\Auth0',
        ],
        'temporaryBucketResourceManager' => [
            'class' => 'common\components\S3ResourceManager',
            'region' => 'eu-west-2', // Bucket based in London
            'key' => $env('TEMPORARY_BUCKET_ACCESS_KEY_ID'),
            'secret' => $env('TEMPORARY_BUCKET_SECRET_ACCESS_KEY'),
            'bucket' => $env('TEMPORARY_BUCKET_NAME', 'plugn-public-anyone-can-upload-24hr-expiry')
            /**
             * You can access the Temporary bucket with:
             * https://pogi-public-anyone-can-upload-24hr-expiry.s3.amazonaws.com/
             * https://pogi-public-anyone-can-upload-24hr-expiry.s3.amazonaws.com/folderName/fileName.jpg
             */
        ],
        'accountManager' => [//Component for agent to manage Restaurant
            'class' => 'common\components\AccountManager',
        ],
        'tapPayments' => [
            'class' => 'common\components\TapPayments',
            'gatewayToUse' => \common\components\TapPayments::USE_LIVE_GATEWAY,
            'plugnLiveApiKey' => $env('TAP_PLUGN_LIVE_API_KEY'),
            'plugnTestApiKey' => $env('TAP_PLUGN_TEST_API_KEY'),
            'destinationId' => $env('TAP_PLUGN_DESTINATION_ID'),
        ],
        'myFatoorahPayment' => [
            'class' => 'common\components\MyFatoorahPayment',
            'gatewayToUse' => \common\components\MyFatoorahPayment::USE_LIVE_GATEWAY,
            'kuwaitLiveApiKey' => $env('MYFATOORAH_KUWAIT_LIVE_API_KEY'),
            'kuwaitTestApiKey' => $env('MYFATOORAH_KUWAIT_TEST_API_KEY'),
            'saudiLiveApiKey' => $env('MYFATOORAH_SAUDI_LIVE_API_KEY')
        ],
        'armadaDelivery' => [
            'class' => 'common\components\ArmadaDelivery',
            'keyToUse' => \common\components\ArmadaDelivery::USE_LIVE_KEY,
        ],
        'smsComponent' => [
            'class' => 'common\components\SmsComponent'
        ],
        'fileGeneratorComponent' => [
            'class' => 'common\components\FileGeneratorComponent'
        ],
        'mashkorDelivery' => [
            'class' => 'common\components\MashkorDelivery',
            'keyToUse' => \common\components\MashkorDelivery::USE_LIVE_KEY
        ],
        'googleMapComponent' => [
            'class' => 'common\components\GoogleMapComponent',
            'token' => $env('GOOGLE_MAPS_API_KEY')
        ],
        'netlifyComponent' => [
            'class' => 'common\components\NetlifyComponent',
            'token' => $env('NETLIFY_TOKEN')
        ],
        'githubComponent' => [
            'class' => 'common\components\GithubComponent',
            'token' => $env('PLUGN_GITHUB_TOKEN'),
            'branch' => 'master'
        ],
        'slack' => [
            'class' => 'understeam\slack\Client',
            'url' => $env('SLACK_ACTIVITY_WEBHOOK_URL'),
            // plugn-activity
            'username' => 'Plugn',
        ],
        'slackTapOperation' => [
            'class' => 'understeam\slack\Client',
            'url' => $env('SLACK_TAP_OPERATION_WEBHOOK_URL'),
            'username' => 'Plugn',
        ],
        'slackError' => [
            'class' => 'understeam\slack\Client',
            'url' => $env('SLACK_ERROR_WEBHOOK_URL'),
            // plugn-errors-backend
            'username' => 'Plugn',
        ],
        'httpclient' => [
            'class' => 'yii\httpclient\Client',
        ],
        'jwt' => [
            'class' => 'common\components\JWT'
        ],
    ],
];
