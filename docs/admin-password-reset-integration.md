# Admin cấp lại mật khẩu — nhánh tích hợp vào develop

Nhánh này chuyển chức năng từ `feature/admin-password-reset` sang code `develop` mà **không sao chép AccountModel/CSS cũ**.

## Hành vi dự kiến
- Chỉ admin đã đăng nhập có thể gửi POST (route guard + CSRF).
- Chỉ reset tài khoản có `id_role = 2`, không reset chính admin.
- Form token riêng, hết hạn sau 15 phút; mật khẩu tối thiểu 12 ký tự.
- Bcrypt và transaction; đồng thời tăng `account.session_version` để vô hiệu hóa các phiên đăng nhập cũ.
- Thu hồi các token và job đang chờ của chức năng Client quên mật khẩu; dùng thứ tự khóa account -> jobs -> token tương thích mô hình hiện có.
- Không ghi mật khẩu vào log, phản hồi hoặc session.

## Bắt buộc trước khi merge
1. Chạy `php -l` cho các file PHP thay đổi.
2. Chạy bộ test đăng nhập/đổi mật khẩu/khôi phục mật khẩu Client trên môi trường cách ly.
3. Kiểm thử Admin reset cho Client đang đăng nhập: phiên cũ bị thu hồi, mật khẩu cũ không đăng nhập được, mật khẩu mới đăng nhập được.
4. Kiểm thử CSRF sai, GET thay POST, token hết hạn/đã dùng, reset tài khoản admin, quyền khách hàng, password trùng hash, hai tab, thay đổi role trong quá trình reset.
5. Kiểm thử dùng Mailpit và SQL demo mới, không dùng database thật. Kiểm tra phê duyệt code từ thành viên khác.

**Chưa chạy kiểm thử trên máy local trong thao tác GitHub này.** PR nên để draft cho đến khi hoàn tất.
