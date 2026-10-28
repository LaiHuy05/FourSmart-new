# Giai đoạn 2: Mailpit + PHPMailer local

Ngày kiểm chứng: 24/10/2026 (Asia/Bangkok).
Nhánh: `feature/client-password-recovery`. Kết quả: **PASS**.

Cập nhật sau giai đoạn này: [worker và E2E thực giai đoạn 3](local-password-recovery-e2e.md)
đã PASS; dữ liệu fixture đã dọn và Recovery trở lại OFF.

Recovery vẫn `0`, session policy vẫn `required`. Không chạy CLI worker trên
`duan1`, không tạo token khôi phục thật, không chạy migration hoặc sửa instance
database. Không commit/push/merge. Test recovery tự động dùng fixture SQLite
tạm và token giả lập của fixture; không kết nối `duan1`, không gửi token vào Mailpit.

## Kiểm tra môi trường

| Thành phần | Kết quả |
| --- | --- |
| PHP CLI | `C:\xampp\php\php.exe`, 8.2.12 ZTS x64 |
| PHP Apache | 8.2.12, SAPI `apache2handler` |
| php.ini của cả hai SAPI | `C:\xampp\php\php.ini` |
| Extension | ctype, filter, hash, mbstring, openssl, curl, pdo_mysql, pdo_sqlite, zip có sẵn |
| Composer | 2.10.3, `C:\xampp\php\composer.phar` |
| PHPMailer | 7.1.1, đã có trong `vendor`, khớp `composer.lock` |
| Composer autoload | `vendor/autoload.php`, hoạt động dưới CLI và Apache |
| Platform requirements | PASS |
| composer.json / lock | Không sửa, không install/update dependency |
| Website database qua Apache | `127.0.0.1:33367/duan1`, `foursmart_app@127.0.0.1` |

`composer validate --no-check-publish` xác nhận cấu hình hợp lệ, có hai cảnh báo
cũ: thiếu license và constraint PHPMailer `*`. Không đổi dependency trong giai đoạn này.
Wrapper `composer.bat` dựa vào `php` trên PATH; có thể gọi Composer chắc chắn bằng
`C:\xampp\php\php.exe C:\xampp\php\composer.phar`.

`SMTP=localhost`, `smtp_port=25` trong php.ini là cấu hình cho PHP `mail()`.
FourSmart dùng PHPMailer `isSMTP()` và không sử dụng hoặc sửa những thiết lập toàn cục đó.

## Mailpit

