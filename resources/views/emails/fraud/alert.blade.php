<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cảnh Báo Gian Lận</title>
    <style>
        body {
            font-family: Arial, sans-serif;
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
            background-color: #e11d48;
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
            background-color: #fff1f2;
            border-left: 4px solid #f43f5e;
            padding: 16px;
            border-radius: 4px;
            margin-bottom: 20px;
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
            <h1>🚨 CẢNH BÁO AN NINH HỆ THỐNG 🚨</h1>
        </div>
        <div class="content">
            <p>Xin chào <strong>Chủ nhà hàng {{ $restaurantName }}</strong>,</p>
            <p>Hệ thống AI giám sát nội bộ Aventura phát hiện một hành vi có chỉ số rủi ro cao xảy ra tại nhà hàng của bạn. Chi tiết dưới đây:</p>
            
            <div class="alert-box">
                <strong>Hành vi phát hiện:</strong> {{ $alert['violation_type'] }}
            </div>

            <table class="details-table">
                <tr>
                    <td class="label">Nhân sự liên quan:</td>
                    <td><strong>{{ $alert['employee_name'] }}</strong></td>
                </tr>
                <tr>
                    <td class="label">Mức độ nghiêm trọng:</td>
                    <td><span style="color: #e11d48; font-weight: bold; text-transform: uppercase;">{{ $alert['severity'] }}</span></td>
                </tr>
                <tr>
                    <td class="label">Chỉ số rủi ro AI:</td>
                    <td><strong>{{ $alert['risk_score'] }}%</strong></td>
                </tr>
                <tr>
                    <td class="label">Chi tiết mô tả:</td>
                    <td>{{ $alert['description'] }}</td>
                </tr>
            </table>

            <p style="margin-top: 25px;">Khuyến nghị bạn đăng nhập ngay vào trang quản trị để rà soát lại nhật ký kiểm toán ca trực.</p>
        </div>
        <div class="footer">
            Đây là email tự động gửi từ Hệ thống Giám sát Nội bộ Aventura POS.<br>
            Vui lòng không trả lời email này.
        </div>
    </div>
</body>
</html>
