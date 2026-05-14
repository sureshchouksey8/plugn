<?php

$requiredEnv = static function (string $name): string {
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException("Set {$name} before booting prod-railway common config.");
    }

    return $value;
};

$requiredIntEnv = static function (string $name) use ($requiredEnv): int {
    $value = $requiredEnv($name);
    if (!ctype_digit($value)) {
        throw new RuntimeException("Set {$name} to an integer before booting prod-railway common config.");
    }

    return (int) $value;
};

return [
    'components' => [
        'db' => [
            'class' => 'yii\db\Connection',
            'dsn' => $requiredEnv('PLUGN_RAILWAY_DB_DSN'),
            'username' => $requiredEnv('PLUGN_RAILWAY_DB_USERNAME'),
            'password' => $requiredEnv('PLUGN_RAILWAY_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            // Enable Caching of Schema to Reduce SQL Queries
            'enableSchemaCache' => true,
            // Duration of schema cache.
            'schemaCacheDuration' => 60, // 1 minute
            // Name of the cache component used to store schema information
            'schemaCache' => 'cache',
        ],
        'eventManager' => [
            'class' => 'common\components\EventManager',
            "sqsRagion" => $requiredEnv('PLUGN_RAILWAY_SQS_REGION'),
            "sqsKey" => $requiredEnv('PLUGN_RAILWAY_SQS_KEY'),
            "sqsSecret" => $requiredEnv('PLUGN_RAILWAY_SQS_SECRET'),
            "sqsQueue" => $requiredEnv('PLUGN_RAILWAY_SQS_QUEUE')
        ],
        'walletManager' => [
            'class' => 'common\components\WalletManager',
            'apiKey' => $requiredEnv('PLUGN_WALLET_API_KEY'),
            'apiEndpoint' => $requiredEnv('PLUGN_WALLET_API_ENDPOINT'),
        ],
        'resourceManager' => [
            'class' => 'common\components\S3ResourceManager',
            'authMethod' => \common\components\S3ResourceManager::AUTH_VIA_KEY_AND_SECRET,
            'region' => $requiredEnv('PLUGN_RAILWAY_S3_REGION'),
            'bucket' => $requiredEnv('PLUGN_RAILWAY_S3_BUCKET'),
            'key' => $requiredEnv('PLUGN_RAILWAY_S3_KEY'),
            'secret' => $requiredEnv('PLUGN_RAILWAY_S3_SECRET'),
            /**
             * For Local Development, we access using key and secret
             * For Dev and Production servers, access is via server embedded IAM roles so no key/secret required
             *
             * You can access the bucket with:
             * https://plugn-uploads.s3.amazonaws.com/
             * https://plugn-uploads.s3.amazonaws.com/folderName/fileName.jpg
             */
        ],
        'log' => [
            'targets' => [
                [
                    'class' => 'notamedia\sentry\SentryTarget',
                    'dsn' => $requiredEnv('PLUGN_SENTRY_DSN'),
                    'levels' => ['error', 'warning'],
                    'except' => [
                        'yii\web\BadRequestHttpException',
                        'yii\web\UnauthorizedHttpException',
                        'yii\web\NotFoundHttpException',
                        'yii\web\HttpException:400',
                        'yii\web\HttpException:401',
                        'yii\web\HttpException:404',
                    ],
                    'clientOptions' => [
                        //which environment are we running this on?
                        'environment' => 'production',
                    ],
                    'context' => true // Write the context information. The default is true.
                ],
               /* [
                    'class' => 'common\components\SlackLogger',
                    'logVars' => [],
                    'levels' => [ 'warning','error'],
                    'categories' => ['backend\*', 'frontend\*', 'common\*', 'console\*','crm\*','api\*','agent\*'],
                ],*/
                [
                    'class' => 'common\components\SlackLogger',
                    'logVars' => [],
                    'levels' => ['info', 'warning','error'],
                    'categories' => ['backend\*', 'frontend\*', 'common\*', 'console\*','crm\*','api\*','agent\*'],
                ],
            ],
        ],
        'redis' => [
            'class' => 'yii\redis\Connection',
            'hostname' => $requiredEnv('PLUGN_REDIS_HOST'),
            'username' => $requiredEnv('PLUGN_REDIS_USERNAME'),
            'password' => $requiredEnv('PLUGN_REDIS_PASSWORD'),
            'port' => $requiredIntEnv('PLUGN_REDIS_PORT'),
            'database' => $requiredIntEnv('PLUGN_REDIS_DATABASE'),
        ],
        'cache' => [
            'class' => 'yii\redis\Cache',
            //'class' => 'yii\caching\FileCache',
        ],
        //aws
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@common/mail',
            'transport' => [
                'scheme' => 'smtp',
                'host' => $requiredEnv('PLUGN_SMTP_HOST'),
                'username' => $requiredEnv('PLUGN_SMTP_USERNAME'),
                'password' => $requiredEnv('PLUGN_SMTP_PASSWORD'),
                'port' => $requiredIntEnv('PLUGN_SMTP_PORT'),
            ]
        ],
        'tapPayments' => [
            'gatewayToUse' => \common\components\TapPayments::USE_LIVE_GATEWAY,
        ],
        'myFatoorahPayment' => [
            'gatewayToUse' => \common\components\MyFatoorahPayment::USE_LIVE_GATEWAY
        ],
       'armadaDelivery' => [
            'keyToUse' => \common\components\ArmadaDelivery::USE_LIVE_KEY,
        ],
        'mashkorDelivery' => [
            'class' => 'common\components\MashkorDelivery',
            'keyToUse' => \common\components\MashkorDelivery::USE_LIVE_KEY,
        ],
        'githubComponent' => [
            'class' => 'common\components\GithubComponent',
            'branch' => 'master'
        ],
        'apiUrlManager' => [
            'class' => 'yii\web\UrlManager',
            'baseUrl' => 'https://api.plugn.io',
            'enablePrettyUrl' => false,
            'showScriptName' => false,
        ],
        'agentApiUrlManager' => [
            'class' => 'yii\web\UrlManager',
            'baseUrl' => 'https://agent.plugn.io',
            'enablePrettyUrl' => false,
            'showScriptName' => false,
        ],
        //microservices todo: for docker 
        'blogManager' => [
            'class' => 'common\components\BlogManager',
            'apiEndpoint' => $requiredEnv('PLUGN_BLOG_MANAGER_API_ENDPOINT'),
            'token' => $requiredEnv('PLUGN_BLOG_MANAGER_TOKEN')
        ],
        'gpt' => [
            'class' => 'common\components\GptComponent',
            'token' => $requiredEnv('PLUGN_GPT_TOKEN'),
            'apiEndpoint' => $requiredEnv('PLUGN_GPT_API_ENDPOINT')
        ],
    ],
];
