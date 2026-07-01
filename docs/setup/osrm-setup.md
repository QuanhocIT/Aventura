# Triển khai OSRM (Open Source Routing Machine) cho tính năng giao hàng

`app/Services/Delivery/OsrmClient.php` thay thế công thức haversine + tốc độ cố định
theo loại xe (13/32/38 km/h) bằng khoảng cách/thời gian di chuyển thật trên mạng lưới
đường bộ. Khi `OSRM_URL` trống hoặc server không phản hồi, hệ thống tự động quay về
công thức haversine cũ — không có gì bị gián đoạn nếu bạn chưa triển khai OSRM.

## 1. Chọn dữ liệu bản đồ (OSM extract)

Tải file `.osm.pbf` cho khu vực nhà hàng hoạt động (khuyến nghị tải theo vùng/tỉnh thay
vì toàn bộ Việt Nam để giảm thời gian xử lý) từ Geofabrik:

```
https://download.geofabrik.de/asia/vietnam.html
```

## 2. Tiền xử lý dữ liệu (một lần, hoặc định kỳ khi refresh bản đồ)

```bash
docker run -t -v "${PWD}/osrm-data:/data" osrm/osrm-backend osrm-extract -p /opt/car.lua /data/vietnam-latest.osm.pbf
docker run -t -v "${PWD}/osrm-data:/data" osrm/osrm-backend osrm-partition /data/vietnam-latest.osrm
docker run -t -v "${PWD}/osrm-data:/data" osrm/osrm-backend osrm-customize /data/vietnam-latest.osrm
```

Bước `osrm-extract` cần nhiều RAM nhất (tuỳ kích thước vùng dữ liệu — cả nước Việt Nam
có thể cần 4-8GB RAM cho bước này). Sau khi xử lý xong, dung lượng vận hành ổn định
(`osrm-routed`) nhỏ hơn nhiều.

## 3. Chạy OSRM server

```yaml
# docker-compose.yml (thêm vào file compose hiện có của dự án nếu cần)
services:
  osrm:
    image: osrm/osrm-backend
    volumes:
      - ./osrm-data:/data
    ports:
      - "5000:5000"
    command: osrm-routed --algorithm mld /data/vietnam-latest.osrm
    restart: unless-stopped
```

## 4. Cấu hình Aventura trỏ tới OSRM

```env
OSRM_URL=http://localhost:5000
```

Không cần thay đổi gì thêm — `OsrmClient::isConfigured()` sẽ tự động phát hiện giá trị
này và `DeliveryDispatchService::recalculateEtas()` sẽ ưu tiên dùng OSRM thay vì
haversine ngay khi server phản hồi thành công.

## 5. Kiểm tra hoạt động

```bash
curl "http://localhost:5000/route/v1/driving/106.7009,10.7769;106.7015,10.7775?overview=false"
```

Nếu trả về JSON có `routes[0].distance`/`routes[0].duration`, server đã sẵn sàng.

## 6. Bảo trì dữ liệu bản đồ

Bản đồ đường bộ thay đổi theo thời gian (đường mới, phân luồng...). Khuyến nghị tải lại
extract và chạy lại bước 2 mỗi 3-6 tháng, tuỳ tốc độ phát triển hạ tầng khu vực hoạt
động.

## Việc chưa làm trong phiên này

Phiên này mới nối OSRM vào bước **tính ETA cho tài xế đang giao hàng**
(`recalculateEtas`), vì đây là nơi có giá trị cao nhất và độ rủi ro thấp nhất khi thay
đổi. Thuật toán **sắp xếp thứ tự điểm dừng** (`RouteOptimizationService::nearestNeighbor`/
`twoOpt`/`orOpt`) vẫn dùng haversine — việc chuyển thuật toán này sang dùng ma trận
khoảng cách thật từ `OsrmClient::distanceMatrix()` là bước tiếp theo hợp lý, nên làm sau
khi đã có server OSRM thật để kiểm thử so sánh kết quả trước/sau.
