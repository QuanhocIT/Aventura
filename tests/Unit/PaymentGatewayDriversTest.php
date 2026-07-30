<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\PaymentGateways\MomoDriver;
use App\Services\PaymentGateways\VNPayDriver;
use App\Services\PaymentGateways\ZaloPayDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentGatewayDriversTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.vnpay.tmncode', 'TESTTMN');
        config()->set('services.vnpay.hashsecret', 'test-hash-secret');
        config()->set('services.vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');

        config()->set('services.momo.partner_code', 'TESTPARTNER');
        config()->set('services.momo.access_key', 'test-access-key');
        config()->set('services.momo.secret_key', 'test-secret-key');
        config()->set('services.momo.endpoint', 'https://test-payment.momo.vn/v2/gateway/api/create');

        config()->set('services.zalopay.app_id', '2554');
        config()->set('services.zalopay.key1', 'test-key1');
        config()->set('services.zalopay.key2', 'test-key2');
        config()->set('services.zalopay.endpoint', 'https://sb-openapi.zalopay.vn/v2/create');
    }

    public function test_vnpay_creates_url_and_verifies_own_valid_signature(): void
    {
        $order = Order::factory()->create(['total_amount' => 150000]);
        $driver = app(VNPayDriver::class);

        $url = $driver->createPaymentUrl($order, 'https://app.test/order/payment/return');

        $this->assertStringContainsString('vnp_TmnCode=TESTTMN', $url);
        $this->assertStringContainsString('vnp_Amount=15000000', $url); // *100
        $this->assertStringContainsString('vnp_SecureHash=', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        // Giả lập VNPay gọi IPN lại với đúng các tham số + hash vừa tạo, kèm response code thành công.
        $params['vnp_ResponseCode'] = '00';
        $params['vnp_TransactionStatus'] = '00';
        $params['vnp_TransactionNo'] = '14000123';

        // Hash phải tính lại bao gồm cả 2 field mới thêm, đúng như VNPay thực tế sẽ làm.
        $hashSecret = 'test-hash-secret';
        $forHash = $params;
        unset($forHash['vnp_SecureHash']);
        ksort($forHash);
        $parts = [];
        foreach ($forHash as $k => $v) {
            $parts[] = $k . '=' . urlencode((string) $v);
        }
        $params['vnp_SecureHash'] = hash_hmac('sha512', implode('&', $parts), $hashSecret);

        $request = Request::create('/api/webhooks/payments/vnpay', 'GET', $params);
        $result = $driver->verifyCallback($request);

        $this->assertTrue($result->success);
        $this->assertSame($order->id, $result->orderId);
        $this->assertSame('14000123', $result->transactionCode);
    }

    public function test_vnpay_rejects_tampered_signature(): void
    {
        $order = Order::factory()->create(['total_amount' => 150000]);
        $driver = app(VNPayDriver::class);

        $url = $driver->createPaymentUrl($order, 'https://app.test/order/payment/return');
        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        $params['vnp_ResponseCode'] = '00';
        $params['vnp_TransactionStatus'] = '00';
        $params['vnp_Amount'] = '1'; // giả mạo số tiền, không tính lại hash

        $request = Request::create('/api/webhooks/payments/vnpay', 'GET', $params);
        $result = $driver->verifyCallback($request);

        $this->assertFalse($result->success);
        $this->assertSame('invalid_signature', $result->error);
    }

    public function test_momo_creates_url_via_http_and_verifies_own_valid_signature(): void
    {
        Http::fake([
            'test-payment.momo.vn/*' => Http::response(['payUrl' => 'https://test-payment.momo.vn/pay/abc123'], 200),
        ]);

        $order = Order::factory()->create(['total_amount' => 200000]);
        $driver = app(MomoDriver::class);

        $payUrl = $driver->createPaymentUrl($order, 'https://app.test/order/payment/return');

        $this->assertSame('https://test-payment.momo.vn/pay/abc123', $payUrl);

        Http::assertSent(fn ($request) => $request['orderId'] === (string) $order->id && $request['amount'] === '200000');

        // Giả lập Momo gọi IPN lại với chữ ký hợp lệ.
        $secretKey = 'test-secret-key';
        $callback = [
            'accessKey' => 'test-access-key',
            'amount' => '200000',
            'extraData' => '',
            'message' => 'Success',
            'orderId' => (string) $order->id,
            'orderInfo' => 'Thanh toan don hang ' . $order->order_number,
            'orderType' => 'momo_wallet',
            'partnerCode' => 'TESTPARTNER',
            'payType' => 'qr',
            'requestId' => $order->id . '-123',
            'responseTime' => '1234567890',
            'resultCode' => '0',
            'transId' => '9999888877',
        ];

        $rawSignature = "accessKey={$callback['accessKey']}&amount={$callback['amount']}&extraData={$callback['extraData']}"
            . "&message={$callback['message']}&orderId={$callback['orderId']}&orderInfo={$callback['orderInfo']}"
            . "&orderType={$callback['orderType']}&partnerCode={$callback['partnerCode']}&payType={$callback['payType']}"
            . "&requestId={$callback['requestId']}&responseTime={$callback['responseTime']}&resultCode={$callback['resultCode']}"
            . "&transId={$callback['transId']}";

        $callback['signature'] = hash_hmac('sha256', $rawSignature, $secretKey);

        $request = Request::create('/api/webhooks/payments/momo', 'POST', $callback);
        $result = $driver->verifyCallback($request);

        $this->assertTrue($result->success);
        $this->assertSame($order->id, $result->orderId);
        $this->assertSame('9999888877', $result->transactionCode);
    }

    public function test_zalopay_creates_url_via_http_and_verifies_own_valid_mac(): void
    {
        Http::fake([
            'sb-openapi.zalopay.vn/*' => Http::response(['return_code' => 1, 'order_url' => 'https://sb-openapi.zalopay.vn/pay/xyz'], 200),
        ]);

        $order = Order::factory()->create(['total_amount' => 300000]);
        $driver = app(ZaloPayDriver::class);

        $orderUrl = $driver->createPaymentUrl($order, 'https://app.test/order/payment/return');

        $this->assertSame('https://sb-openapi.zalopay.vn/pay/xyz', $orderUrl);

        // Giả lập ZaloPay gọi callback lại với data + mac hợp lệ theo key2.
        $data = json_encode([
            'app_trans_id' => now()->format('ymd') . '_' . $order->id . '_999',
            'zp_trans_id' => '2205551234',
            'amount' => 300000,
        ]);
        $mac = hash_hmac('sha256', $data, 'test-key2');

        $request = Request::create('/api/webhooks/payments/zalopay', 'POST', ['data' => $data, 'mac' => $mac]);
        $result = $driver->verifyCallback($request);

        $this->assertTrue($result->success);
        $this->assertSame($order->id, $result->orderId);
        $this->assertSame('2205551234', $result->transactionCode);
    }
}
