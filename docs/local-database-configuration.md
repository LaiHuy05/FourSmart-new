# Cấu hình database local

`Database::connection()` đọc biến môi trường `FOURSMART_DATABASE_CONFIG`, trỏ tới
file JSON nằm ngoài repository và DocumentRoot. Không có kết nối mặc định hoặc
kết nối dự phòng khi file hay endpoint không hợp lệ.

File JSON cần đủ các trường:

- `host`: `127.0.0.1` để dùng TCP loopback.
- `port`: số nguyên chỉ cổng của instance đã kiểm chứng.
- `database`: tên database.
- `user`: tài khoản runtime riêng, không dùng `root`.
- `password`: mật khẩu không rỗng, giữ trong file bí mật ngoài Git.

Chỉ cấp quyền DML cần thiết cho tài khoản runtime. Khóa ACL của file để tài khoản
chạy PHP đọc được; không đặt file trong thư mục có thể truy cập qua HTTP.

Với Apache, đặt `SetEnv FOURSMART_DATABASE_CONFIG "<đường dẫn tuyệt đối>"` trong
khối `Directory` riêng của FourSmart, ngoài repository, rồi kiểm tra cấu hình
Apache và xác minh PDO qua HTTP. Với PHP CLI, truyền biến này cho riêng tiến trình.
Không đưa nội dung file JSON vào terminal, log, báo cáo hoặc commit.

Kết nối luôn khai báo cổng, database và tài khoản cụ thể, đồng thời kiểm tra danh
tính server sau khi kết nối. Thiếu cấu hình hoặc kết nối thất bại sẽ báo lỗi; không
tự thử `localhost`, cổng mặc định hay tài khoản quản trị.

Cấu hình database không thay đổi các cờ recovery và session. Giữ recovery tắt và
giữ nguyên policy hiện tại cho đến khi có phê duyệt triển khai riêng.

Trước khi chuyển endpoint, sao lưu source/cấu hình Apache ra ngoài repository,
kiểm chứng backup dữ liệu, thử PDO riêng rồi kiểm thử website bằng fixture có
marker. Sau khi dọn fixture, so sánh đủ dữ liệu gốc; bộ đếm AUTO_INCREMENT có thể
tăng do kiểm thử và không cần đặt lại.

Khi rollback cấu hình, dừng các luồng ghi và phục hồi đúng các file đã sao lưu.
Rollback cấu hình không tự khởi động hay sửa server cũ. Nếu đã phát sinh dữ liệu
mới ngoài fixture, sao lưu và đối chiếu trước mọi rollback dữ liệu.
