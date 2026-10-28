# Chính sách session_version độc lập

Nhánh triển khai: `feature/client-password-recovery`.
Không chạy migration, kết nối MariaDB hoặc xử lý lỗi `mysql.db` trong bản sửa này.

## Hai trạng thái triển khai

`FOURSMART_SESSION_VERSION_MODE` được đọc từ môi trường tiến trình, không dùng
file `.env` và không phụ thuộc HMAC hay `FOURSMART_PASSWORD_RECOVERY_ENABLED`.

| Mode | Schema dự kiến | Hành vi |
| --- | --- | --- |
| `legacy` được khai báo rõ | Trước migration | Đăng nhập/đổi mật khẩu theo Giai đoạn 1; không SELECT/UPDATE cột version chưa tồn tại; guard không mở kết nối DB để kiểm tra version. |
| `required` | Sau migration đầy đủ | Login lưu version; mọi request đã đăng nhập so sánh version; đổi mật khẩu tăng version và thu hồi token/job ngay cả khi recovery OFF. |
| Không khai báo hoặc giá trị khác, kể cả chuỗi rỗng | Cấu hình không hợp lệ | Login/request đã đăng nhập bị từ chối bằng lỗi an toàn 503. Không fallback về legacy. |

Không tự dò schema rồi bỏ kiểm tra khi truy vấn lỗi. Trong `required`, thiếu cột,
version trong DB sai định dạng hoặc lỗi DB trả 503. Không có tài khoản hoặc phiên
thiếu/sai version thì xóa trạng thái đăng nhập: GET chuyển về login, POST trả 403.
Version 0 hợp lệ; bool, float, array, số âm và chuỗi không chuẩn không được ép về 0.

Recovery chỉ chạy khi cờ recovery ON và policy là `required`; `load()` của cấu hình
chặn cả controller và worker khi policy không phù hợp, trước khi worker mở DB.
Tắt recovery không thay đổi policy. Một request đang chạy không bị ngắt hồi tố.

## Triển khai trên XAMPP sau migration được duyệt riêng

1. Trước migration, chỉ dùng `legacy` được khai báo rõ và recovery OFF. Không
   bỏ biến mode: từ đợt rà soát Git 5A, thiếu cấu hình cũng bị từ chối an toàn.
   Local hiện tại đã ở `required`; xem [hướng dẫn vận hành](local-recovery-operations.md).
2. Sau khi xử lý lỗi MariaDB và migration hoàn thành, xác minh cột version, cả
   ba bảng recovery, FK/index/CHECK trước khi chuyển mode. Không chuyển mode khi
   mới áp dụng một phần SQL; đổi mật khẩu trong required cần schema recovery đầy đủ.
3. Trong cấu hình môi trường riêng của Apache và các tiến trình PHP liên quan,
   đặt `FOURSMART_SESSION_VERSION_MODE=required`, giữ recovery OFF. Nạp lại Apache
   theo quy trình triển khai; một biến trong PowerShell không tự truyền vào Apache.
4. Kiểm tra đăng nhập Client/Admin, phân quyền, đổi mật khẩu, và thu hồi phiên ở
   thiết bị khác. Tất cả phiên cũ thiếu version phải đăng nhập lại, kể cả Admin.
5. Chỉ sau phê duyệt bật recovery, cấu hình HMAC ngoài Git/web root, Mailpit và
   worker; đặt cờ recovery ON cho đúng tiến trình. Worker cũng cần mode required.

Ví dụ môi trường **sau migration**, chưa bật email:

```text
FOURSMART_SESSION_VERSION_MODE=required
FOURSMART_PASSWORD_RECOVERY_ENABLED=0
```

Không thực thi worker trên `duan1` trước khi được duyệt. Worker có cleanup ghi DB
kể cả khi hàng đợi rỗng. HMAC không cần cho login/đổi mật khẩu khi email OFF.

Khi rollback email, giữ mode required, schema và các giá trị version đã tăng.
Không đổi mode về legacy hoặc khôi phục version về 0 khi còn phiên đang tin cậy.
Nếu phải rollback schema/code, bảo trì ứng dụng và thu hồi toàn bộ phiên FourSmart
trước khi mở lại; tránh xóa bừa thư mục session dùng chung của XAMPP.

