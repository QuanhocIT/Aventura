<?php

namespace App\Services\Integrations;

use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * Xuất chứng từ bán hàng theo mẫu nhập liệu MISA SME (CSV, UTF-8 BOM
 * để Excel/MISA đọc đúng tiếng Việt). Mỗi dòng = một chứng từ bán hàng
 * tổng hợp từ một đơn đã hoàn thành/thanh toán.
 */
class MisaExportService
{
    public const COLUMNS = [
        'Ngày hạch toán',
        'Ngày chứng từ',
        'Số chứng từ',
        'Diễn giải',
        'Mã khách hàng',
        'Tên khách hàng',
        'TK Nợ',
        'TK Có',
        'Doanh thu (chưa thuế)',
        'Tiền chiết khấu',
        'Tổng thanh toán',
        'Hình thức thanh toán',
    ];

    /** @return Collection<int, Order> */
    public function ordersForPeriod(int $restaurantId, string $from, string $to): Collection
    {
        return Order::withoutGlobalScopes()
            ->with('customer')
            ->where('restaurant_id', $restaurantId)
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->orderBy('created_at')
            ->get();
    }

    /** Sinh nội dung CSV (chuỗi hoàn chỉnh gồm BOM + header + dữ liệu). */
    public function buildCsv(int $restaurantId, string $from, string $to): string
    {
        $orders = $this->ordersForPeriod($restaurantId, $from, $to);

        $rows = [self::COLUMNS];

        foreach ($orders as $order) {
            $date = $order->created_at->format('d/m/Y');

            $rows[] = [
                $date,
                $date,
                'BH-'.$order->order_number,
                'Doanh thu bán hàng - đơn '.$order->order_number,
                $order->customer ? 'KH'.str_pad((string) $order->customer_id, 5, '0', STR_PAD_LEFT) : 'KHLE',
                $order->customer?->full_name ?? 'Khách lẻ',
                '111', // Tiền mặt / tương đương
                '511', // Doanh thu bán hàng
                $this->money((float) $order->subtotal),
                $this->money((float) $order->discount_amount),
                $this->money((float) $order->total_amount),
                $this->paymentMethodLabel($order),
            ];
        }

        $csv = '';
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($cell) => $this->escapeCsv((string) $cell), $row))."\r\n";
        }

        // BOM UTF-8 để MISA/Excel nhận diện tiếng Việt
        return "\xEF\xBB\xBF".$csv;
    }

    private function money(float $amount): string
    {
        return number_format($amount, 0, '.', '');
    }

    private function paymentMethodLabel(Order $order): string
    {
        $method = $order->payments()->latest()->first()?->payment_method ?? 'cash';

        return match ($method) {
            'cash' => 'Tiền mặt',
            'card' => 'Thẻ',
            'bank_transfer' => 'Chuyển khoản',
            'vnpay' => 'VNPay',
            'momo' => 'MoMo',
            'zalopay' => 'ZaloPay',
            default => $method,
        };
    }

    private function escapeCsv(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
