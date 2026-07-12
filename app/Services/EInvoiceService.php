<?php

namespace App\Services;

use App\Models\Order;

/**
 * Sinh XML hóa đơn điện tử theo cấu trúc dữ liệu Thông tư 78/2021/TT-BTC
 * (định dạng chuẩn Tổng cục Thuế). File XML này dùng để nhập vào phần mềm
 * HĐĐT của nhà cung cấp (Viettel/VNPT/MISA meInvoice) hoặc lưu trữ đối chiếu.
 * Lưu ý: đây là dữ liệu hóa đơn, chưa có chữ ký số — ký số do nhà cung cấp
 * HĐĐT thực hiện khi phát hành chính thức.
 */
class EInvoiceService
{
    private const VAT_RATE = 8; // % VAT ngành ăn uống hiện hành

    public function buildXml(Order $order): string
    {
        $order->loadMissing(['items.product', 'restaurant', 'customer']);
        $restaurant = $order->restaurant;

        // Tổng thanh toán đã gồm VAT → tách ngược ra tiền hàng + thuế
        $totalWithVat = (float) $order->total_amount;
        $totalWithoutVat = round($totalWithVat / (1 + self::VAT_RATE / 100), 2);
        $vatAmount = round($totalWithVat - $totalWithoutVat, 2);

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><HDon/>');

        $dlHDon = $xml->addChild('DLHDon');
        $ttChung = $dlHDon->addChild('TTChung');
        $ttChung->addChild('PBan', '2.0.0'); // phiên bản định dạng theo QĐ 1450/TCT
        $ttChung->addChild('THDon', 'Hóa đơn giá trị gia tăng');
        $ttChung->addChild('KHMSHDon', '1');
        $ttChung->addChild('KHHDon', 'C' . now()->format('y') . 'TAA');
        $ttChung->addChild('SHDon', (string) $order->id);
        $ttChung->addChild('NLap', ($order->completed_at ?? $order->created_at)->format('Y-m-d'));
        $ttChung->addChild('DVTTe', 'VND');
        $ttChung->addChild('HTTToan', $this->paymentMethodLabel($order));

        $ndHDon = $dlHDon->addChild('NDHDon');

        // Người bán
        $nBan = $ndHDon->addChild('NBan');
        $this->addCData($nBan->addChild('Ten'), $restaurant->name ?? '');
        $nBan->addChild('MST', $restaurant->tax_code ?? '');
        $this->addCData($nBan->addChild('DChi'), $restaurant->address ?? '');
        $nBan->addChild('SDThoai', $restaurant->phone ?? '');

        // Người mua
        $nMua = $ndHDon->addChild('NMua');
        $this->addCData($nMua->addChild('Ten'), $order->customer?->full_name ?? 'Khách lẻ không lấy hóa đơn');
        $nMua->addChild('MST', '');
        $nMua->addChild('SDThoai', $order->customer?->phone ?? '');

        // Danh sách hàng hóa dịch vụ
        $dsHHDVu = $ndHDon->addChild('DSHHDVu');
        $line = 1;

        foreach ($order->items as $item) {
            $unitPriceNoVat = round((float) $item->unit_price / (1 + self::VAT_RATE / 100), 2);
            $lineTotalNoVat = round($unitPriceNoVat * (float) $item->quantity, 2);

            $hhdv = $dsHHDVu->addChild('HHDVu');
            $hhdv->addChild('TChat', '1'); // 1 = hàng hóa dịch vụ thường
            $hhdv->addChild('STT', (string) $line++);
            $this->addCData($hhdv->addChild('THHDVu'), $item->product?->name ?? 'Món ăn');
            $hhdv->addChild('DVTinh', 'Phần');
            $hhdv->addChild('SLuong', $this->num((float) $item->quantity));
            $hhdv->addChild('DGia', $this->num($unitPriceNoVat));
            $hhdv->addChild('ThTien', $this->num($lineTotalNoVat));
            $hhdv->addChild('TSuat', self::VAT_RATE . '%');
        }

        // Tổng hợp thanh toán
        $tToan = $ndHDon->addChild('TToan');
        $tToan->addChild('TgTCThue', $this->num($totalWithoutVat));
        $tToan->addChild('TgTThue', $this->num($vatAmount));
        $tToan->addChild('TTCKTMai', $this->num((float) $order->discount_amount));
        $tToan->addChild('TgTTTBSo', $this->num($totalWithVat));
        $this->addCData($tToan->addChild('TgTTTBChu'), $this->amountInWords($totalWithVat));

        $dom = dom_import_simplexml($xml)->ownerDocument;
        $dom->formatOutput = true;

        return $dom->saveXML();
    }

    private function paymentMethodLabel(Order $order): string
    {
        $method = $order->payments()->latest()->first()?->payment_method ?? 'cash';

        return match ($method) {
            'cash' => 'TM',
            'card', 'bank_transfer', 'vnpay', 'momo', 'zalopay' => 'CK',
            default => 'TM/CK',
        };
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private function addCData(\SimpleXMLElement $node, string $value): void
    {
        $dom = dom_import_simplexml($node);
        $dom->appendChild($dom->ownerDocument->createCDATASection($value));
    }

    /** Đọc số tiền thành chữ tiếng Việt (đủ dùng cho hóa đơn, tối đa hàng tỷ). */
    public function amountInWords(float $amount): string
    {
        $amount = (int) round($amount);

        if ($amount === 0) {
            return 'Không đồng';
        }

        $units = ['', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
        $groups = [];
        $groupNames = ['', ' nghìn', ' triệu', ' tỷ'];

        while ($amount > 0) {
            $groups[] = $amount % 1000;
            $amount = intdiv($amount, 1000);
        }

        $readGroup = function (int $n, bool $full) use ($units): string {
            $hundreds = intdiv($n, 100);
            $tens = intdiv($n % 100, 10);
            $ones = $n % 10;
            $parts = [];

            if ($hundreds > 0 || $full) {
                $parts[] = $units[$hundreds] . ' trăm';
            }

            if ($tens > 1) {
                $parts[] = $units[$tens] . ' mươi';

                if ($ones === 1) {
                    $parts[] = 'mốt';
                } elseif ($ones === 5) {
                    $parts[] = 'lăm';
                } elseif ($ones > 0) {
                    $parts[] = $units[$ones];
                }
            } elseif ($tens === 1) {
                $parts[] = 'mười';

                if ($ones === 5) {
                    $parts[] = 'lăm';
                } elseif ($ones > 0) {
                    $parts[] = $units[$ones];
                }
            } else {
                if (($hundreds > 0 || $full) && $ones > 0) {
                    $parts[] = 'lẻ';
                }

                if ($ones > 0) {
                    $parts[] = $units[$ones];
                }
            }

            return implode(' ', $parts);
        };

        $result = [];

        for ($i = count($groups) - 1; $i >= 0; $i--) {
            if ($groups[$i] === 0) {
                continue;
            }

            $isHighest = $i === count($groups) - 1;
            $result[] = $readGroup($groups[$i], ! $isHighest) . $groupNames[$i];
        }

        $text = trim(preg_replace('/\s+/', ' ', implode(' ', $result)));

        return mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1) . ' đồng';
    }
}
