# 1. Dừng và xóa các container đang chạy
docker compose down

# 2. Khởi động lại toàn bộ hệ thống chạy ngầm
docker compose up -d

# 3. Xem log chạy thực tế của backend để kiểm tra xem hệ thống đã boot xong chưa
docker compose logs -f backend