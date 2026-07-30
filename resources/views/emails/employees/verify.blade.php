<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Thư mời nhận việc - Aventura</title>
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f5f7; color: #334155; margin: 0; padding: 40px 20px;">
    <table align="center" width="100%" max-width="600px" style="background-color: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; padding: 40px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); margin: 0 auto;">
        <tr>
            <td>
                <div style="text-align: center; margin-bottom: 30px;">
                    <h2 style="margin: 0; font-size: 24px; font-weight: 800; color: #4f46e5;">AVENTURA POS</h2>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 1px;">Thư mời nhận việc & xác thực tài khoản</p>
                </div>

                <p style="font-size: 15px; line-height: 1.6;">Xin chào <strong>{{ $employeeName }}</strong>,</p>

                <p style="font-size: 15px; line-height: 1.6;">
                    Chúc mừng! Bạn đã nhận được lời mời làm việc tại nhà hàng <strong>{{ $restaurantName }}</strong> với chức danh <strong>{{ $jobTitle }}</strong>.
                </p>

                <p style="font-size: 15px; line-height: 1.6;">
                    Để kích hoạt hồ sơ nhân viên và tài khoản đăng nhập của bạn trên hệ thống quản trị vận hành Aventura POS, vui lòng bấm vào nút xác nhận dưới đây:
                </p>

                <div style="text-align: center; margin: 35px 0;">
                    <a href="{{ $verificationUrl }}" style="background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 14px 28px; font-size: 14px; font-weight: 700; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2); transition: all 0.2s; display: inline-block;">
                        Xác Nhận Nhận Việc & Kích Hoạt Tài Khoản
                    </a>
                </div>

                <p style="font-size: 12px; color: #94a3b8; line-height: 1.6; text-align: center; background-color: #f8fafc; padding: 12px; border-radius: 10px; border: 1px dashed #cbd5e1;">
                    ⚠️ Lời mời này có giá trị xác thực trong vòng 3 ngày kể từ khi được gửi đi. 
                </p>

                <p style="font-size: 14px; line-height: 1.6; margin-top: 30px;">
                    Trân trọng,<br>
                    <strong>Đội ngũ quản trị {{ $restaurantName }} & Aventura POS</strong>
                </p>

                <div style="margin-top: 40px; border-top: 1px solid #f1f5f9; padding-top: 20px; text-align: center; font-size: 11px; color: #94a3b8;">
                    Hệ thống trích xuất tự động từ Aventura POS. Vui lòng không trả lời thư này.
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
