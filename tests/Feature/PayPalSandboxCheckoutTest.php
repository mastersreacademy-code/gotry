<?php

namespace Tests\Feature;

use App\Services\PayPalSandboxCheckout;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class PayPalSandboxCheckoutTest extends TestCase
{
    public function test_checkout_uses_sandbox_and_charges_two_australian_dollars(): void
    {
        config(['paypal.client_id' => 'sandbox-client', 'paypal.client_secret' => 'sandbox-secret']);
        Http::preventStrayRequests();
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'sandbox-token']),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders' => Http::response(['id' => 'ORDER-1'], 201),
            'https://api-m.sandbox.paypal.com/v2/checkout/orders/ORDER-1/capture' => Http::response(['status' => 'COMPLETED'], 201),
        ]);

        $checkout = app(PayPalSandboxCheckout::class);
        $order = $checkout->createOrder('trial-1', 'create-1', 'https://example.test/return', 'https://example.test/cancel');
        $capture = $checkout->captureOrder($order['id'], 'capture-1');

        $this->assertSame('COMPLETED', $capture['status']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/orders')
            && $request->hasHeader('Authorization', 'Bearer sandbox-token')
            && $request->hasHeader('PayPal-Request-Id', 'create-1')
            && $request['purchase_units'][0]['amount'] === ['currency_code' => 'AUD', 'value' => '2.00']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/ORDER-1/capture')
            && $request->hasHeader('PayPal-Request-Id', 'capture-1')
            && $request->body() === '{}');
    }

    public function test_missing_credentials_prevent_checkout_requests(): void
    {
        config(['paypal.client_id' => null, 'paypal.client_secret' => null]);
        Http::fake();
        $this->expectException(RuntimeException::class);

        try {
            app(PayPalSandboxCheckout::class)->captureOrder('ORDER-1', 'capture-1');
        } finally {
            Http::assertNothingSent();
        }
    }
}
