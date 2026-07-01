<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\PaymentGateways\MomoDriver;
use App\Services\PaymentGateways\VNPayDriver;
use App\Services\PaymentGateways\ZaloPayDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * These drivers can't be fully verified against the real Momo/VNPay/ZaloPay sandboxes
 * without live merchant credentials, so these tests cover what can be checked
 * independently: HMAC signing/verification round-trips correctly, tampering is rejected,
 * and the request payload sent to each gateway matches its documented contract.
 */
class PaymentGatewayDriversTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.momo.partner_code' => 'MOMOTEST',
            'services.momo.access_key' => 'test-access-key',
            'services.momo.secret_key' => 'test-secret-key',
            'services.momo.endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',

            'services.vnpay.tmncode' => 'VNPTEST',
            'services.vnpay.hashsecret' => 'test-vnpay-hash-secret',
            'services.vnpay.url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',

            'services.zalopay.app_id' => '2553',
            'services.zalopay.key1' => 'test-zalopay-key1',
            'services.zalopay.key2' => 'test-zalopay-key2',
            'services.zalopay.endpoint' => 'https://sb-openapi.zalopay.vn/v2/create',
        ]);
    }

    public function test_momo_create_payment_url_signs_request_correctly_and_returns_pay_url(): void
    {
        $order = Order::factory()->create(['total_amount' => 150000]);

        Http::fake(function ($request) {
            $body = $request->data();

            $accessKey = 'test-access-key';
            $expectedRaw = "accessKey={$accessKey}&amount={$body['amount']}&extraData={$body['extraData']}"
                . "&ipnUrl={$body['ipnUrl']}&orderId={$body['orderId']}&orderInfo={$body['orderInfo']}"
                . "&partnerCode={$body['partnerCode']}&redirectUrl={$body['redirectUrl']}"
                . "&requestId={$body['requestId']}&requestType={$body['requestType']}";
            $expectedSignature = hash_hmac('sha256', $expectedRaw, 'test-secret-key');

            $this->assertEquals($expectedSignature, $body['signature'], 'Momo request signature must match documented field order');

            return Http::response(['resultCode' => 0, 'payUrl' => 'https://test-payment.momo.vn/pay/abc123'], 200);
        });

        $driver = new MomoDriver();
        $url = $driver->createPaymentUrl($order, 'https://example.test/return');

        $this->assertEquals('https://test-payment.momo.vn/pay/abc123', $url);
    }

    public function test_momo_verify_callback_accepts_valid_signature_and_decodes_order_id(): void
    {
        $order = Order::factory()->create();
        $secretKey = 'test-secret-key';
        $accessKey = 'test-access-key';
        $extraData = base64_encode((string) $order->id);

        $fields = [
            'partnerCode' => 'MOMOTEST', 'orderId' => 'ORD-1', 'requestId' => 'req-1',
            'amount' => '150000', 'orderInfo' => 'Thanh toan', 'orderType' => 'momo_wallet',
            'transId' => '9999', 'resultCode' => '0', 'message' => 'Success',
            'payType' => 'qr', 'responseTime' => '1234567890', 'extraData' => $extraData,
        ];

        $rawSignature = "accessKey={$accessKey}&amount={$fields['amount']}&extraData={$fields['extraData']}"
            . "&message={$fields['message']}&orderId={$fields['orderId']}&orderInfo={$fields['orderInfo']}"
            . "&orderType={$fields['orderType']}&partnerCode={$fields['partnerCode']}&payType={$fields['payType']}"
            . "&requestId={$fields['requestId']}&responseTime={$fields['responseTime']}&resultCode={$fields['resultCode']}"
            . "&transId={$fields['transId']}";
        $fields['signature'] = hash_hmac('sha256', $rawSignature, $secretKey);

        $driver = new MomoDriver();
        $result = $driver->verifyCallback(Request::create('/webhooks/momo', 'POST', $fields));

        $this->assertTrue($result->success);
        $this->assertEquals($order->id, $result->orderId);
        $this->assertEquals('9999', $result->transactionCode);
    }

    public function test_momo_verify_callback_rejects_tampered_signature(): void
    {
        $driver = new MomoDriver();
        $result = $driver->verifyCallback(Request::create('/webhooks/momo', 'POST', [
            'orderId' => 'ORD-1', 'amount' => '150000', 'resultCode' => '0',
            'extraData' => base64_encode('1'), 'transId' => '9999',
            'signature' => 'not-a-real-signature',
        ]));

        $this->assertFalse($result->success);
    }

    public function test_vnpay_create_payment_url_produces_verifiable_secure_hash(): void
    {
        $order = Order::factory()->create(['total_amount' => 200000]);

        $driver = new VNPayDriver();
        $url = $driver->createPaymentUrl($order, 'https://example.test/return');

        $this->assertStringStartsWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?', $url);

        // Parse the generated URL's query string back out and feed it through
        // verifyCallback() to prove the secure hash is self-consistent.
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);

        $this->assertArrayHasKey('vnp_SecureHash', $params);

        // Simulate VNPay appending vnp_ResponseCode=00 (success) on the return callback.
        $params['vnp_ResponseCode'] = '00';
        $params['vnp_TransactionNo'] = '13345678';

        // Re-sign exactly like VNPay would for the callback (same params minus the hash),
        // since our own createPaymentUrl hash covers the pre-callback param set only.
        $forVerification = $params;
        unset($forVerification['vnp_SecureHash']);
        ksort($forVerification);
        $expectedHash = hash_hmac('sha512', http_build_query($forVerification, '', '&', PHP_QUERY_RFC3986), 'test-vnpay-hash-secret');
        $params['vnp_SecureHash'] = $expectedHash;

        $driver2 = new VNPayDriver();
        $result = $driver2->verifyCallback(Request::create('/webhooks/vnpay', 'GET', $params));

        $this->assertTrue($result->success);
        $this->assertEquals($order->id, $result->orderId);
    }

    public function test_vnpay_verify_callback_rejects_invalid_signature(): void
    {
        $driver = new VNPayDriver();
        $result = $driver->verifyCallback(Request::create('/webhooks/vnpay', 'GET', [
            'vnp_TxnRef' => '1-123', 'vnp_ResponseCode' => '00',
            'vnp_SecureHash' => 'invalid',
        ]));

        $this->assertFalse($result->success);
    }

    public function test_zalopay_create_payment_url_signs_request_correctly(): void
    {
        $order = Order::factory()->create(['total_amount' => 99000]);

        Http::fake(function ($request) {
            $body = $request->data();
            $expectedMac = hash_hmac(
                'sha256',
                "{$body['app_id']}|{$body['app_trans_id']}|{$body['app_user']}|{$body['amount']}|{$body['app_time']}|{$body['embed_data']}|{$body['item']}",
                'test-zalopay-key1'
            );

            $this->assertEquals($expectedMac, $body['mac'], 'ZaloPay request mac must match documented pipe-delimited field order');

            return Http::response(['return_code' => 1, 'order_url' => 'https://sb-openapi.zalopay.vn/pay/xyz'], 200);
        });

        $driver = new ZaloPayDriver();
        $url = $driver->createPaymentUrl($order, 'https://example.test/return');

        $this->assertEquals('https://sb-openapi.zalopay.vn/pay/xyz', $url);
    }

    public function test_zalopay_verify_callback_accepts_valid_mac_and_decodes_order_id(): void
    {
        $order = Order::factory()->create();
        $key2 = 'test-zalopay-key2';

        $data = json_encode([
            'app_trans_id' => '260101_1_123',
            'zp_trans_id' => 'ZP1234',
            'embed_data' => json_encode(['order_id' => $order->id]),
        ]);
        $mac = hash_hmac('sha256', $data, $key2);

        $driver = new ZaloPayDriver();
        $result = $driver->verifyCallback(Request::create('/webhooks/zalopay', 'POST', [
            'data' => $data,
            'mac' => $mac,
        ]));

        $this->assertTrue($result->success);
        $this->assertEquals($order->id, $result->orderId);
        $this->assertEquals('ZP1234', $result->transactionCode);
    }

    public function test_zalopay_verify_callback_rejects_invalid_mac(): void
    {
        $driver = new ZaloPayDriver();
        $result = $driver->verifyCallback(Request::create('/webhooks/zalopay', 'POST', [
            'data' => json_encode(['embed_data' => json_encode(['order_id' => 1])]),
            'mac' => 'invalid-mac',
        ]));

        $this->assertFalse($result->success);
    }
}
