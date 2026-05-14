# Production readiness checks

Production Yii apps in `prod`, `prod-docker`, and `prod-railway` require a non-empty cookie validation key before they boot. Set one shared key with `PLUGN_COOKIE_VALIDATION_KEY`, or set app-specific keys when each app should rotate independently:

* `PLUGN_API_COOKIE_VALIDATION_KEY`
* `PLUGN_FRONTEND_COOKIE_VALIDATION_KEY`
* `PLUGN_BACKEND_COOKIE_VALIDATION_KEY`
* `PLUGN_PARTNER_COOKIE_VALIDATION_KEY`
* `PLUGN_AGENT_COOKIE_VALIDATION_KEY`
* `PLUGN_CRM_COOKIE_VALIDATION_KEY`
* `PLUGN_SHORTNER_COOKIE_VALIDATION_KEY`
* `PLUGN_REMAIL_COOKIE_VALIDATION_KEY`

The `prod-railway` common config also requires environment-provided database, SQS, S3, Sentry, Redis, SMTP, wallet, blog manager, and GPT settings before boot:

* `PLUGN_RAILWAY_DB_DSN`
* `PLUGN_RAILWAY_DB_USERNAME`
* `PLUGN_RAILWAY_DB_PASSWORD`
* `PLUGN_RAILWAY_SQS_REGION`
* `PLUGN_RAILWAY_SQS_KEY`
* `PLUGN_RAILWAY_SQS_SECRET`
* `PLUGN_RAILWAY_SQS_QUEUE`
* `PLUGN_WALLET_API_KEY`
* `PLUGN_WALLET_API_ENDPOINT`
* `PLUGN_RAILWAY_S3_REGION`
* `PLUGN_RAILWAY_S3_BUCKET`
* `PLUGN_RAILWAY_S3_KEY`
* `PLUGN_RAILWAY_S3_SECRET`
* `PLUGN_SENTRY_DSN`
* `PLUGN_REDIS_HOST`
* `PLUGN_REDIS_USERNAME`
* `PLUGN_REDIS_PASSWORD`
* `PLUGN_REDIS_PORT`
* `PLUGN_REDIS_DATABASE`
* `PLUGN_SMTP_HOST`
* `PLUGN_SMTP_USERNAME`
* `PLUGN_SMTP_PASSWORD`
* `PLUGN_SMTP_PORT`
* `PLUGN_BLOG_MANAGER_API_ENDPOINT`
* `PLUGN_BLOG_MANAGER_TOKEN`
* `PLUGN_GPT_TOKEN`
* `PLUGN_GPT_API_ENDPOINT`

The `prod-railway` backend, frontend, and partner apps also require `PLUGN_REDIS_PASSWORD` for Redis-backed sessions. The common config and app session configs fail fast when required variables are missing so production cannot boot with committed secrets or empty Redis credentials.

Run these checks before deploying production config changes:

```bash
bash tests/check-prod-cookie-validation-env.sh
bash tests/check-prod-api-debug-disabled.sh
bash tests/check-prod-redis-session-env.sh
bash tests/check-prod-railway-common-env.sh
```
