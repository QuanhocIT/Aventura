#!/usr/bin/env bash
#
# Aventura — deploy/cập nhật 1 lệnh trên VPS (Linux + Docker Compose).
# Dùng: từ thư mục dự án trên VPS đã có file .env production:
#   bash docker/deploy.sh
#
# Lần đầu: đảm bảo đã `cp .env.production.example .env` và điền giá trị thật.
set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -f .env ]; then
  echo "✗ Chưa có file .env. Chạy: cp .env.production.example .env  rồi điền giá trị thật."
  exit 1
fi

echo "→ [1/5] Build & khởi động container (app + mysql + redis + meilisearch)..."
docker compose up -d --build

echo "→ [2/5] Chờ MySQL sẵn sàng..."
until docker compose exec -T mysql mysqladmin ping -h localhost --silent 2>/dev/null; do
  sleep 2
done

echo "→ [3/5] Chạy migration..."
docker compose exec -T app php artisan migrate --force

echo "→ [4/5] Seed dữ liệu hệ thống tối thiểu + tối ưu cache..."
docker compose exec -T app php artisan db:seed --class=ProductionSeeder --force
docker compose exec -T app php artisan optimize

echo "→ [5/5] Kiểm tra sẵn sàng production..."
docker compose exec -T app php artisan launch:check

echo ""
echo "✅ Deploy xong. Kiểm tra sức khỏe: curl -f https://<domain>/api/health"
echo "   Tạo nhà hàng mẫu để demo:  docker compose exec app php artisan demo:showcase"
echo "   Nhập thực đơn từ CSV:       docker compose exec app php artisan menu:import file.csv --email=..."
