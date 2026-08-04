<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12px;
            color: #1a1a2e;
            background: #fff;
        }

        /* ── Header ────────────────────────────────── */
        .header {
            background: linear-gradient(135deg, #16213e 0%, #0f3460 100%);
            color: #fff;
            padding: 28px 36px;
            display: table;
            width: 100%;
        }
        .header-left { display: table-cell; vertical-align: middle; }
        .header-right { display: table-cell; text-align: right; vertical-align: middle; }
        .brand-name { font-size: 26px; font-weight: 700; letter-spacing: 1px; }
        .brand-tagline { font-size: 10px; color: rgba(255,255,255,0.7); margin-top: 2px; }
        .invoice-label { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; color: rgba(255,255,255,0.7); }
        .invoice-number { font-size: 20px; font-weight: 700; margin-top: 4px; }

        /* ── Meta bar ───────────────────────────────── */
        .meta-bar {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 36px;
            display: table;
            width: 100%;
        }
        .meta-cell { display: table-cell; width: 25%; vertical-align: top; }
        .meta-label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 3px; }
        .meta-value { font-size: 12px; font-weight: 600; color: #0f172a; }

        /* ── Body ───────────────────────────────────── */
        .body { padding: 28px 36px; }

        /* Parties */
        .parties { display: table; width: 100%; margin-bottom: 28px; }
        .party-cell { display: table-cell; width: 48%; vertical-align: top; }
        .party-divider { display: table-cell; width: 4%; }
        .party-title { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 8px; }
        .party-name { font-size: 14px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .party-detail { font-size: 11px; color: #64748b; margin-bottom: 2px; }

        /* Items table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table thead tr { background: #0f3460; color: #fff; }
        .items-table th { padding: 10px 14px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        .items-table th.right { text-align: right; }
        .items-table tbody tr { border-bottom: 1px solid #f1f5f9; }
        .items-table tbody tr:nth-child(even) { background: #f8fafc; }
        .items-table td { padding: 12px 14px; font-size: 11px; color: #374151; }
        .items-table td.right { text-align: right; font-weight: 600; }

        /* Totals */
        .totals { float: right; width: 280px; margin-bottom: 32px; }
        .totals-row { display: table; width: 100%; padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
        .totals-label { display: table-cell; font-size: 11px; color: #64748b; }
        .totals-value { display: table-cell; text-align: right; font-size: 11px; font-weight: 600; color: #0f172a; }
        .totals-total { border-top: 2px solid #0f3460 !important; border-bottom: none !important; padding-top: 10px !important; }
        .totals-total .totals-label { font-size: 13px; font-weight: 700; color: #0f172a; }
        .totals-total .totals-value { font-size: 16px; font-weight: 700; color: #0f3460; }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }

        /* Footer */
        .footer {
            clear: both;
            border-top: 2px solid #e2e8f0;
            padding: 20px 36px;
            display: table;
            width: 100%;
            background: #f8fafc;
        }
        .footer-left { display: table-cell; vertical-align: middle; }
        .footer-right { display: table-cell; text-align: right; vertical-align: middle; }
        .footer-note { font-size: 10px; color: #94a3b8; line-height: 1.6; }
        .footer-brand { font-size: 11px; font-weight: 700; color: #0f3460; }

        /* Type badge */
        .type-chip {
            display: inline-block;
            background: #ede9fe;
            color: #5b21b6;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-left">
        <div class="brand-name">🍽 Aventura</div>
        <div class="brand-tagline">Restaurant Management Platform</div>
    </div>
    <div class="header-right">
        <div class="invoice-label">Hóa đơn / Invoice</div>
        <div class="invoice-number">#{{ $invoice->invoice_number }}</div>
    </div>
</div>

<!-- Meta bar -->
<div class="meta-bar">
    <div class="meta-cell">
        <div class="meta-label">Ngày phát hành</div>
        <div class="meta-value">{{ optional($invoice->issued_on)->format('d/m/Y') ?? now()->format('d/m/Y') }}</div>
    </div>
    <div class="meta-cell">
        <div class="meta-label">Hạn thanh toán</div>
        <div class="meta-value">{{ optional($invoice->due_on)->format('d/m/Y') ?? '—' }}</div>
    </div>
    <div class="meta-cell">
        <div class="meta-label">Trạng thái</div>
        <div class="meta-value">
            <span class="status-badge {{ in_array($invoice->status, ['generated','sent','processed']) ? 'status-paid' : 'status-pending' }}">
                {{ $invoice->status === 'processed' ? 'Đã thanh toán' : ($invoice->status === 'sent' ? 'Đã gửi' : ($invoice->status === 'generated' ? 'Đã tạo' : 'Chờ xử lý')) }}
            </span>
        </div>
    </div>
    <div class="meta-cell">
        <div class="meta-label">Loại</div>
        <div class="meta-value"><span class="type-chip">{{ $invoice->type }}</span></div>
    </div>
</div>

<div class="body">
    <!-- Parties -->
    <div class="parties">
        <div class="party-cell">
            <div class="party-title">Người bán / Billed From</div>
            <div class="party-name">Aventura Platform</div>
            <div class="party-detail">platform@aventura.vn</div>
            <div class="party-detail">Restaurant Management SaaS</div>
        </div>
        <div class="party-divider"></div>
        <div class="party-cell">
            <div class="party-title">Người mua / Billed To</div>
            <div class="party-name">{{ $invoice->restaurant?->name ?? 'N/A' }}</div>
            @if($invoice->recipient_email)
                <div class="party-detail">{{ $invoice->recipient_email }}</div>
            @endif
            @if($invoice->restaurant?->phone)
                <div class="party-detail">{{ $invoice->restaurant->phone }}</div>
            @endif
        </div>
    </div>

    <!-- Items table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 40%;">Dịch vụ / Service</th>
                <th style="width: 20%;">Chu kỳ / Period</th>
                <th style="width: 20%;">Thời gian</th>
                <th class="right" style="width: 20%;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div style="font-weight: 700; color: #0f172a;">Gói {{ $invoice->subscription?->plan?->name ?? 'Dịch vụ Aventura' }}</div>
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">Restaurant Management Platform</div>
                </td>
                <td>
                    @php
                        $cycle = $invoice->subscription?->billing_cycle ?? 'monthly';
                        $cycleLabel = match($cycle) {
                            'yearly' => 'Hàng năm',
                            'quarterly' => 'Hàng quý',
                            default => 'Hàng tháng',
                        };
                    @endphp
                    {{ $cycleLabel }}
                </td>
                <td>
                    <div>{{ optional($invoice->subscription?->started_at)->format('d/m/Y') ?? '—' }}</div>
                    <div style="font-size: 10px; color: #94a3b8;">đến {{ optional($invoice->subscription?->ended_at)->format('d/m/Y') ?? '—' }}</div>
                </td>
                <td class="right">
                    {{ number_format((float) $invoice->subtotal, 0, ',', '.') }} {{ $invoice->currency }}
                </td>
            </tr>
            @if((float)$invoice->discount_amount > 0)
            <tr>
                <td colspan="3" style="color: #16a34a;">
                    Giảm giá
                    @if($invoice->subscription?->coupon_code)
                        (mã: {{ $invoice->subscription->coupon_code }})
                    @endif
                </td>
                <td class="right" style="color: #16a34a;">
                    -{{ number_format((float) $invoice->discount_amount, 0, ',', '.') }} {{ $invoice->currency }}
                </td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals">
        @if((float)$invoice->discount_amount > 0)
        <div class="totals-row">
            <div class="totals-label">Tạm tính</div>
            <div class="totals-value">{{ number_format((float) $invoice->subtotal, 0, ',', '.') }} {{ $invoice->currency }}</div>
        </div>
        <div class="totals-row">
            <div class="totals-label">Giảm giá</div>
            <div class="totals-value" style="color: #16a34a;">-{{ number_format((float) $invoice->discount_amount, 0, ',', '.') }} {{ $invoice->currency }}</div>
        </div>
        @endif
        <div class="totals-row totals-total">
            <div class="totals-label">TỔNG CỘNG</div>
            <div class="totals-value">{{ number_format((float) $invoice->total, 0, ',', '.') }} {{ $invoice->currency }}</div>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <div class="footer-left">
        <div class="footer-brand">Aventura Platform</div>
        <div class="footer-note">
            Cảm ơn bạn đã sử dụng dịch vụ Aventura.<br>
            Mọi thắc mắc vui lòng liên hệ: support@aventura.vn
        </div>
    </div>
    <div class="footer-right">
        <div class="footer-note" style="text-align: right;">
            Hóa đơn được tạo tự động bởi hệ thống<br>
            {{ now()->format('d/m/Y H:i') }} — Aventura Billing v2.0
        </div>
    </div>
</div>

</body>
</html>
