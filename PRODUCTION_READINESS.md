# Production readiness checks

Production Yii apps require a non-empty cookie validation key before they boot. Set one shared key with `PLUGN_COOKIE_VALIDATION_KEY`, or set app-specific keys when each app should rotate independently:

* `PLUGN_API_COOKIE_VALIDATION_KEY`
* `PLUGN_FRONTEND_COOKIE_VALIDATION_KEY`
* `PLUGN_BACKEND_COOKIE_VALIDATION_KEY`
* `PLUGN_PARTNER_COOKIE_VALIDATION_KEY`
* `PLUGN_AGENT_COOKIE_VALIDATION_KEY`
* `PLUGN_CRM_COOKIE_VALIDATION_KEY`
* `PLUGN_SHORTNER_COOKIE_VALIDATION_KEY`
* `PLUGN_REMAIL_COOKIE_VALIDATION_KEY`

Run these checks before deploying production config changes:

```bash
bash tests/check-prod-cookie-validation-env.sh
bash tests/check-prod-api-debug-disabled.sh
```
