# FourSmart: kết quả kiểm thử MariaDB và hướng dẫn Chrome

Ngày kiểm thử: 2026-10-21. Nhánh: `feature/client-password-recovery`.

Migration chỉ được áp dụng vào **`duan1_test`** theo phê duyệt riêng của người dùng.
**Không áp dụng migration, không sửa cấu trúc hay dữ liệu của `duan1`.**
Không bật tính năng trên Apache đang kết nối `duan1`; không commit/push/merge.

## Database và giới hạn quyền

- Server: MariaDB **10.4.32** trên XAMPP.
- Đọc cấu trúc `account`, `role` và quan hệ FK của 11 bảng nguồn trong transaction
  READ ONLY; không đọc/copy các dòng tài khoản, email hoặc dữ liệu cá nhân thật.
- Sao chép cấu trúc đủ 11 bảng, theo thứ tự phụ thuộc FK; bỏ giá trị bộ đếm
  AUTO_INCREMENT của bảng nguồn. Các bảng danh mục/đơn hàng/giỏ hàng test rỗng.
- Chỉ seed role và tài khoản giả. Bộ test tự dọn các hàng giả do nó tạo.
- Tài khoản chạy migration/test/worker/preview:
  **`foursmart_duan1_test@127.0.0.1`**. Chỉ có quyền trên các bảng được liệt kê
  trong `duan1_test`; không có quyền sửa `duan1`, quyền toàn cục hoặc GRANT OPTION.
- Root chỉ dùng để đọc metadata và provision database/tài khoản/quyền. Sau đó
  được đóng; không dùng để chạy chức năng, migration hoặc kiểm thử ứng dụng.
- `GuardedMariaDB` xác nhận DATABASE() và CURRENT_USER() trước mỗi execute/exec/query
  có thể thay đổi dữ liệu, schema hoặc transaction. Mỗi tiến trình con dùng guard
  riêng. Cấm USE, target production, tạo database/user hoặc cấp quyền trong test.
- File credential/HMAC ở thư mục Temp được khóa Windows ACL cho `<WINDOWS_HOST>\<WINDOWS_USER>`
  và tiến trình `<WINDOWS_HOST>\<TEST_RUNNER_USER>`,
  ngoài Git và ngoài web root. Password database và HMAC thật không nằm trong báo cáo.

Đã phát hiện lỗi môi trường **1034: index bảng quyền `mysql.db` bị hỏng** khi cấp
quyền cấp database. Không chạy REPAIR hoặc sửa bảng quyền hệ thống. Việc cấp quyền
riêng từng bảng qua `mysql.tables_priv` thành công; các phép kiểm tra quyền và toàn
bộ bài test bên dưới đều chạy bằng tài khoản hạn chế. Lỗi `mysql.db` vẫn chưa sửa.

## Migration và cấu trúc đã xác minh

Áp dụng nguyên file `mvc/migrations/20261021_client_password_recovery.proposed.sql`:
**4/4 câu lệnh thành công**, không sửa SQL hoặc mã ứng dụng để làm migration chạy.

SHA-256 trước/sau giữ nguyên:

```text
1796bd7d4a368205870d96a54a11b4824ff628cf7f67d76a223eb8685562725d
```

Đã kiểm tra:

- `account.session_version`: INT UNSIGNED, NOT NULL, DEFAULT 0.
- Ba bảng `password_reset_tokens`, `password_reset_rate_limits`, `password_reset_jobs`.
- Index `(tk_email,id_role)`, các primary key, UNIQUE token_hash và toàn bộ index
  phục vụ expiry, rate window, claim, lease, email và retention.
- `token_hash` là BINARY(32); FK account_id có cùng kiểu INT có dấu với account.tk_id.
- FK token -> account có ON DELETE CASCADE / ON UPDATE RESTRICT.
- Đủ **8 CHECK constraints**, được bật. Có kiểm thử dữ liệu vi phạm và xác nhận
  MariaDB thực sự từ chối, không chỉ kiểm tra sự tồn tại trong metadata.
- Tổng 15 bảng: 11 bảng cấu trúc nguồn + 3 bảng recovery + marker test.
- Trigger tiêm lỗi rollback đã được xóa sau test; không để lại trigger test.

Đọc lại SHOW CREATE TABLE của cả 11 bảng `duan1` sau kiểm thử: khớp hoàn toàn
snapshot trước đó. `duan1` vẫn có 0 bảng recovery và 0 cột session_version.
GET trang login Apache trả HTTP 200, không có liên kết Quên mật khẩu.

## Kết quả

