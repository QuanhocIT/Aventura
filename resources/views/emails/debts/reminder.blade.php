<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nhắc Nhở Công Nợ - Aventura</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin: 0 auto;
        }
        .header {
            background-color: #1e3a8a; /* Deep Navy Indigo */
            color: #ffffff;
            padding: 28px 24px;
            text-align: center;
        }
        .header.receivable {
            background-color: #0d9488; /* Teal for customer relationships */
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 28px 24px;
            color: #334155;
            line-height: 1.6;
        }
        .info-box {
            background-color: #f1f5f9;
            border-left: 4px solid #3b82f6;
            padding: 18px;
            border-radius: 6px;
            margin-bottom: 24px;
        }
        .info-box.overdue {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #f8fafc;
            border-radius: 8px;
            overflow: hidden;
        }
        .details-table th, .details-table td {
            padding: 12px 16px;
            text-align: left;
            font-size: 14px;
        }
        .details-table th {
            background-color: #e2e8f0;
            color: #475569;
            font-weight: 600;
        }
        .details-table td {
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        .details-table td.label {
            font-weight: 600;
            color: #64748b;
            width: 150px;
        }
        .action-button {
            display: inline-block;
            background-color: #1e3a8a;
            color: #ffffff;
            text-decoration: none;
            padding: 12px 24px;
            font-weight: 600;
            border-radius: 6px;
            margin-top: 15px;
            text-align: center;
        }
        .action-button.receivable {
            background-color: #0d9488;
        }
        .footer {
            background-color: #f1f5f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        @if($type === 'payable')
            <!-- Supplier Payable Header -->
            <div class="header">
                <h1>⏰ NHẮC NHỞ CÔNG NỢ PHẢI TRẢ (SUPPLIER PO)</h1>
            </div>
            <div class="content">
                <p>Xin chào <strong>Chủ nhà hàng / Ban quản lý</strong>,</p>
                <p>Hệ thống Aventura POS xin gửi thông báo cập nhật về khoản công nợ nhà cung cấp sắp đến hạn hoặc đã quá hạn của nhà hàng:</p>

                @if($daysToDueDate < 0)
                    <div class="info-box overdue">
                        <strong>⚠️ Cảnh báo:</strong> Khoản nợ này đã <strong>quá hạn {{ abs($daysToDueDate) }} ngày</strong>! Vui lòng thanh toán cho nhà cung cấp để tránh ảnh hưởng đến SLA và chuỗi cung ứng.
                    </div>
                @else
                    <div class="info-box">
                        <strong>ℹ️ Thông tin:</strong> Khoản nợ sẽ đến hạn thanh toán trong vòng <strong>{{ $daysToDueDate }} ngày</strong> tới.
                    </div>
                @endif

                <table class="details-table">
                    <tr>
                        <td class="label">Nhà cung cấp:</td>
                        <td><strong>{{ $debt->supplier->name ?? '—' }}</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Đơn hàng PO:</td>
                        <td><code>{{ $debt->purchaseOrder->po_number ?? '—' }}</code></td>
                    </tr>
                    <tr>
                        <td class="label">Tổng giá trị đơn:</td>
                        <td>{{ number_format($debt->amount) }}đ</td>
                    </tr>
                    <tr>
                        <td class="label">Đã thanh toán:</td>
                        <td>{{ number_format($debt->paid_amount) }}đ</td>
                    </tr>
                    <tr>
                        <td class="label">Còn nợ:</td>
                        <td style="color: #ef4444; font-weight: bold;">{{ number_format($debt->amount - $debt->paid_amount) }}đ</td>
                    </tr>
                    <tr>
                        <td class="label">Hạn thanh toán:</td>
                        <td><strong>{{ \Carbon\Carbon::parse($debt->due_date)->format('d/m/Y') }}</strong></td>
                    </tr>
                </table>

                <p>Vui lòng truy cập trang Quản lý công nợ để thực hiện ghi nhận thanh toán cho nhà cung cấp.</p>
            </div>
        @else
            <!-- Customer Receivable Header -->
            <div class="header receivable">
                <h1>⏰ THÔNG BÁO DƯ NỢ THANH TOÁN ĐƠN HÀNG</h1>
            </div>
            <div class="content">
                <p>Kính gửi Quý khách hàng <strong>{{ $debt->customer->full_name ?? 'Khách hàng' }}</strong>,</p>
                <p>Chúng tôi xin gửi lời chào trân trọng từ nhà hàng. Hệ thống ghi nhận Quý khách đang có khoản dư nợ mua hàng chưa hoàn tất thanh toán như sau:</p>

                @if($daysToDueDate < 0)
                    <div class="info-box overdue">
                        <strong>⚠️ Nhắc nhở quá hạn:</strong> Khoản dư nợ này đã quá hạn thanh toán <strong>{{ abs($daysToDueDate) }} ngày</strong>. Kính mong Quý khách sớm sắp xếp hoàn tất thanh toán.
                    </div>
                @else
                    <div class="info-box">
                        <strong>ℹ️ Nhắc nhở thanh toán:</strong> Khoản dư nợ sẽ đến hạn thanh toán trong vòng <strong>{{ $daysToDueDate }} ngày</strong> tới.
                    </div>
                @endif

                <table class="details-table">
                    <tr>
                        <td class="label">Mã đơn hàng:</td>
                        <td><code>{{ $debt->order->order_number ?? '—' }}</code></td>
                    </tr>
                    <tr>
                        <td class="label">Số tiền mua hàng:</td>
                        <td>{{ number_format($debt->amount) }}đ</td>
                    </tr>
                    <tr>
                        <td class="label">Đã thanh toán:</td>
                        <td>{{ number_format($debt->received_amount) }}đ</td>
                    </tr>
                    <tr>
                        <td class="label">Còn nợ lại:</td>
                        <td style="color: #0d9488; font-weight: bold;">{{ number_format($debt->amount - $debt->received_amount) }}đ</td>
                    </tr>
                    <tr>
                        <td class="label">Hạn thanh toán:</td>
                        <td><strong>{{ \Carbon\Carbon::parse($debt->due_date)->format('d/m/Y') }}</strong></td>
                    </tr>
                </table>

                <p>Quý khách vui lòng liên hệ bộ phận thu ngân hoặc ban quản lý nhà hàng để đối soát và tất toán khoản dư nợ này.</p>
                <p>Xin trân trọng cảm ơn sự đồng hành và hợp tác của Quý khách!</p>
            </div>
        @endif
        <div class="footer">
            Đây là email tự động gửi từ hệ thống quản lý tài chính Aventura POS.<br>
            Vui lòng không trả lời trực tiếp email này.
        </div>
    </div>
</body>
</html>
