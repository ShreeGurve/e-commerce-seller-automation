# Dynamic Marketplace Platform Connections

## Included
- Replaces the fixed Amazon/Flipkart readiness screen with a marketplace directory-driven connection list.
- Add one platform at a time, enter credentials, verify against the platform, and persist only after verification succeeds.
- Amazon verification exchanges the supplied LWA refresh token at `https://api.amazon.com/auth/o2/token`.
- Flipkart verification requests a self-access token using the documented client-credentials flow.
- Credentials are stored using Laravel's `encrypted:array` cast. They are never returned to Blade views.
- Failed verification does not save the attempted credentials and does not overwrite an existing successful connection.
- One seller connection per marketplace per business in this version.

## Install
1. Back up the project and database.
2. Extract this ZIP into the Laravel project root, preserving paths.
3. Run:

```bash
php artisan migrate
php artisan optimize:clear
```

No `migrate:fresh` is required or recommended.

## Security
- Keep `APP_KEY` stable and back it up securely; encrypted credentials cannot be decrypted if the key is lost or changed.
- Use HTTPS in production.
- Never commit `.env` or share credentials in screenshots/chat.
- Do not enable marketplace writes based only on token verification.

## Verification boundaries
- Amazon: verifies LWA client credentials + refresh token by obtaining an access token. It does not test AWS IAM/SigV4 signing, SP-API role grants, or any listing/order/inventory operation.
- Flipkart: verifies application credentials by obtaining a Seller API access token. It does not prove every seller API operation is enabled.
- Other marketplaces in the `marketplaces` table are shown dynamically, but remain unavailable until a platform-specific verifier/adapter is implemented. Do not mark unsupported connectors as connected.

## Official references
- Amazon private app authorization: https://developer-docs.amazon.com/sp-api/docs/self-authorization
- Amazon LWA token exchange: https://developer-docs.amazon.com/sp-api/docs/connecting-to-the-selling-partner-api
- Flipkart self-access client credentials: https://seller.flipkart.com/api-docs/FMSAPI.html