Dùng lại `C:\tools\mailpit\mailpit.exe`, v1.31.6, Windows/amd64.
Đã tải ZIP từ [release chính thức v1.31.6](https://github.com/axllent/mailpit/releases/tag/v1.31.6)
vào thư mục tạm, kiểm tra digest archive do GitHub công bố, sau đó đối chiếu
binary trong ZIP với file hiện có. Không cài bản thứ hai hoặc thay binary.

```text
Archive SHA-256:
5d5ad094bfb3bac9004f9d1105d2335fea9f8952c09951df73ae0a280e34d3b1
Installed mailpit.exe SHA-256:
1de8b20a01f40d8d3222f126e35aca75a8e6267f279743d15c1526b6c91d096e
```

| Thiết lập | Giá trị |
| --- | --- |
| SMTP | `127.0.0.1:1025` |
| Web UI / API | `http://127.0.0.1:8025` |
| Runtime riêng ngoài DocumentRoot | `<mailpit_runtime>` trong cấu hình tools local (ignored) |
| Inbox bền vững | `mailpit.sqlite` trong thư mục runtime |
| Log | `mailpit.log`, chỉ lỗi, không bật verbose |
| State quản lý tiến trình | `process.json`, kiểm tra PID, path và thời điểm start |

Script [tools/local-mailpit.ps1](../tools/local-mailpit.ps1) xóa biến `MP_*`
khỏi môi trường của tiến trình con trước khi start, rồi phục hồi môi trường shell.
Không truyền relay, forwarding, webhook, POP3, auth hoặc TLS. Tắt version check,
reverse DNS và remote CSS/font. Chỉ hai listener loopback được phép.
Các tùy chọn bind và relay được đối chiếu với
[tài liệu runtime chính thức](https://mailpit.axllent.org/docs/configuration/runtime-options/).

Từ PowerShell:

Điền đường dẫn riêng trong `tools/local-tools.local.json` theo
[hướng dẫn vận hành hiện tại](local-recovery-operations.md). Mẫu
`tools/local-tools.example.json` không có đường dẫn đặc thù của máy.

```powershell
& 'C:\xampp\htdocs\FourSmart\tools\local-mailpit.ps1' start
& 'C:\xampp\htdocs\FourSmart\tools\local-mailpit.ps1' status
& 'C:\xampp\htdocs\FourSmart\tools\local-mailpit.ps1' stop
```

Script mở tiến trình ẩn cửa sổ, không đăng ký service hoặc Task Scheduler.
Nếu cổng đang bị chiếm, script dừng với lỗi; không kill tiến trình khác.
Stop giữ inbox, start kiểm tra `/readyz`. Đã kiểm thử stop/start và xác minh cả hai
email thử vẫn còn. Mailpit đang được để chạy để có thể xem Web UI.

## Tích hợp PHPMailer

- [mvc/service/PasswordRecoveryMailer.php](../mvc/service/PasswordRecoveryMailer.php):
  giữ lớp gửi mail hiện có. `send()` tạo HTML và plain text; `sendTestMessage()`
  gửi nội dung cố định không token; cả hai dùng chung `deliver()` và PHPMailer.
- [mvc/config/password_recovery_mail.php](../mvc/config/password_recovery_mail.php):
  đọc cấu hình môi trường, chỉ chấp nhận transport `mailpit`, host `127.0.0.1`,
  sender/Reply-To `.test`, port hợp lệ và timeout 1–10 giây. Reject header injection.
- `mvc/.htaccess`: cấu hình Apache local không chứa secret. Không có `.env` loader.
- [mvc/tests/mailpit_smtp_test.php](../mvc/tests/mailpit_smtp_test.php):
  smoke test gọi lớp gửi mail thật, không bootstrap ứng dụng hoặc kết nối database.

Cấu hình hiện tại, áp dụng riêng cho môi trường local:

```text
FOURSMART_MAIL_TRANSPORT=mailpit
FOURSMART_SMTP_HOST=127.0.0.1
FOURSMART_SMTP_PORT=1025
FOURSMART_SMTP_TIMEOUT=5
FOURSMART_MAIL_FROM=noreply@foursmart.test
FOURSMART_MAIL_FROM_NAME=FourSmart
FOURSMART_MAIL_REPLY_TO=support@foursmart.test
FOURSMART_PASSWORD_RECOVERY_ENABLED=0
FOURSMART_SESSION_VERSION_MODE=required
```

CLI đọc biến môi trường riêng của tiến trình, với cùng giá trị mặc định cho mail.
CLI không kế thừa `SetEnv` của Apache. Không hỗ trợ SMTP Internet, mật khẩu SMTP
hoặc khóa SMTP trong profile này. `SMTPAuth=false`, `SMTPSecure=''`,
`SMTPAutoTLS=false`, `SMTPDebug=0`; không có fallback PHP `mail()`.

Lỗi gửi chỉ đưa ra `Local email delivery failed.`, không nối exception gốc.
Tham số email/token/body được đánh dấu `SensitiveParameter`. Worker service
hiện có vẫn dùng mã lỗi allowlist `SMTP_FAILED`; không sửa worker hoặc cơ chế queue.

## Kết quả kiểm thử

Đã gửi hai email không token tới `developer@foursmart.test`: một từ CLI và một
từ PHP Apache. Subject `FourSmart - Kiểm thử email tiếng Việt`.
From `FourSmart <noreply@foursmart.test>`, Reply-To
`FourSmart <support@foursmart.test>`. MIME là `multipart/alternative`, có
`text/plain` và `text/html`, cả hai charset UTF-8. Nội dung `Nguyễn Văn An,
ă â ê ô ơ ư đ` được đối chiếu chính xác sau giải mã MIME và hiển thị bằng Chrome thật.

| Bộ test đã chạy | Kết quả |
| --- | --- |
| `password_recovery_mailer_test.php` | 13/13 PASS |
| `password_recovery_model_test.php`, SQLite cô lập | 21/21 PASS |
| `database_config_test.php`, cấu hình và endpoint giả lập | 12/12 PASS |
| `client_password_recovery_js_test.js`, DOM simulation | 5/5 PASS |
| `session_version_policy_test.php`, SQLite/HTTP cô lập | 21/21 PASS |
| `client_password_change_test.php`, SQLite/HTTP cô lập | 41/41 PASS |
| `client_password_recovery_test.php`, không `--mailpit`/`--mariadb` | 10/10 PASS |
| Tổng test tự động liên quan | **123/123 PASS** |
| Runtime Apache / MIME / Chrome / database read-only | 25 kiểm tra PASS, probe đã xóa |
| PHP lint các file PHP thay đổi/thêm của giai đoạn 2 | 5/5 PASS |
| Apache config `httpd.exe -t` | Syntax OK |
| Mailpit stop/start/readiness/listener/inbox | PASS |

Bộ test mailer kiểm tra SMTP không lắng nghe, lỗi từ chối DATA và timeout bằng
peer SMTP loopback tạm. Mọi lỗi đều có thông báo chung, không chain exception,
không có debug output hoặc nội dung nhạy cảm trong PHP log. Peer còn quảng bá
STARTTLS/AUTH nhưng mailer vẫn dùng chế độ không TLS/không auth.

Website thật: Client home và login trả 200, Admin chưa đăng nhập chuyển về login,
hai route recovery trả 503 vì tính năng OFF. Test đăng nhập và quyền Client/Admin
được chạy với fixture SQLite; không đăng nhập/đổi mật khẩu tài khoản thật.
Probe Apache dùng transaction read-only và so sánh số dòng, digest dữ liệu và schema
của toàn bộ bảng `duan1` trước/sau; tất cả không đổi. Apache và MariaDB giữ nguyên
tiến trình, không khởi động lại hoặc sửa cấu hình database.

Bằng chứng ngoài web root:
`<mailpit_runtime>\evidence-phase2\` chứa `apache-baseline.json`,
`apache-final.json`, `verification.json`, `message.eml`, `message.json`,
`inbox.png`, `message.png` và DOM Chrome. Không chứa token reset hoặc DB credentials.
Các probe PHP tạm đã được xóa.

## Giai đoạn tiếp theo — chưa thực hiện

Chỉ tiếp tục sau phê duyệt riêng chạy worker và kiểm thử end-to-end:

1. Chốt tài khoản Client thử, phạm vi ghi/xóa fixture và kế hoạch dọn dữ liệu trên
   database được duyệt; xác minh schema và quyền sẵn có, không tự chạy migration.
2. Chuẩn bị HMAC key ổn định ngoài Git/DocumentRoot, cùng một key cho Apache và
   CLI; cấu hình `FOURSMART_APP_URL` localhost, database config ngoài web root và
   biến SMTP local. Không đưa secret vào dòng lệnh, transcript hoặc log.
3. Giữ session `required`; chỉ bật recovery trong cửa sổ kiểm thử đã duyệt,
   kiểm chứng cấu hình của cả Apache và CLI trước khi chạy worker giới hạn.
4. Kiểm thử request → queue → worker → Mailpit → reset → login; kiểm tra sai token,
   hết hạn, replay, unknown/Admin email, rate limit, CSRF, session trên nhiều thiết
   bị và retry SMTP. Chỉ đọc reset URL trong Mailpit, không ghi vào báo cáo/log.
5. Dọn fixture, đối chiếu dữ liệu và trả recovery về trạng thái theo kế hoạch
   được duyệt. Không tự tạo lịch worker hoặc cấu hình SMTP thật.

**Dừng tại đây chờ phê duyệt giai đoạn tiếp theo.**
