<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Đồng Hành Cùng Phát Triển - Aventura POS</title>
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin: 0 auto;
        }
        .header {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
            color: #ffffff;
            padding: 35px 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            margin: 8px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }
        .content {
            padding: 32px 28px;
            color: #334155;
            line-height: 1.7;
        }
        .intro {
            font-size: 16px;
            margin-bottom: 20px;
        }
        .survey-box {
            background-color: #f0fdfa;
            border-left: 4px solid #0d9488;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
        }
        .survey-box h3 {
            margin-top: 0;
            color: #0f766e;
            font-size: 16px;
        }
        .gift-box {
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
            border: 1px dashed #f97316;
            padding: 24px;
            border-radius: 8px;
            text-align: center;
            margin: 25px 0;
        }
        .gift-title {
            font-size: 15px;
            font-weight: bold;
            color: #ea580c;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .coupon-code {
            display: inline-block;
            background-color: #ffffff;
            color: #ea580c;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: 2px;
            padding: 10px 24px;
            border-radius: 6px;
            border: 1px solid #fed7aa;
            margin: 12px 0;
            box-shadow: 0 2px 4px rgba(234, 88, 12, 0.05);
        }
        .gift-desc {
            font-size: 13px;
            color: #7c2d12;
            margin: 0;
        }
        .button {
            display: inline-block;
            background-color: #0d9488;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 15px;
            border-radius: 6px;
            margin-top: 10px;
            box-shadow: 0 4px 6px rgba(13, 148, 136, 0.15);
            transition: all 0.2s ease;
        }
        .button:hover {
            background-color: #0f766e;
            transform: translateY(-1px);
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #f1f5f9;
        }
        .footer a {
            color: #0d9488;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Cảm ơn bạn đã đồng hành cùng Aventura POS</h1>
            <p>Chúng tôi luôn nỗ lực vì sự thành công của doanh nghiệp bạn</p>
        </div>
        <div class="content">
            <p class="intro">Kính gửi Anh/Chị <strong>{{ $ownerName }}</strong> - Chủ nhà hàng <strong>{{ $restaurantName }}</strong>,</p>
            
            <p>Lời đầu tiên, đội ngũ <strong>Aventura POS</strong> xin gửi lời cảm ơn chân thành nhất tới Anh/Chị vì đã tin tưởng lựa chọn sản phẩm của chúng tôi làm người bạn đồng hành quản lý vận hành.</p>
            
            <p>Gần đây, hệ thống nhận thấy nhà hàng của bạn có một số thay đổi trong tần suất sử dụng phần mềm. Chúng tôi rất quan tâm và mong muốn được biết liệu Anh/Chị có đang gặp bất kỳ khó khăn nào trong quá trình vận hành, hoặc có tính năng nào chưa đáp ứng được kỳ vọng của nhà hàng hay không?</p>
            
            <div class="survey-box">
                <h3>Khảo sát chất lượng dịch vụ & Hỗ trợ kỹ thuật</h3>
                <p style="margin: 0 0 15px 0; font-size: 14px;">Chỉ mất 2 phút chia sẻ, ý kiến của Anh/Chị sẽ giúp chúng tôi hoàn thiện sản phẩm và trực tiếp cử chuyên viên liên hệ giải quyết các khó khăn cho nhà hàng.</p>
                <a href="https://aventura.com/customer-success-survey?restaurant={{ $restaurantName }}" class="button" target="_blank">Chia sẻ ý kiến ngay</a>
            </div>

            <p>Nhân dịp này, Aventura POS xin gửi tặng nhà hàng món quà tri ân đặc biệt thay lời cảm ơn sâu sắc:</p>

            <div class="gift-box">
                <div class="gift-title">🎁 Quà tặng gia hạn gói dịch vụ 🎁</div>
                <div class="coupon-code">{{ $promoCode }}</div>
                <p class="gift-desc"><strong>Giảm ngay 30%</strong> chi phí khi gia hạn gói dịch vụ tiếp theo của nhà hàng.</p>
            </div>

            <p>Nếu Anh/Chị cần bất kỳ sự hỗ trợ kỹ thuật hoặc tư vấn vận hành ca trực nào, xin vui lòng phản hồi email này hoặc liên hệ trực tiếp hotline CSKH: <strong>1900.xxxx (hoạt động 24/7)</strong>.</p>
            
            <p style="margin-top: 30px;">Chúc nhà hàng kinh doanh hồng phát và luôn phát đạt!</p>
            <p>Trân trọng,<br><strong>Đội ngũ Customer Success - Aventura POS</strong></p>
        </div>
        <div class="footer">
            Bản quyền © {{ date('Y') }} Aventura POS. Bảo lưu mọi quyền.<br>
            Bạn nhận được thư này vì là chủ sở hữu tài khoản doanh nghiệp trên <a href="https://aventura.com">Aventura POS</a>.
        </div>
    </div>
</body>
</html>
