# 🧪 Hướng Dẫn Chạy Kiểm Thử

> Aventura dùng **PHPUnit** (tích hợp sẵn Laravel) cho Feature & Unit tests.

---

## ⚡ Chạy Nhanh

```bash
# Toàn bộ test suite
php artisan test

# Chỉ Feature tests
php artisan test --testsuite=Feature

# Chỉ Unit tests
php artisan test --testsuite=Unit

# Chạy 1 file test cụ thể
php artisan test tests/Feature/FeedbackTest.php

# Chạy theo tên method
php artisan test --filter test_customer_can_submit_feedback
```

---

## 🏃 Chạy Song Song (Nhanh Hơn)

```bash
php artisan test --parallel
```

---

## 📊 Xem Coverage Report

```bash
php artisan test --coverage
php artisan test --coverage --min=80   # Fail nếu dưới 80%
```

---

## 🗃️ Database Test

Aventura dùng **SQLite in-memory** cho test (cấu hình sẵn trong `phpunit.xml`):

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

> Không cần tạo database riêng. Mỗi test tự `migrate` và xóa sau khi chạy.

---

## 📋 Danh Sách Test Files

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   ├── TwoFactorTest.php           ← TC-2FA-28
│   │   └── BruteForceTest.php          ← TC-EMP-22
│   ├── Feedback/
│   │   └── FeedbackSubmitTest.php      ← TC-FDB-19
│   ├── Order/
│   │   ├── QrOrderTest.php             ← TC-QR-01
│   │   ├── OrderLockingTest.php        ← TC-LCK-05
│   │   └── SplitOrderTest.php          ← TC-SPL-06
│   ├── Inventory/
│   │   ├── BomDeductionTest.php        ← TC-BOM-07
│   │   └── ConcurrencyTest.php         ← TC-INV-20
│   └── ...
└── Unit/
    ├── LoyaltyPointServiceTest.php
    ├── NpsCalculatorTest.php
    └── ...
```

---

## 🔗 Xem Thêm

- [Ma trận kiểm thử đầy đủ](test-cases.md)
- [Quay lại mục lục](../README.md)
