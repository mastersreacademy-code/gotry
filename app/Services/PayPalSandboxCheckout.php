<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalSandboxCheckout
{
    private const BASE_URL = 'https://api-m.sandbox.paypal.com';

    public function createOrder(string $reference, string $requestId, string $returnUrl, string $cancelUrl): array
    {
        return $this->post('/v2/checkout/orders', $requestId, [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $reference,
                'amount' => ['currency_code' => 'AUD', 'value' => '2.00'],
            ]],
            'payment_source' => ['paypal' => ['experience_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ]]],
        ]);
    }

    public function captureOrder(string $orderId, string $requestId): array
    {
        return $this->post('/v2/checkout/orders/'.rawurlencode($orderId).'/capture', $requestId, (object) []);
    }

    private function post(string $path, string $requestId, array|object $body): array
    {
        $clientId = config('paypal.client_id');
        $secret = config('paypal.client_secret');
        if (! $clientId || ! $secret) {
            throw new RuntimeException('PayPal sandbox credentials are missing.');
        }

        $token = Http::asForm()->acceptJson()->withBasicAuth($clientId, $secret)
            ->connectTimeout(5)->timeout(10)
            ->post(self::BASE_URL.'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('PayPal sandbox did not return an access token.');
        }

        $result = Http::acceptJson()->withToken($token)->withHeaders(['PayPal-Request-Id' => $requestId])
            ->connectTimeout(5)->timeout(15)
            ->withBody(json_encode($body, JSON_THROW_ON_ERROR), 'application/json')
            ->post(self::BASE_URL.$path)->throw()->json();

        if (! is_array($result)) {
            throw new RuntimeException('PayPal sandbox returned an invalid checkout response.');
        }

        return $result;
    }
}