| Phạm vi | Trạng thái | Bằng chứng |
| --- | --- | --- |
| Migration trên duan1_test | PASS | 4/4 statements; hash không đổi |
| MariaDB model/service/schema/quyền | PASS | 15 passed, 0 failed; exit 0 |
| HTTP + session + CSRF + Admin + Mailpit trên MariaDB | PASS | 11 passed, 0 failed; exit 0 |
| Server preview + worker CLI entrypoint thật + Mailpit + reset/login/profile/home | PASS | Hoàn tất luồng qua HTTP |
| Toàn bộ test Đổi mật khẩu, feature OFF | PASS | 41 passed, 0 failed, SQLite cô lập |
| Toàn bộ test Đổi mật khẩu, feature ON | PASS | 41 passed, 0 failed, SQLite cô lập |
| SQLite model/config/service | PASS | 21 passed, 0 failed |
| SQLite HTTP regression | PASS | 10 passed, 0 failed; SMTP được kiểm riêng trên MariaDB |
| JavaScript DOM simulation | PASS | 5 passed, 0 failed |
| Cú pháp | PASS | 88 PHP + 3 JavaScript files |
| Lỗi chức năng còn mở trong các suite đã chạy | FAIL = 0 | Tất cả lệnh kiểm thử cuối trả exit 0 |
| Chrome GUI/hiển thị trực quan và HTTPS | NOT TESTED | Chưa thao tác Chrome thật; đã kiểm luồng HTTP |
| Migration trên duan1 | NOT TESTED / không thực hiện | Ngoài phạm vi phê duyệt |

Các ca MariaDB bao gồm hết hạn đúng 900 giây, replay, password reuse, commit đồng
thời password/version/used_at/job, rollback thực sự sau khi UPDATE account đã chạy
nhưng trigger từ chối UPDATE token, thu hồi token sau Đổi mật khẩu và FK cascade.

Ba phép thử đồng thời dùng hai tiến trình/kết nối khác nhau, đợi cùng barrier rồi
bắt đầu; mỗi ca chỉ một bên thắng. Connection IDs trong lượt cuối:

```text
request: 234, 235
worker:  236, 237
reset:   238, 239
```

Tiến trình MariaDB test chính thực hiện **198 lượt guard** trước lệnh thay đổi/
transaction; tiến trình con cũng dùng cùng guard. Trigger tiêm lỗi trả SQLSTATE
45000 / error 1644; sau rollback password/version vẫn nguyên và token còn dùng được.

Mailpit đã nhận và đối chiếu đúng token hash lưu trong MariaDB:

```text
MariaDB service/worker: 7cFrLUkzzCCIECB7vCotCQ
MariaDB HTTP suite:    2Xj2SnGFYr9SxlfJIjOpB9
Preview CLI E2E:       4qsq2ZTcmqi0O8zyXunaz6
```

Đây là thư bằng chứng của các lượt test đã dọn/đặt lại fixture. Khi thử bằng Chrome,
hãy dùng thư mới gửi cho `customer_test@example.test`, không dùng các liên kết cũ.

## Chạy thử đầy đủ bằng Chrome và Mailpit

File cấu hình riêng đã được chuẩn bị tại (đường dẫn mẫu):

```text
C:\Users\<WINDOWS_USER>\AppData\Local\Temp\foursmart-mariadb-test-<16_HEX>\connection.json
```

`<WINDOWS_USER>` và `<16_HEX>` là placeholder. Thay chúng bằng tên thư mục người dùng
và hậu tố 16 ký tự hex của thư mục cấu hình riêng đã chuẩn bị trên máy bạn trước
khi chạy các lệnh bên dưới.

Giữ file này khi còn sử dụng database test. Không chép vào repository/web root.
Server kiểm thử đã dừng sau kiểm tra tự động. Không cần chỉnh `Database.php`, cấu
hình Apache hay biến môi trường toàn hệ thống.

**Terminal PowerShell 1**: mở server test, giữ cửa sổ này chạy:

```powershell
Set-Location C:\xampp\htdocs\FourSmart
$testConfig = 'C:\Users\<WINDOWS_USER>\AppData\Local\Temp\foursmart-mariadb-test-<16_HEX>\connection.json'
php mvc/tests/password_recovery_mariadb_preview.php --reset-demo --private-config="$testConfig"
```

`--reset-demo` chỉ đặt lại tài khoản giả ID 9000002, các job demo và rate counters
trong `duan1_test`. Không chạy migration. Có thể bỏ cờ này để giữ mật khẩu test mới.
Server bind 127.0.0.1:8091 nhưng URL ứng dụng dùng **localhost**; cookie riêng là
`FOURSMART_TESTSESSID`, độc lập với PHPSESSID của Apache trên cổng 80.

