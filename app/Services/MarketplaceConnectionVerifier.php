<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class MarketplaceConnectionVerifier
{
    /**
     * Verify credentials without saving them. This verifies authentication only;
     * it does not imply that every marketplace operation/role is available.
     */
    public function verify(string $platform, array $credentials, string $environment = 'production'): array
    {
        try {
            return match ($platform) {
                'amazon' => $this->verifyAmazon($credentials, $environment),
                'flipkart' => $this->verifyFlipkart($credentials),
                default => ['ok' => false, 'message' => 'This platform does not have a verification adapter yet.'],
            };
        } catch (Throwable $e) {
            report($e);
            return ['ok' => false, 'message' => 'The marketplace could not be reached or returned an unexpected response. Check the credentials and try again.'];
        }
    }

    private function verifyAmazon(array $c, string $environment = 'production'): array
    {
        $response = Http::asForm()->acceptJson()->timeout(20)->post('https://api.amazon.com/auth/o2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $c['refresh_token'],
            'client_id' => $c['client_id'],
            'client_secret' => $c['client_secret'],
        ]);

        if (!$response->successful() || !$response->json('access_token')) {
            return ['ok' => false, 'message' => 'Amazon rejected the LWA credentials. Check Client ID, Client Secret and Refresh Token.'];
        }

        if ($environment === 'sandbox') {
            $region = $c['region'] ?? 'eu';
            $endpoint = match ($region) {
                'na' => 'https://sandbox.sellingpartnerapi-na.amazon.com',
                'fe' => 'https://sandbox.sellingpartnerapi-fe.amazon.com',
                default => 'https://sandbox.sellingpartnerapi-eu.amazon.com',
            };

            // Amazon's official first-call sandbox example uses this static
            // Orders API test case. It returns mocked data, never seller data.
            $sandboxResponse = Http::withHeaders([
                'x-amz-access-token' => $response->json('access_token'),
                'Content-Type' => 'application/json',
                'User-Agent' => 'SellerAutomation/1.0 (Language=PHP)',
            ])->acceptJson()->timeout(20)->get($endpoint.'/orders/v0/orders', [
                'MarketplaceIds' => 'ATVPDKIKX0DER',
                'CreatedAfter' => 'TEST_CASE_200',
            ]);

            if (!$sandboxResponse->successful()) {
                return ['ok' => false, 'message' => 'Amazon LWA credentials were accepted, but the SP-API Sandbox test request failed (HTTP '.$sandboxResponse->status().'). Check the sandbox app credentials, region and API availability.'];
            }

            return ['ok' => true, 'message' => 'Amazon Sandbox verified: LWA token issued and SP-API Sandbox returned a test response. This is mock data only; production seller access is not verified.'];
        }

        return ['ok' => true, 'message' => 'Amazon Production LWA credentials verified. SP-API role permissions and signed marketplace operations still need a separate test.'];
    }

    private function verifyFlipkart(array $c): array
    {
        $url = 'https://api.flipkart.net/oauth-service/oauth/token?grant_type=client_credentials&scope=Seller_Api,Default';
        $response = Http::withBasicAuth($c['app_id'], $c['app_secret'])
            ->acceptJson()->timeout(20)->get($url);

        if (!$response->successful() || !$response->json('access_token')) {
            return ['ok' => false, 'message' => 'Flipkart rejected the application credentials or access is not enabled. Check Application ID and Application Secret.'];
        }

        return ['ok' => true, 'message' => 'Flipkart application credentials verified and an access token was issued. Seller API permissions should be tested with the required API operations next.'];
    }
}
