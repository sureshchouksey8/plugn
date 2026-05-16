# Common Runtime Secrets

`common/config/main.php` loads shared third-party credentials from environment
variables. Set these values in the target runtime instead of committing secrets.

Required for the relevant integrations:

- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_API_KEY`
- `CLOUDINARY_API_SECRET`
- `IPSTACK_ACCESS_KEY`
- `RECAPTCHA_SECRET_KEY`
- `TEMPORARY_BUCKET_ACCESS_KEY_ID`
- `TEMPORARY_BUCKET_SECRET_ACCESS_KEY`
- `TEMPORARY_BUCKET_NAME`
- `TAP_PLUGN_LIVE_API_KEY`
- `TAP_PLUGN_TEST_API_KEY`
- `TAP_PLUGN_DESTINATION_ID`
- `MYFATOORAH_KUWAIT_LIVE_API_KEY`
- `MYFATOORAH_KUWAIT_TEST_API_KEY`
- `MYFATOORAH_SAUDI_LIVE_API_KEY`
- `GOOGLE_MAPS_API_KEY`
- `NETLIFY_TOKEN`
- `PLUGN_GITHUB_TOKEN`
- `SLACK_ACTIVITY_WEBHOOK_URL`
- `SLACK_TAP_OPERATION_WEBHOOK_URL`
- `SLACK_ERROR_WEBHOOK_URL`

Static non-secret defaults, such as component classes, branch names, regions, and
public bucket names, remain in code.
