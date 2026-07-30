<?php

namespace App\Services\Printing;

use App\Models\Order;
use App\Models\PosDevice;
use Illuminate\Support\Facades\Log;

/**
 * Dựng hóa đơn ESC/POS từ Order và gửi tới máy in nhiệt qua mạng LAN
 * (RAW/JetDirect port 9100). Máy in được khai báo trong trang Tích hợp
 * (PosDevice type=printer, meta: ip, port, paper_width).
 */
class ReceiptPrinterService
{
    public function buildReceipt(Order $order): string
    {
        $order->loadMissing(['items.product', 'restaurant', 'table']);

        $paperWidth = 32; // mặc định khổ 58mm
        $printer = $this->defaultPrinter($order->restaurant_id);
        if ($printer && (int) ($printer->meta['paper_width'] ?? 58) === 80) {
            $paperWidth = 48;
        }

        $builder = new EscPosBuilder($paperWidth);
        $restaurant = $order->restaurant;

        $builder->initialize()
            ->align('center')
            ->doubleSize()
            ->text($restaurant->name ?? 'Aventura')
            ->doubleSize(false);

        if ($restaurant?->address) {
            $builder->text($restaurant->address);
        }
        if ($restaurant?->phone) {
            $builder->text('DT: '.$restaurant->phone);
        }

        $builder->divider('=')
            ->bold()
            ->text('HOA DON THANH TOAN')
            ->bold(false)
            ->align('left')
            ->row('So don:', $order->order_number)
            ->row('Ngay:', $order->created_at->format('d/m/Y H:i'));

        if ($order->table) {
            $builder->row('Ban:', (string) $order->table->name);
        }

        $builder->divider();

        foreach ($order->items as $item) {
            $name = $item->product?->name ?? 'Mon #'.$item->product_id;
            $builder->text($item->quantity.'x '.$name);
            $builder->row('', number_format((float) $item->line_total).'d');
        }

        $builder->divider()
            ->row('Tam tinh:', number_format((float) $order->subtotal).'d');

        if ((float) $order->discount_amount > 0) {
            $builder->row('Giam gia:', '-'.number_format((float) $order->discount_amount).'d');
        }
        if ((float) $order->service_charge > 0) {
            $builder->row('Phi dich vu:', number_format((float) $order->service_charge).'d');
        }

        $builder->bold()
            ->row('TONG CONG:', number_format((float) $order->total_amount).'d')
            ->bold(false)
            ->divider('=')
            ->align('center')
            ->text('Cam on quy khach!')
            ->text('Hen gap lai')
            ->feed(3)
            ->cut();

        return $builder->getBytes();
    }

    /**
     * In hóa đơn tới máy in chỉ định (hoặc máy in mặc định của nhà hàng).
     * Trả về [success, message] — không throw để UI hiển thị lỗi thân thiện.
     */
    public function print(Order $order, ?PosDevice $printer = null): array
    {
        $printer ??= $this->defaultPrinter($order->restaurant_id);

        if (! $printer) {
            return [false, 'Chưa khai báo máy in nhiệt nào. Thêm máy in trong Cài đặt → Tích hợp.'];
        }

        $ip = $printer->meta['ip'] ?? null;
        $port = (int) ($printer->meta['port'] ?? 9100);

        if (! $ip) {
            return [false, "Máy in \"{$printer->name}\" chưa có địa chỉ IP."];
        }

        $bytes = $this->buildReceipt($order);

        try {
            $socket = @fsockopen($ip, $port, $errno, $errstr, 3);

            if (! $socket) {
                Log::warning('[PRINTER] Không kết nối được máy in', [
                    'printer' => $printer->name, 'ip' => $ip, 'port' => $port, 'error' => $errstr,
                ]);

                return [false, "Không kết nối được máy in \"{$printer->name}\" ({$ip}:{$port})."];
            }

            fwrite($socket, $bytes);
            fclose($socket);

            $printer->update(['last_seen_at' => now()]);

            return [true, "Đã gửi hóa đơn {$order->order_number} tới máy in \"{$printer->name}\"."];
        } catch (\Throwable $e) {
            Log::error('[PRINTER] Lỗi in hóa đơn', ['error' => $e->getMessage()]);

            return [false, 'Lỗi khi gửi dữ liệu tới máy in: '.$e->getMessage()];
        }
    }

    public function defaultPrinter(int $restaurantId): ?PosDevice
    {
        return PosDevice::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('type', PosDevice::TYPE_PRINTER)
            ->where('status', 'active')
            ->orderByDesc('last_seen_at')
            ->first();
    }
}
