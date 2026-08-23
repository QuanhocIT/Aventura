# Data lifecycle runbook

Tài liệu này mô tả cơ chế quản lý dung lượng được triển khai cho Aventura. Phạm vi bao gồm retention, archive, cleanup, backup và capacity monitoring. Không bao gồm database-per-tenant, sharding hoặc tách tenant lớn sang database riêng.

## Chính sách mặc định

- Đơn hàng completed/cancelled quá 3 tháng: chuyển khỏi bảng nóng sang archive.
- Order archive: giữ 36 tháng; purge partition phải được phê duyệt.
- Audit log: archive mặc định sau 6 tháng; retention có thể thay đổi tại màn hình Audit Log.
- Search log/notification: 6 tháng.
- Customer behavior: 12 tháng.
- Webhook delivery: 3 tháng.
- Commission, loyalty, cash và inventory transaction: không tự động xóa.
- File mồ côi: chỉ đủ điều kiện sau 30 ngày và tenant có legal hold sẽ bị bỏ qua.
- Backup: giữ daily 7 ngày, weekly 8 tuần và monthly 12 tháng.

Các giá trị được cấu hình tại `config/data_lifecycle.php` và có thể override bằng `.env`.

## Quy trình cleanup an toàn

1. Mở `/super-admin/data-lifecycle`.
2. Tạo dry-run cho action cần thực hiện.
3. Kiểm tra số lượng bản ghi, partition, dung lượng giải phóng và tenant legal hold.
4. Phê duyệt cleanup run.
5. Theo dõi trạng thái trong bảng Cleanup runs và log vận hành.

CLI mặc định chỉ chạy dry-run:

```bash
php artisan data:cleanup --action=all
php artisan orders:purge --months=36
```

Các lệnh tự động an toàn được scheduler gọi:

```bash
php artisan data:storage-snapshot
php artisan data:health-check
php artisan data:cleanup --action=technical --confirm --automatic
php artisan data:cleanup --action=audit --confirm --automatic
php artisan data:cleanup --action=backups --confirm --automatic
php artisan data:cleanup --action=snapshots --confirm --automatic
```

Purge partition thật không chạy tự động. Trong production, `orders:purge --confirm` yêu cầu cleanup run đã được phê duyệt hoặc cấu hình đặc biệt `DATA_LIFECYCLE_ALLOW_DIRECT_CLI_PURGE=true`. Không bật cấu hình đặc biệt này trong vận hành thông thường.

## Legal hold

Khi tenant cần giữ nguyên dữ liệu:

```bash
php artisan data:legal-hold 123 --reason="Đang kiểm tra dữ liệu tài chính"
php artisan data:legal-hold 123 --release
```

Hoặc dùng API quản trị tại màn hình Data Lifecycle. Legal hold chặn archive đơn hàng và dọn file mồ côi. Vì partition là cấu trúc dùng chung cho nhiều tenant, nếu bất kỳ tenant nào có legal hold thì order archive purge bị chặn toàn bộ.

## Object storage cho backup/archive

Full tenant-media migration to object storage is intentionally deferred with
the excluded Phase 4. This phase only supports object storage for backups and
archives; `MEDIA_DISK` remains local.

Production nên cấu hình:

```env
BACKUP_DISK=s3
ARCHIVE_DISK=s3
MEDIA_DISK=local
```

Ngoài cấu hình ứng dụng, cần đặt lifecycle policy ở S3/R2/MinIO:

- `backups/`: áp dụng retention daily/weekly/monthly tương ứng.
- `archives/audit-logs/`: giữ theo chính sách audit và legal hold.
- file đã đánh dấu xóa/quarantine: xóa sau 30 ngày.
- thư mục tạm: xóa sau 24 giờ.

Không xóa bản backup cuối cùng trước khi kiểm tra restore thành công.

## Kiểm tra sau deploy

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan config:cache
docker compose exec app php artisan schedule:list
docker compose exec app php artisan data:storage-snapshot
docker compose exec app php artisan data:health-check
docker compose exec app supervisorctl status
```

Supervisord phải hiển thị `scheduler RUNNING`. Nếu heartbeat quá 10 phút, hệ thống tạo system alert `platform.data_lifecycle_health`.

## Rollback vận hành

- Tạm dừng cleanup bằng `DATA_LIFECYCLE_ENABLED=false` rồi clear/cache lại config.
- Không rollback migration đã có dữ liệu bằng `migrate:rollback` trên production.
- Không chạy `DROP PARTITION` thủ công nếu chưa có backup và xác nhận legal hold.
- Restore archive/backup trên môi trường tạm để kiểm tra trước khi đưa dữ liệu về production.