## Kiểm thử cô lập

Không chạy các script model cũ chỉ in dữ liệu từ `Database::connection()` thật.
Các suite dưới đây chỉ tạo SQLite fixture riêng trong bộ nhớ/Temp, session/cookie
riêng và PHP server loopback; không chạy file migration, không mở MySQL, không dùng
credential MariaDB. Fixture được dọn sau chạy. Không dùng `--mariadb` trong đợt này.

```powershell
php mvc/tests/session_version_policy_test.php
php mvc/tests/password_recovery_model_test.php
php mvc/tests/client_password_recovery_test.php
node mvc/tests/client_password_recovery_js_test.js
```

Chạy lại toàn bộ 41 ca Đổi mật khẩu ở ba cấu hình, chỉ trong terminal test:

```powershell
$oldMode = $env:FOURSMART_SESSION_VERSION_MODE
$oldRecovery = $env:FOURSMART_PASSWORD_RECOVERY_ENABLED
try {
    $env:FOURSMART_SESSION_VERSION_MODE = 'legacy'
    $env:FOURSMART_PASSWORD_RECOVERY_ENABLED = '0'
    php mvc/tests/client_password_change_test.php

    $env:FOURSMART_SESSION_VERSION_MODE = 'required'
    php mvc/tests/client_password_change_test.php

    $env:FOURSMART_PASSWORD_RECOVERY_ENABLED = '1'
    php mvc/tests/client_password_change_test.php
} finally {
    $env:FOURSMART_SESSION_VERSION_MODE = $oldMode
    $env:FOURSMART_PASSWORD_RECOVERY_ENABLED = $oldRecovery
}
```

Suite policy kiểm tra luồng HTTP thật: schema trước migration, required/recovery
OFF, đổi mật khẩu, reset ON rồi tắt OFF trước khi phiên cũ gửi GET/POST, bật lại ON,
phiên thiếu/sai version, thiếu schema, lỗi DB và sai cấu hình. Admin login/list,
chặn Client/anonymous và thu hồi phiên Admin cũng được kiểm tra trên fixture.

## Kết quả xác minh 2026-10-21

18 ca hồi quy ban đầu chạy trước khi sửa: 2 PASS, 16 FAIL; lỗi được tái hiện khi
recovery OFF. Sau sửa và bổ sung kiểm tra cấu hình/kiểu version:

| Kiểm tra | Kết quả | Bằng chứng |
| --- | --- | --- |
| Session policy + Client/Admin HTTP | PASS | 21 passed, 0 failed |
| Toàn bộ Đổi mật khẩu: legacy, recovery OFF | PASS | 41 passed, 0 failed |
| Toàn bộ Đổi mật khẩu: required, recovery OFF | PASS | 41 passed, 0 failed |
| Toàn bộ Đổi mật khẩu: required, recovery ON | PASS | 41 passed, 0 failed |
| Recovery model/config/concurrency SQLite | PASS | 21 passed, 0 failed |
| Recovery HTTP, gồm Admin | PASS | 10 passed, 0 failed |
| JavaScript DOM simulation | PASS | 5 passed, 0 failed |
| Cú pháp PHP/JavaScript | PASS | 91 PHP, 3 JS; không lỗi |
| MariaDB, migration, repair mysql.db | NOT TESTED | Không mở connection MariaDB; ngoài phạm vi bản sửa |
| PHPMailer/Mailpit thực tế, Chrome, HTTPS | NOT TESTED | Không gửi email hoặc thao tác trình duyệt trong lượt này |

Các lệnh suite cuối trả exit 0; fixture SQLite và PHP server tạm được dọn sau chạy.
Không xem kết quả MariaDB/Mailpit lịch sử là kết quả của bản sửa này. Không chỉnh
cấu hình Apache/máy, bật recovery trên `duan1`, sửa SQL migration hoặc chạy worker
trên database hiện có. Migration và repair vẫn cần phê duyệt riêng.