1. Chrome: mở `http://localhost:8091/index.php?client=login`.
   Tài khoản giả: **customer_test**, mật khẩu ban đầu **Original-Password!2026**.
   Có thể đăng nhập thêm bằng Incognito để quan sát thu hồi phiên.
2. Chọn Quên mật khẩu hoặc mở
   `http://localhost:8091/index.php?client=forgotPassword`.
   Nhập **customer_test@example.test**, gửi yêu cầu; màn hình trả thông báo chung.
3. **Terminal PowerShell 2**: xử lý hàng đợi bằng worker thật, kết nối guard:

   ```powershell
   Set-Location C:\xampp\htdocs\FourSmart
   $testConfig = 'C:\Users\<WINDOWS_USER>\AppData\Local\Temp\foursmart-mariadb-test-<16_HEX>\connection.json'
   php mvc/tests/password_recovery_mariadb_preview.php --worker --private-config="$testConfig"
   ```

   Kết quả mong đợi: `sent`, sau đó `idle`. SMTP cố định 127.0.0.1:1025, không
   xác thực/TLS, không có SMTP Internet hoặc transport dự phòng.
4. Mở `http://127.0.0.1:8025`, chọn thư mới nhất tới **customer_test@example.test**,
   tiêu đề **FourSmart - Đặt lại mật khẩu**. Mở liên kết localhost:8091 trong thư.
   Giữ JavaScript bật; token fragment được chuyển vào POST rồi xóa khỏi URL.
5. Đặt mật khẩu mới, ví dụ **New-Client!2026**, nhập xác nhận giống nhau. Hoàn tất
   sẽ chuyển về login, không tự đăng nhập.
6. Mật khẩu cũ phải bị từ chối; mật khẩu mới phải đăng nhập được. Phiên cũ trên
   cửa sổ/Incognito khác sẽ bị chuyển về login ở request được bảo vệ tiếp theo.
7. Mở lại cùng email và gửi form reset lần nữa: liên kết đã dùng phải bị từ chối.
   GET không tiêu thụ/không xác thực token fragment, nên lỗi được xác nhận khi POST.
   Thư sau 15 phút cũng bị từ chối. Muốn gửi lại, chờ tối thiểu 60 giây và chạy lại
   worker; tối đa 3 yêu cầu/email/15 phút và 20 yêu cầu/IP/15 phút.
8. Nhấn Enter trong Terminal 1 để dừng server. Database test và cấu hình riêng
   được giữ lại để bạn kiểm tra; không có server nền hoặc scheduled task được cài.

## Chạy lại các bài test tự động

Dừng server demo trước khi chạy suite vì suite dọn/reset dữ liệu giả. Cùng thư mục
và biến `$testConfig` ở trên:

```powershell
php mvc/tests/password_recovery_mysql_test.php --run-approved --mailpit --private-config="$testConfig"
php mvc/tests/client_password_recovery_test.php --mariadb --mailpit --private-config="$testConfig"
php mvc/tests/password_recovery_model_test.php
php mvc/tests/client_password_change_test.php
```

Suite Đổi mật khẩu vẫn dùng SQLite riêng. Để chạy cùng versioning bật, đặt
`FOURSMART_PASSWORD_RECOVERY_ENABLED=1` chỉ trong tiến trình/terminal test rồi khôi
phục giá trị cũ như hướng dẫn trong tài liệu triển khai; không cấu hình trên Apache.
Sau suite MariaDB, dùng lệnh preview với `--reset-demo` để tạo lại tài khoản demo.

`setup_duan1_test.php` là provisioner một lần, từ chối database có bảng. Không chạy
lại file này với database test đã tồn tại và không áp dụng migration lại.

## File bổ sung/cập nhật trong đợt kiểm thử MariaDB

Tạo mới:

```text
mvc/tests/setup_duan1_test.php
mvc/tests/support/guarded_mariadb.php
mvc/tests/support/password_recovery_mariadb_router.php
mvc/tests/password_recovery_mariadb_preview.php
mvc/tests/password_recovery_mariadb_preview_test.php
docs/client-password-recovery-mariadb-verification.md
```

Cập nhật harness và tài liệu:

```text
mvc/tests/password_recovery_mysql_test.php
mvc/tests/client_password_recovery_test.php
docs/client-password-recovery.md
```

Không chỉnh mã Controller/Model/Service ứng dụng, cấu hình database thật, file SQL
migration, `.gitignore`, Composer hoặc giao diện Admin trong đợt kiểm thử này.
