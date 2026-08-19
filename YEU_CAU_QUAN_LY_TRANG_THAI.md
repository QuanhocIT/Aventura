# Yêu cầu kiểm soát chi nhánh — Trạng thái hoàn thành

> **15/15 HOÀN THÀNH ✅** · Mỗi mục đều có test tự động.

---

- **✅** Quản lý phê duyệt hoàn tiền / hủy món (bếp chưa bấm chế biến) / báo hỏng, phê duyệt mọi vấn đề chi nhánh; các phê duyệt được ghi lại và báo Chủ.

- **✅** Yêu cầu gửi cho **cả** quản lý chi nhánh + Chủ; hiện "ai đã phê duyệt" + trạng thái cho người gửi (trang "Yêu cầu của tôi").

- **✅** Chênh lệch tiền cuối ca: hai chữ ký + ảnh + giải trình + **đếm tiền mù**; Chủ tự cấu hình bật/tắt đếm mù, ngưỡng giải trình, ngưỡng kèm ảnh, bàn giao 2 chữ ký (trang Chốt ca → "Kiểm soát tiền mặt").

- **✅** Chi phí khẩn cấp: Chủ đặt **ngân sách/hạn mức chi tiêu theo chi nhánh**, quản lý bắt buộc hoá đơn + chặn vượt hạn mức; Chủ được vượt.

- **✅** Hàng hỏng/hết hạn/hao hụt: **lý do bắt buộc** + **ảnh hàng hủy bắt buộc** + báo cáo qua Chủ/quản lý duyệt + bảng phân tích tỷ lệ hao hụt.

- **✅** Kho giao thiếu/sai: **nhận một phần** (số thực nhận từng món) + đối chiếu PO↔thực nhận↔hoá đơn → đóng băng; khi lệch **bắt buộc ảnh + lý do chênh lệch** + biên bản báo **Chủ và Trưởng kho**.

- **✅** Điều chuyển liên chi nhánh: Quản lý tạo yêu cầu → **Chủ định tuyến** chọn chi nhánh thừa + **sinh mã giao nhận** → chi nhánh thừa xuất → chi nhánh thiếu nhận bằng mã; **người xuất ≠ người nhận**, xác nhận hai bước.

- **✅** Nguyên liệu sắp hết hạn: **khóa lô** (không cho tiêu thụ) + **gửi yêu cầu kho thu hồi** (báo Chủ + Trưởng kho) + Chủ mở khóa.

- **✅** Tạm ngưng bán món: **cô lập theo RIÊNG từng chi nhánh** (không ảnh hưởng chi nhánh khác) + lý do bắt buộc + nhật ký người khóa; **mở lại phải qua bước DUYỆT** — bếp chỉ *đề nghị mở lại*, Quản lý/Chủ mới *duyệt mở lại*.

- **✅** Sự cố khẩn cấp (tai nạn/ngộ độc/cháy nổ): escalation tự động báo Chủ, biên bản bắt buộc, không được xóa.

- **✅** Thay ca khẩn cấp: quản lý chỉ định người thay (ca gốc → vắng, ca thay liên kết + báo Chủ); **guardrail: quản lý không được tự xếp mình tăng ca**. Có nút "Thay ca khẩn cấp" trong trang Nhân viên.

- **✅** Lập biên bản sai phạm + **kháng cáo** của nhân viên; chấp nhận thì vô hiệu phạt + hoàn khoản cấn trừ lương, mọi thao tác lưu lại cho Chủ.

- **✅** Quỹ lương từng chi nhánh + bậc lương giờ/ca/tháng; quản lý chỉ chọn bậc Chủ quy định (khoá mức), tạo nhân viên qua Chủ duyệt, tổng lương ≤ quỹ chi nhánh.

- **✅** Bàn giao ca: **Chủ tự tạo checklist bàn giao** (loại "Bàn giao ca") và **gán áp dụng cho từng chi nhánh** tuỳ ý; hạng mục có thể yêu cầu ảnh.

---

## Những quyền KHÔNG giao toàn quyền cho manager — **✅ đủ + đặt được con số**

- **✅** Giá gốc, KM/mã toàn chuỗi; xóa đơn/giao dịch/log; tạo/nâng quyền tài khoản quản lý; rút tiền; cấu hình thuế/thanh toán/ngân hàng — đều nằm trong danh sách cấm.
- **✅** **Hoàn tiền/giảm giá vượt hạn mức** & **điều chỉnh tồn kho vượt ngưỡng** — Chủ đặt **con số hạn mức** cho từng thao tác tại trang **Phê duyệt → Chính sách** (`manager_limit_amount`/ngày/tháng); vượt là tự động chuyển Chủ duyệt.
- **✅** Chặn tự phê duyệt yêu cầu do chính mình tạo.

---

## Tổng kết

| Trạng thái | Số mục |
|---|---|
| ✅ Hoàn thành | **15 / 15** |
| 🟡 Còn một phần | **0** |

**Kiểm thử:** Full SQLite **769 pass / 0 fail** · Smoke MySQL **13/13** · Frontend build sạch. Kèm sửa bonus lỗi 500 trang Trung tâm an ninh super-admin (`DB::raw` ép cast int).
