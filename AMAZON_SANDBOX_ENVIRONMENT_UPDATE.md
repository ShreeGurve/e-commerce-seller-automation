# Amazon Sandbox Environment Support

## What this update changes
- Adds a separate `environment` value (`sandbox` or `production`) to marketplace connections.
- Allows one Amazon Sandbox connection and one Amazon Production connection per business without overwriting either. Existing connections migrate as `production`.
- Adds separate Add Amazon Sandbox / Add Amazon Production actions.
- Sandbox verification exchanges the sandbox refresh token at Amazon LWA, then makes the official static Sandbox Orders test request.
- Sandbox responses are simulated data only; they are not the seller's live data.
- Flipkart remains production-only and is unchanged.
- Credentials continue to use Laravel's encrypted array cast.

## Install
1. Back up your project and database.
2. Extract the ZIP into the Laravel project root, preserving paths.
3. Run:

```bash
php artisan migrate
php artisan optimize:clear
```

Do not run `migrate:fresh`. The migration adds an environment column, preserves existing rows as `production`, and changes the unique key to business + marketplace + environment.

## Configure Amazon Sandbox
1. Open Sales → Marketplace Connections.
2. Choose Add Amazon Sandbox.
3. Enter values from Developer Central → View sandbox credentials and Action → Create Token.
4. Keep region `EU` for the India marketplace endpoint.
5. Click Verify credentials & save.
6. The connection should display as Sandbox / Verified.

The sandbox verifier uses Amazon's documented static sandbox test request (`GET /orders/v0/orders` with `CreatedAfter=TEST_CASE_200`). It verifies the LWA token and that the sandbox API responds. The sample test request uses a mock US marketplace ID as in Amazon's onboarding guide; it does not retrieve India seller data.

## Important boundaries
- This does not verify Production SP-API authorization, AWS IAM/SigV4, or seller role access.
- Sandbox results must never be treated as real product/order/inventory data.
- All publishing, inventory writes, order actions and other live operations remain disabled.
- Do not paste secrets into chat or commit them to source control.

## Official references
- Amazon onboarding overview: https://developer-docs.amazon.com/sp-api/docs/onboarding-overview
- First sandbox API call and credentials: https://developer-docs.amazon.com/sp-api/docs/onboarding-step-5-make-your-first-call-to-the-sp-api-sandbox
- SP-API endpoints: https://developer-docs.amazon.com/sp-api/docs/sp-api-endpoints
