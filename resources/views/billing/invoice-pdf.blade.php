<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Aventura - Hoa don {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a2e; padding: 40px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #E97316; padding-bottom: 15px; margin-bottom: 25px; }
        .brand { font-size: 24px; font-weight: 900; color: #E97316; }
        .subtitle { font-size: 10px; color: #666; margin-top: 4px; }
        .meta { text-align: right; font-size: 10px; color: #888; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 14px; font-weight: 700; color: #1a1a2e; border-left: 4px solid #E97316; padding-left: 10px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { background: #f1f5f9; padding: 8px 12px; text-align: left; font-weight: 700; font-size: 10px; text-transform: uppercase; color: #666; border-bottom: 2px solid #e2e8f0; }
        td { padding: 8px 12px; border-bottom: 1px solid #f1f5f9; }
        .text-right { text-align: right; }
        .totals td { border-bottom: none; padding: 4px 12px; }
        .totals .grand-total td { font-size: 15px; font-weight: 900; color: #E97316; border-top: 2px solid #1a1a2e; padding-top: 10px; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 9px; font-weight: 700; text-transform: uppercase; background: #dcfce7; color: #166534; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #eee; text-align: center; font-size: 9px; color: #aaa; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">Aventura</div>
            <div class="subtitle">Hoa don thanh toan dich vu</div>
        </div>
        <div class="meta">
            <div><strong>So hoa don:</strong> {{ $invoice->invoice_number }}</div>
            <div><strong>Ngay xuat:</strong> {{ optional($invoice->issued_on)->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
            <div><strong>Han thanh toan:</strong> {{ optional($invoice->due_on)->format('d/m/Y') ?? '-' }}</div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Thong tin khach hang</div>
        <table>
            <tr>
                <td style="width:50%"><strong>Nha hang:</strong> {{ $invoice->restaurant?->name ?? 'N/A' }}</td>
                <td><strong>Ma so thue:</strong> {{ $invoice->restaurant?->tax_code ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Dia chi:</strong> {{ $invoice->restaurant?->address ?? '-' }}</td>
                <td><strong>Lien he:</strong> {{ $invoice->recipient_email ?? $invoice->restaurant?->email ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Chi tiet dich vu</div>
        <table>
            <thead>
                <tr>
                    <th>Noi dung</th>
                    <th class="text-right">So tien</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        Goi dich vu: {{ $invoice->subscription?->plan?->name ?? 'N/A' }}
                        <br>
                        <span style="color:#888; font-size:10px;">Loai giao dich: {{ $invoice->type }}</span>
                    </td>
                    <td class="text-right">{{ number_format((float) $invoice->subtotal, 0, ',', '.') }} {{ $invoice->currency }}</td>
                </tr>
            </tbody>
        </table>

        <table class="totals" style="margin-top: 10px;">
            <tr>
                <td class="text-right" style="width:80%;">Tam tinh</td>
                <td class="text-right">{{ number_format((float) $invoice->subtotal, 0, ',', '.') }} {{ $invoice->currency }}</td>
            </tr>
            @if ((float) $invoice->discount_amount > 0)
            <tr>
                <td class="text-right">Giam gia</td>
                <td class="text-right">-{{ number_format((float) $invoice->discount_amount, 0, ',', '.') }} {{ $invoice->currency }}</td>
            </tr>
            @endif
            <tr class="grand-total">
                <td class="text-right">Tong cong</td>
                <td class="text-right">{{ number_format((float) $invoice->total, 0, ',', '.') }} {{ $invoice->currency }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <span class="status-badge">{{ strtoupper($invoice->status) }}</span>
    </div>

    <div class="footer">
        Hoa don duoc tao tu dong boi he thong Aventura SaaS &bull; {{ now()->format('H:i d/m/Y') }}
    </div>
</body>
</html>
