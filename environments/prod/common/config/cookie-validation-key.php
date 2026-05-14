<?php

return static function (string $application): string {
    $applicationVariables = [
        'api' => 'PLUGN_API_COOKIE_VALIDATION_KEY',
        'frontend' => 'PLUGN_FRONTEND_COOKIE_VALIDATION_KEY',
        'backend' => 'PLUGN_BACKEND_COOKIE_VALIDATION_KEY',
        'partner' => 'PLUGN_PARTNER_COOKIE_VALIDATION_KEY',
        'agent' => 'PLUGN_AGENT_COOKIE_VALIDATION_KEY',
        'crm' => 'PLUGN_CRM_COOKIE_VALIDATION_KEY',
        'shortner' => 'PLUGN_SHORTNER_COOKIE_VALIDATION_KEY',
        'remail' => 'PLUGN_REMAIL_COOKIE_VALIDATION_KEY',
    ];

    if (!array_key_exists($application, $applicationVariables)) {
        throw new InvalidArgumentException(sprintf('Unknown production app "%s".', $application));
    }

    $applicationVariable = $applicationVariables[$application];
    $fallbackVariable = 'PLUGN_COOKIE_VALIDATION_KEY';

    $cookieValidationKey = getenv($applicationVariable);
    if ($cookieValidationKey === false || $cookieValidationKey === '') {
        $cookieValidationKey = getenv($fallbackVariable);
    }

    if ($cookieValidationKey === false || $cookieValidationKey === '') {
        throw new RuntimeException(
            sprintf(
                'Set %s or %s before booting the %s production app.',
                $applicationVariable,
                $fallbackVariable,
                $application
            )
        );
    }

    return $cookieValidationKey;
};
