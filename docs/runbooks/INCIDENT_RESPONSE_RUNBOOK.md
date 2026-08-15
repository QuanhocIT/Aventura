# QUY TRÌNH XỬ LÝ SỰ CỐ VẬN HÀNH (INCIDENT RESPONSE RUNBOOK) — AVENTURA

Dành cho Đội ngũ Kỹ thuật & Vận hành Production của Aventura SaaS.

---

## 1. PHÂN LOẠI MỨC ĐỘ SỰ CỐ (INCIDENT SEVERITY LEVEL)

| Mức độ | Tiêu chí | Thời gian phản ứng (SLA) | Trách nhiệm chính |
| :--- | :--- | :--- | :--- |
| **P1 — Critical** | Toàn bộ hệ thống sập (Outage), POS/Thanh toán ngưng trệ hoàn toàn, mất dữ liệu. | **< 15 phút** | Lead Engineer + DevOps + On-call |
| **P2 — Major** | Một microservice hoặc tính năng quan trọng bị ngắt (như Reverb, Email, Chatbot, Webhook trễ). | **< 1 giờ** | Backend Engineer + On-call |
| **P3 — Minor** | Lỗi nhỏ về giao diện, chậm tải dashboard, lỗi đơn lẻ không ảnh hưởng giao dịch POS. | **< 8 giờ** | Support / Developer |

---

## 2. KÊNH CẢNH BÁO & ON-CALL

- **Cảnh báo tự động**: Telegram Bot / Slack Webhook liên kết với `/api/ready` probe và `OperationsCenterController`.
- **Kênh trực sự cố khẩn cấp**: `#incident-production` (Slack / Zalo Chat).
- **Quy trình escalation**:
  1. System Alert kích hoạt -> Bắn thông báo về Telegram/Slack.
  2. Kỹ sư On-Call nhận thông báo, xác nhận (ACK) trong 5 phút.
  3. Nếu không có ACK sau 10 phút -> Gọi tự động/SMS cho Lead Engineer.

---

## 3. RUNBOOKS KHÔI PHỤC DỊCH VỤ

### 3.1. Database MySQL Bị Sập / Treo Connection
1. **Kiểm tra trạng thái**: `docker compose ps mysql`
2. **Xem log**: `docker compose logs --tail=100 mysql`
3. **Restart service**: `docker compose restart mysql`
4. **Nếu cạn dung lượng đĩa**:
   - Kiểm tra: `df -h`
   - Giải phóng log: `docker system prune -f`
5. **Khôi phục từ Backup mới nhất**:
   - Chạy lệnh: `php artisan backup:restore --file=backup_latest.sql.gz`

### 3.2. Redis Bị Đầy Memory / Queue Bị Kẹt (Backlog High)
1. **Kiểm tra memory & key count**: `docker compose exec redis redis-cli -a $REDIS_PASSWORD info memory`
2. **Xác nhận policy**: Phải đảm bảo `maxmemory-policy noeviction` để tránh mất queue job.
3. **Khởi động lại queue worker**: `docker compose exec app php artisan queue:restart`
4. **Kiểm tra failed jobs**: `docker compose exec app php artisan queue:failed`
5. **Retry failed jobs safe**: `docker compose exec app php artisan queue:retry all`

### 3.3. Webhook Thanh Toán (VietQR / SePay / VNPay / MoMo) Bị Thất Bại
1. **Kiểm tra log webhook**: Tìm theo `correlation_id` trong `storage/logs/laravel.log`.
2. **Kiểm tra chữ ký (Signature) & Số tiền (Amount)**.
3. **Re-process thủ công qua Inbox Log**:
   - Kiểm tra `payment_webhook_logs` table.
   - Chạy lại job đối soát: `php artisan payments:reconcile`

### 3.4. Python Microservices (Email / Chatbot / Analytics) Mất Kết Nối
1. **Kiểm tra probe**: `curl -i http://localhost:8001/health` (Email), `8002` (Chatbot), `8003` (Analytics).
2. **Rebuild / Restart**: `docker compose restart email-service chatbot-service analytics-service`

---

## 4. QUY TRÌNH POSTMORTEM (BÁO CÁO SAU SỰ CỐ P1/P2)

Mọi sự cố P1/P2 bắt buộc phải thực hiện báo cáo Postmortem trong vòng 24h sau khi khắc phục xong:
1. **Timeline chi tiết**: Thời điểm phát sinh, thời điểm phát hiện, thời điểm khắc phục.
2. **Root Cause Analysis (RCA)**: Nguyên nhân gốc rễ (dùng kỹ thuật 5 Whys).
3. **Tác động**: Số lượng nhà hàng / giao dịch bị ảnh hưởng.
4. **Hành động khắc phục (Action Items)**: Danh sách việc cần sửa code/infra kèm deadline và người phụ trách để chống tái diễn.
