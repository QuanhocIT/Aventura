<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Aventura - {{ $restaurant->name }} - {{ $date }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', Times, serif; font-size: 12px; color: #1a1a2e; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #E97316; padding-bottom: 15px; margin-bottom: 25px; }
        .brand { font-size: 24px; font-weight: 900; color: #E97316; }
        .subtitle { font-size: 10px; color: #666; margin-top: 4px; }
        .meta { text-align: right; font-size: 10px; color: #888; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 14px; font-weight: 700; color: #1a1a2e; border-left: 4px solid #E97316; padding-left: 10px; margin-bottom: 10px; }
        .grid { display: table; width: 100%; margin-bottom: 15px; }
        .grid-cell { display: table-cell; width: 25%; padding: 12px; background: #f8f9fa; border-radius: 8px; text-align: center; border: 1px solid #eee; }
        .grid-cell .value { font-size: 20px; font-weight: 900; color: #1a1a2e; }
        .grid-cell .label { font-size: 9px; color: #888; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { background: #f1f5f9; padding: 8px 12px; text-align: left; font-weight: 700; font-size: 10px; text-transform: uppercase; color: #666; border-bottom: 2px solid #e2e8f0; }
        td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; text-align: center; font-size: 9px; color: #aaa; }
        .highlight { color: #E97316; font-weight: 700; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">Aventura</div>
            <div class="subtitle">{{ $restaurant->name }}</div>
        </div>
        <div class="meta">
            <div><strong>Ngay:</strong> {{ $date }}</div>
            <div><strong>Goi:</strong> {{ $restaurant->plan?->name ?? 'Free' }}</div>
            <div>Xuat luc: {{ now()->format('H:i d/m/Y') }}</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Tong quan Doanh thu</div>
        @if($summary)
        <table>
            <tr>
                <td><strong>Doanh thu rong</strong></td>
                <td class="text-right highlight">{{ number_format($summary->net_revenue ?? 0) }} VND</td>
                <td><strong>Don hoan thanh</strong></td>
                <td class="text-right">{{ $summary->completed_order_count ?? 0 }}</td>
            </tr>
            <tr>
                <td><strong>Doanh thu gop</strong></td>
                <td class="text-right">{{ number_format($summary->gross_revenue ?? 0) }} VND</td>
                <td><strong>Don da huy</strong></td>
                <td class="text-right">{{ $summary->cancelled_order_count ?? 0 }}</td>
            </tr>
            <tr>
                <td><strong>Giam gia</strong></td>
                <td class="text-right">{{ number_format($summary->discount_total ?? 0) }} VND</td>
                <td><strong>Tong don</strong></td>
                <td class="text-right">{{ $summary->order_count ?? 0 }}</td>
            </tr>
            <tr>
                <td><strong>Gia von (COGS)</strong></td>
                <td class="text-right">{{ number_format($summary->cogs_amount ?? 0) }} VND</td>
                <td><strong>Bien loi nhuan</strong></td>
                <td class="text-right highlight">
                    @php
                        $margin = ($summary->net_revenue > 0) ? round(($summary->net_revenue - $summary->cogs_amount) / $summary->net_revenue * 100, 1) : 0;
                    @endphp
                    {{ $margin }}%
                </td>
            </tr>
        </table>
        @else
        <p style="text-align:center; color:#888; padding:20px;">Chua co du lieu bao cao cho ngay nay.</p>
        @endif
    </div>

    <div class="footer">
        Bao cao duoc tao tu dong boi Aventura SaaS &bull; {{ $restaurant->name }} &bull; {{ $date }}
    </div>
</body>
</html>
