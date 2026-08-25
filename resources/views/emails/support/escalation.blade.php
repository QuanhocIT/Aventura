<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cảnh Báo Leo Thang SLA</title>
    <style>
        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #f4f6f8;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e1e8ed;
        }
        .header {
            background-color: #dc2626;
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .content {
            padding: 24px;
            color: #334155;
            line-height: 1.6;
        }
        .alert-box {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .details-table td {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .details-table td.label {
            font-weight: bold;
            color: #64748b;
            width: 160px;
        }
        .btn-link {
            display: inline-block;
            background-color: #4f46e5;
            color: #ffffff !important;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            margin-top: 20px;
            text-align: center;
        }
        .footer {
            background-color: #f8fafc;
            padding: 16px;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 CẢNH BÁO LEO THANG SLA KHẨN CẤP 🚨</h1>
        </div>
        <div class="content">
            <p>Xin chào quản trị viên <strong>{{ $admin->name }}</strong>,</p>
            <p>Hệ thống giám sát chất lượng dịch vụ (SLA Monitor) phát hiện một yêu cầu hỗ trợ thuộc gói dịch vụ cao cấp đã quá hạn xử lý ban đầu mà chưa được nhân viên kỹ thuật phản hồi.</p>
            
            <div class="alert-box">
                <strong>Sự cố:</strong> Một ticket từ khách hàng gói <strong>Enterprise</strong> đã trôi qua hơn 1 giờ kể từ lúc tạo mà không có bất kỳ phản hồi nào từ bộ phận hỗ trợ kỹ thuật POS.
            </div>

            <table class="details-table">
                <tr>
                    <td class="label">Mã Ticket:</td>
                    <td><strong>{{ $ticket->code }}</strong></td>
                </tr>
                <tr>
                    <td class="label">Nhà hàng:</td>
                    <td><strong>{{ $ticket->restaurant?->name ?? 'Hệ thống' }}</strong></td>
                </tr>
                <tr>
                    <td class="label">Gói dịch vụ:</td>
                    <td><span style="color: #dc2626; font-weight: bold; text-transform: uppercase;">Enterprise</span></td>
                </tr>
                <tr>
                    <td class="label">Tiêu đề hỗ trợ:</td>
                    <td><strong>{{ $ticket->title }}</strong></td>
                </tr>
                <tr>
                    <td class="label">Thời gian tạo:</td>
                    <td>{{ $ticket->created_at->format('H:i d/m/Y') }} (Đã trôi qua hơn 1 giờ)</td>
                </tr>
                <tr>
                    <td class="label">Thời hạn SLA phản hồi:</td>
                    <td>{{ $ticket->sla_due_at ? $ticket->sla_due_at->format('H:i d/m/Y') : 'Chưa thiết lập' }}</td>
                </tr>
                <tr>
                    <td class="label">Nội dung yêu cầu:</td>
                    <td>{{ $ticket->description }}</td>
                </tr>
            </table>

            <div style="text-align: center; margin-top: 25px;">
                <a href="{{ url('/super-admin/support') }}" class="btn-link" target="_blank">TRUY CẬP ĐỂ XỬ LÝ NGAY</a>
            </div>
        </div>
        <div class="footer">
            Đây là email tự động từ Hệ thống SLA & Escalation Monitor của Aventura POS.<br>
            Vui lòng đăng nhập trang Superadmin để can thiệp kịp thời.
        </div>
    </div>
</body>
</html>
