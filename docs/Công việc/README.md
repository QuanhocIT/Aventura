# Quy trình Công việc theo Tác nhân (Actor Workflows)

Tài liệu này định nghĩa cấu trúc thư mục công việc và nhiệm vụ chi tiết của từng tác nhân (actor) trong hệ thống quản lý nhà hàng **Aventura**.

## 📂 Cấu trúc thư mục

```
docs/Công việc/
├── README.md (Tài liệu tổng quan)
├── Super Admin/
│   └── README.md (Công việc & Nhiệm vụ của Super Admin)
├── Chủ nhà hàng/
│   └── README.md (Công việc & Nhiệm vụ của Chủ nhà hàng)
├── Quản lý/
│   └── README.md (Công việc & Nhiệm vụ của Quản lý)
├── Nhân viên/
│   ├── README.md (Tổng quan nhóm nhân sự)
│   ├── Thu ngân/
│   │   └── README.md (Công việc của Thu ngân & Phục vụ)
│   ├── Bếp/
│   │   └── README.md (Công việc của bộ phận Bếp)
│   └── Kho/
│       └── README.md (Công việc của bộ phận Kho)
├── Nhà phân phối/
│   └── README.md (Tương tác với Nhà phân phối)
└── Khách hàng/
    └── README.md (Trải nghiệm & Quyền hạn của Khách hàng)
```

---

## 👥 Tóm tắt vai trò của các tác nhân

1. **Super Admin**: Quản trị viên toàn bộ nền tảng SaaS, quản lý gói dịch vụ và các tenant (nhà hàng).
2. **Chủ nhà hàng (Owner)**: Quản trị cao nhất của một tenant cụ thể, quản lý doanh thu, kho và nhân sự tổng thể.
3. **Quản lý (Manager)**: Điều hành hoạt động hàng ngày, xử lý nhân sự cấp trung, chấm công, duyệt lịch làm việc và xử lý sự cố.
4. **Nhân viên (Staff)**:
   - **Thu ngân / Phục vụ**: Order món, hỗ trợ thanh toán, quản lý bàn.
   - **Bếp (Kitchen)**: Tiếp nhận order thực tế, chế biến và điều phối món ăn theo trạng thái realtime.
   - **Kho (Warehouse)**: Nhập xuất kho nguyên vật liệu, báo cáo hao hụt.
5. **Nhà phân phối**: Cung ứng nguyên liệu và quản lý đơn đặt hàng từ phía nhà hàng.
6. **Khách hàng (Customer)**: Quét mã QR tại bàn, xem menu trực tuyến và gọi món tự động.
