# SessionVersionPolicy trên Apache local

Website FourSmart local dùng `required` với MariaDB `127.0.0.1:33367/duan1`
và tài khoản ứng dụng `foursmart_app`. Recovery vẫn tắt.

Cập nhật 24/10/2026: [giai đoạn 4](local-recovery-operations.md) đã bật Recovery
riêng cho loopback. Session policy tiếp tục `required`; nội dung bên dưới ghi
nhận lần triển khai session ban đầu khi recovery còn tắt.

Cờ runtime nằm trong `mvc/.htaccess` được Git bỏ qua. Mẫu chia sẻ là
`mvc/.htaccess.example` (Recovery OFF, session required). Không sao chép mẫu
đè lên cấu hình local đang chạy. Thiếu hoặc sai mode sẽ bị từ chối an toàn,
không tự chọn legacy. Đoạn sau ghi nhận cấu hình ở giai đoạn session ban đầu:

```apache
SetEnv FOURSMART_SESSION_VERSION_MODE required
SetEnv FOURSMART_PASSWORD_RECOVERY_ENABLED 0
```

Apache hiện tải `mod_env` và cho phép `AllowOverride All` tại DocumentRoot.
Các directive được đọc cho từng request, nên lần triển khai này không cần khởi
động lại Apache. Không đặt mật khẩu hoặc đường dẫn chứa thông tin đăng nhập trong
file này. Cấu hình database riêng vẫn nằm ngoài repository và DocumentRoot.

PHP chạy dưới `apache2handler`. `SessionVersionPolicy` gọi
`getenv('FOURSMART_SESSION_VERSION_MODE')` và nhận `required` từ môi trường request
của Apache; `$_SERVER` cũng nhận giá trị này. `getenv(..., true)` chỉ đọc môi
trường tiến trình và trả `false` trên cấu hình hiện tại. Không dùng kết quả của
PHP CLI để suy ra chế độ của website. Với CLI test riêng, truyền biến cho đúng
tiến trình test và không chạy worker trong giai đoạn này.

Phiên mới lưu `account.session_version` sau khi đăng nhập. Phiên cũ thiếu version
hoặc có version khác database phải đăng nhập lại: GET chuyển về trang đăng nhập,
POST bị từ chối trước khi xử lý tác vụ. Cơ chế áp dụng cho cả Client và Admin.
Đổi mật khẩu thành công tăng version trong database và cập nhật phiên hiện tại;
các phiên khác giữ version cũ sẽ hết hiệu lực, kể cả khi recovery đang tắt.

Không tự hạ về `legacy` khi đăng nhập hoặc kiểm tra phiên gặp lỗi. Nếu gặp 503
ở luồng đăng nhập/bảo vệ phiên, kiểm tra endpoint, schema, quyền DML và cấu hình
SAPI; giữ trạng thái an toàn và xác định nguyên nhân trước khi thay đổi.
Hai trang recovery trả 503 khi tính năng tắt là kết quả dự kiến.

Bằng chứng triển khai ngày 23/10/2026 được lưu ngoài repository:
`<BACKUP_ROOT>\session-policy-required-<deployment-id>\report.md`.
Thư mục này chứa bản sao cấu hình trước thay đổi, backup SQL, kết quả kiểm tra
qua Apache và đối chiếu hash dữ liệu. Probe HTTP và dữ liệu thử có marker được
dọn sau kiểm thử. Mailpit, PHPMailer và worker cần được phê duyệt ở giai đoạn sau.
