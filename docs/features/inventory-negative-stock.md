# Quy trình chuẩn xử lý âm nguyên liệu

## Mục tiêu

Âm nguyên liệu là một sự cố kiểm soát tồn kho, không phải một con số được phép xóa bằng cách sửa trực tiếp dòng tồn. Hệ thống phải giữ nguyên giao dịch làm phát sinh âm, yêu cầu hành động khắc phục bằng giao dịch kho hợp lệ và lưu được người chịu trách nhiệm, người phê duyệt, người xác minh cùng bằng chứng đối chiếu.

## Luồng bắt buộc

1. **Phát hiện tự động**: khi `quantity_on_hand < 0`, hệ thống tạo một hồ sơ `NEG-YYYYMMDD-XXXXXX`, chụp số âm ban đầu, giá trị ước tính, giao dịch nguồn, mức độ và hạn SLA.
2. **Tiếp nhận/điều tra**: quản lý hoặc phụ trách kho chọn nguyên nhân gốc, mô tả bằng chứng, giao người phụ trách, ghi hành động tức thời và hành động phòng ngừa tái diễn.
3. **Phê duyệt theo rủi ro**: hồ sơ Cao/Critical phải ở `pending_owner_approval`; Chủ doanh nghiệp phê duyệt hoặc từ chối kèm ghi chú.
4. **Khắc phục**: thực hiện Nhập hàng, Điều chuyển, Kiểm kê hoặc Điều chỉnh trong đúng module nghiệp vụ. Không cập nhật `inventories.quantity_on_hand` trực tiếp để né sổ giao dịch.
5. **Gửi đối chiếu**: khi tồn về 0 hoặc dương, người phụ trách phải gửi yêu cầu đối chiếu. Backend bắt buộc tìm thấy một giao dịch nhập/điều chỉnh hợp lệ sau thời điểm phát hiện.
6. **Xác minh độc lập và chốt**: người lập phương án không được tự xác minh, trừ Chủ doanh nghiệp/Super Admin có dấu rõ là override. Khi xác minh, hệ thống kiểm tra lại tồn hiện tại, liên kết giao dịch bù và đóng hồ sơ.

## Mức độ và SLA mặc định

| Mức độ | Điều kiện mặc định | SLA | Phê duyệt |
| --- | --- | ---: | --- |
| Critical | Giá trị >= 5.000.000đ hoặc số lượng >= 20 | 4 giờ | Chủ doanh nghiệp |
| Cao | Giá trị >= 1.000.000đ hoặc số lượng >= 10 | 24 giờ | Chủ doanh nghiệp |
| Trung bình | Giá trị >= 200.000đ hoặc số lượng >= 3 | 48 giờ | Theo quản lý |
| Thấp | Còn lại | 72 giờ | Theo quản lý |

SLA là mặc định an toàn; có thể đưa ngưỡng vào cấu hình nhà hàng sau mà không thay đổi hợp đồng trạng thái.

## Trạng thái và nguyên tắc chuyển trạng thái

- `open`: vừa phát hiện, chưa có phương án.
- `in_progress`: đã có phương án hợp lệ và đang khắc phục.
- `pending_owner_approval`: phương án Cao/Critical đang chờ Chủ doanh nghiệp.
- `pending_verification`: tồn đã về 0/dương và đã gửi yêu cầu đối chiếu với giao dịch bù.
- `resolved`: đã có người xác minh và chốt.

Nếu tồn âm trở lại trong lúc chờ đối chiếu, hồ sơ được mở lại về `in_progress`, tăng `reopen_count` và ghi lý do. Hồ sơ không bị xóa hoặc tự chuyển `resolved` chỉ vì có phiếu nhập bù.

## Phân quyền

- Nhân viên kho/inventory staff: xem hồ sơ trong phạm vi được phân công.
- Quản lý chi nhánh/Trưởng Kho Tổng: lập phương án, giao việc, gửi đối chiếu và xử lý trong phạm vi kho.
- Chủ doanh nghiệp: xem toàn hệ thống, phê duyệt Cao/Critical và được override xác minh độc lập khi có lý do.
- Mọi thao tác trạng thái đều ghi vào `inventory_negative_case_events` và `audit_logs` khi có phiên người dùng.

## Các module liên quan

- **Bán hàng/BOM**: giao dịch `usage` là nguồn phát sinh thường gặp; không tự động làm âm thành dương.
- **Nhập hàng/GRN**: giao dịch `purchase` là một dạng bằng chứng khắc phục.
- **Điều chuyển Kho Tổng**: giao dịch `transfer` được dùng để bù cho kho nhận hoặc phân tích thiếu hụt.
- **Kiểm kê & Điều chỉnh**: chỉ phiên kiểm kê/điều chỉnh được phê duyệt mới có giá trị làm bằng chứng.
- **Readiness/Safety gate**: bao gồm cả hồ sơ chờ Chủ duyệt và chờ đối chiếu; không cho coi chi nhánh là sẵn sàng khi quy trình chưa đóng.

## API nghiệp vụ

- `GET /api/inventory/negative-stock-cases/{id}`: xem timeline, giao dịch nguồn và các giao dịch liên quan.
- `POST .../{id}/plan`: lập/cập nhật nguyên nhân, hành động và người phụ trách.
- `POST .../{id}/approve`: Chủ doanh nghiệp phê duyệt/từ chối.
- `POST .../{id}/submit-verification`: gửi hồ sơ sang chờ đối chiếu sau khi đã có giao dịch bù.
- `POST .../{id}/verify`: xác minh độc lập và chốt hồ sơ.

