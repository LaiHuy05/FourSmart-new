# Vận hành Quên mật khẩu Client trên Windows local

Giai đoạn 4 được bật ngày **24/10/2026**, nhánh `feature/client-password-recovery`.
Trạng thái cuối: **Recovery ON**, **SessionVersionPolicy required**, HTTP recovery
chỉ cho `127.0.0.1`/`::1`. Một supervisor thủ công đang chạy; không cài Windows
Service, Task Scheduler hoặc cơ chế tự chạy cùng Windows.

## Kết quả triển khai

| Kiểm tra | Kết quả |
| --- | --- |
| Gate trước bật, gồm 10 test manager | 55/55 PASS |
| Regression cuối, gồm 10 test manager | **144/144 PASS** |
| Activation/E2E bằng Chrome, Apache, MariaDB và worker thật | **36/36 PASS** |
| Kiểm tra vận hành cuối | **22/22 PASS** |

Gate là tập con của regression; không cộng hai lần. Retry, backoff 10/20 giây,
lease 120 giây, timeout SMTP, tối đa 3 lần gửi và race/job quá hạn được kiểm thử
trong fixture cô lập. E2E thực dùng supervisor gọi worker PHP hiện có `--once`.

Đã kiểm tra START trùng giữ cùng PID, `once` không chen vào supervisor đang chạy,
STOP chờ PHP kết thúc và START lại sạch. Login có liên kết Quên mật khẩu; request
từ nguồn ngoài allowlist bị 503 dù giả `X-Forwarded-For: 127.0.0.1`.

Client fixture được gửi đúng một email vào Mailpit, reset qua link trong trình
duyệt thành công. Mật khẩu cũ không đăng nhập được, mật khẩu mới đăng nhập được,
token dùng lại bị từ chối và cả phiên GET/POST cũ bị thu hồi. Worker thật loại
hai job Admin fixture/email không tồn tại mà không tạo token hoặc gửi email
cho chúng. Admin fixture vẫn đăng nhập và dùng trang quản lý tài khoản;
Client vẫn bị cấm vào Admin.

Đã dọn tài khoản/job/token/rate key theo marker và ID cụ thể, xóa đúng email
recovery thử, giữ hai email smoke test cũ. Queue và ba bảng recovery trở lại
rỗng. Dữ liệu và cấu trúc logic của **14 bảng** khớp baseline; AUTO_INCREMENT
có thể tăng do fixture và được giữ nguyên. Hash toàn bộ file MySQL cũ khớp.
Không chạy migration, sửa tài khoản thật, commit, push hoặc merge.

Backup cấu hình, database dump đã phục hồi thử đủ 14 bảng và bằng chứng được
khóa ACL ngoài repository/DocumentRoot. Đường dẫn thật được lưu riêng trên máy:

```text
<BACKUP_ROOT>\recovery-local-activation-<deployment-id>\
  private\config-before\
  private\duan1-before-activation.sql
  private\tools\
  evidence\final-verification.json
```

HMAC 256-bit hiện có được giữ nguyên; đã xác minh ACL, khả năng đọc và cùng key
giữa Apache/CLI. Apache vẫn dùng PHP 8.2.12, PHPMailer 7.1.1 và Composer autoload
hiện có. Không cài hoặc nâng dependency.

## Cấu hình có thể chia sẻ và cấu hình riêng

`mvc/.htaccess` đang chạy được Git bỏ qua, tiếp tục giữ Recovery ON và session
`required`. Chỉ commit `mvc/.htaccess.example`: Recovery OFF, session `required`,
loopback-only và Mailpit local. Không copy mẫu đè lên deployment đang hoạt động.
Thiếu/sai session mode gây lỗi an toàn, không tự hạ về legacy.

Trên máy mới, copy `tools/local-tools.example.json` thành
`tools/local-tools.local.json` (ignored) rồi điền đường dẫn tuyệt đối:

- `recovery_runtime`: thư mục private chứa key, `worker.local.json` và state.
- `mailpit_binary`: binary Windows đã xác minh từ release chính thức.
- `mailpit_runtime`: inbox/log/state ngoài mọi web root.
- `document_root`: DocumentRoot thực tế, dùng để từ chối lưu runtime/config bên trong.

File local chỉ chứa đường dẫn, không chứa password hoặc key. Máy hiện tại đã có
file này; các đường dẫn runtime và tiến trình hiện có được giữ nguyên.
Worker ưu tiên `FOURSMART_RECOVERY_RUNTIME`, rồi file local, rồi thư mục key được
khai báo trong `.htaccess`; nếu chưa có thì dùng LocalAppData/FourSmart/recovery-local.
Mailpit ưu tiên tham số `-Binary`/`-RuntimeDirectory`, biến
`FOURSMART_MAILPIT_BINARY`/`FOURSMART_MAILPIT_RUNTIME`, rồi file local; mặc định tìm
`mailpit.exe` trên PATH và dùng LocalAppData/FourSmart/mailpit-local.

Trong recovery runtime, tạo `worker.local.json` riêng với `php`, `database_config`
là đường dẫn tuyệt đối và `poll_seconds` từ 1 đến 10 (hiện 2). Database JSON và HMAC
phải nằm ngoài Git và web roots, có ACL chỉ cho runtime identity, SYSTEM và Admin.
Apache nhận `FOURSMART_DATABASE_CONFIG` từ cấu hình server private. `.htaccess`
chỉ tham chiếu `FOURSMART_RECOVERY_HMAC_KEY_FILE` và đặt URL localhost đã duyệt.
Không đưa nội dung các file private vào transcript, log hoặc Git.

## Khởi động sau khi đăng nhập Windows

Thực hiện thủ công theo thứ tự sau. Không chọn đăng ký Service hoặc bật MySQL
cũ trong XAMPP Control Panel.

1. **MariaDB mới:**

   ```powershell
   $mariaManager = '<đường dẫn private tới instance MariaDB mới>\manage.py'
   python $mariaManager status
   python $mariaManager start
   ```

   Chỉ START khi status báo stopped. Công cụ kiểm tra đúng server.ini/datadir và
   endpoint `127.0.0.1:33367`; không chuyển sang cổng 3306 hoặc `C:\xampp\mysql\data`.

2. **Apache:** mở `C:\xampp\xampp-control.exe`, bấm **Start ở dòng Apache**.
   Nếu Apache đã chạy thì giữ nguyên, không mở thêm tiến trình. Có thể kiểm tra
   cấu hình bằng:

   ```powershell
   & 'C:\xampp\apache\bin\httpd.exe' -t
   ```

   Website: `http://localhost/FourSmart/mvc/index.php`.
   Cấu hình database ngoài web root vẫn được Apache nạp từ cấu hình server.
   Các cờ local/HMAC file path nằm trong `mvc/.htaccess`; không cần restart
   Apache khi bật/tắt bằng công cụ bên dưới.

3. **Mailpit:**

   ```powershell
   & 'C:\xampp\htdocs\FourSmart\tools\local-mailpit.ps1' start
   & 'C:\xampp\htdocs\FourSmart\tools\local-mailpit.ps1' status
   ```

   SMTP `127.0.0.1:1025`, Web UI `http://127.0.0.1:8025`.
   Không relay, forwarding, auth hoặc TLS SMTP; không gửi Internet.

4. **Worker:**

   ```powershell
   & 'C:\xampp\htdocs\FourSmart\tools\local-recovery.ps1' start
   & 'C:\xampp\htdocs\FourSmart\tools\local-recovery.ps1' status
   & 'C:\xampp\htdocs\FourSmart\tools\local-recovery.ps1' queue
   ```

   START kiểm tra cấu hình CLI/database/HMAC trước khi chạy. Nếu đã có supervisor
   được quản lý, START trả trạng thái hiện tại và không tạo bản thứ hai.
   Sau Windows reboot, file state cũ được kiểm tra bằng PID + thời điểm tạo
   tiến trình; khóa file của lần chạy cũ đã được hệ điều hành giải phóng.

Recovery ON được giữ trong cấu hình qua reboot, nhưng MariaDB/Apache/Mailpit và
worker phải được khởi động thủ công. Không có tác vụ startup nào được đăng ký.

## Điều khiển worker

| Lệnh | Hành vi |
| --- | --- |
| `start` | Mở một supervisor ẩn cửa sổ; gọi worker PHP hiện có với `--once` mỗi 2 giây |
| `stop` | Gửi yêu cầu dừng; chờ invocation PHP hiện tại kết thúc; giữ Recovery ON |
| `status` | Xem PID, child PID nếu đang chạy, mode, phase, kết quả và thời điểm poll cuối |
| `queue` | Xem cấu hình an toàn và số job theo trạng thái, attempts/error code, số token/rate window |
| `once` | Chạy một invocation hữu hạn, chỉ khi không có supervisor/once được quản lý khác |
| `enable` | Kiểm tra cấu hình, backup và bật Recovery HTTP; không tự START worker |
| `disable` | Backup, tắt Recovery HTTP và yêu cầu dừng worker sạch |

Ví dụ dừng hoặc kiểm tra:

```powershell
& 'C:\xampp\htdocs\FourSmart\tools\local-recovery.ps1' stop
& 'C:\xampp\htdocs\FourSmart\tools\local-recovery.ps1' status
& 'C:\xampp\htdocs\FourSmart\tools\local-recovery.ps1' once
```

Worker gốc có hai chế độ: `--once` xử lý tối đa 1 job, mặc định xử lý tối đa 100
job và dừng khi idle. Supervisor dùng `--once`; không tạo hệ thống gửi mail hoặc
queue mới. Khóa file Windows giữ độc quyền giữa `start`/`once` của manager.
Sử dụng các lệnh manager để duy trì cơ chế chống chạy trùng.

STOP không kill PHP giữa transaction hoặc SMTP. Nếu sau 55 giây invocation vẫn
chưa xong, lệnh báo stop pending; yêu cầu dừng vẫn còn hiệu lực, xem STATUS lại.
Không có worker mới được manager tạo chồng lên supervisor còn sống.
Nếu PHP invocation thất bại 3 lần liên tiếp, supervisor dừng với `stopped_error`.
Lỗi gửi SMTP vẫn dùng retry/backoff và giới hạn 3 lần của ứng dụng.

STOP giữ cờ Recovery ON, nên request mới có thể xếp hàng khi worker dừng.
Job chờ quá 5 phút được worker loại bỏ theo policy hiện có. Khi cần ngừng toàn
bộ chức năng, dùng `disable`.

## Cấu hình riêng và log an toàn

Runtime ngoài Git/DocumentRoot:

```text
<recovery_runtime>\hmac.key
<recovery_runtime>\worker.local.json
<recovery_runtime>\worker\process.json
<recovery_runtime>\worker\worker.lock
<recovery_runtime>\worker\events.log
<recovery_runtime>\config-backups\
```

`worker.local.json` chỉ chứa PHP executable path, database config path ngoài
web root và poll interval (hiện 2 giây). Không chứa password hoặc raw HMAC.
Manager đọc cùng các `SetEnv` không bí mật từ `mvc/.htaccess`, bỏ biến FOURSMART
kế thừa và tạo môi trường riêng cho PHP CLI. Không cần copy raw key vào shell.

Manager yêu cầu session `required`, `LOCAL_ONLY=1`, Mailpit `127.0.0.1:1025`.
PHP preflight xác minh port/schema/user khớp database JSON private, cùng secret/URL
hợp lệ. Deployment hiện tại là `33367/duan1/foursmart_app@127.0.0.1`;
không hardcode endpoint riêng trong source và không fallback endpoint/SMTP Internet.

`queue` không in email, token, token hash, password hoặc HMAC. Job terminal
`sent`/`discarded`/`failed` có thể được giữ tới cleanup 24 giờ; queue hết việc
nghĩa là không có `pending`/`processing`. Không xóa rộng các bảng để làm sạch queue.

Xem log manager:

```powershell
$localTools = Get-Content .\tools\local-tools.local.json -Raw | ConvertFrom-Json
Get-Content -LiteralPath (Join-Path $localTools.recovery_runtime 'worker\events.log') -Tail 30
```

Log chỉ chứa timestamp, event allowlist và metadata vận hành. Idle không ghi
log mỗi poll; xem `last_poll_utc` trong STATUS. SMTP/DB exception và stdout lạ
của PHP bị loại bỏ, không ghi lại payload. Mailpit inbox dùng để xem email;
không copy reset URL hoặc token sang log/issue/report. Đã quét log Apache, PHP,
MariaDB, Mailpit và manager trong thử nghiệm: không có secret hoặc reset URL đầy đủ.

## Tắt khi phát hiện lỗi

```powershell
& 'C:\xampp\htdocs\FourSmart\tools\local-recovery.ps1' disable
& 'C:\xampp\htdocs\FourSmart\tools\local-recovery.ps1' status
```

DISABLE vẫn hoạt động khi thiếu cấu hình private/database. Nó đổi duy nhất cờ
Recovery và luôn giữ dòng session policy. Việc thay `.htaccess` là atomic,
không tạo cửa sổ file rỗng làm mất session `required`. Request đang xử lý/SMTP
đang gửi được phép hoàn tất trước khi supervisor dừng.

Nếu cần tắt cả stack, dừng worker trước; sau đó Mailpit bằng `local-mailpit.ps1 stop`,
Apache bằng XAMPP Control Panel, và MariaDB mới bằng:

```powershell
python $mariaManager stop
```

Không dùng `apache_stop.bat` hiện có vì file đó còn placeholder đường dẫn;
dùng Control Panel cho Apache. Không sửa MySQL cũ, hạ session policy hoặc chạy
migration để xử lý lỗi SMTP. Sau khi sửa cấu hình/dependency, dùng `queue` để
preflight; nếu đã tắt Recovery thì `enable`, rồi `start` và kiểm tra STATUS.

## Source chờ duyệt Git

Giai đoạn 4 thêm:

- `tools/local-recovery.py`, `tools/local-recovery.ps1`: vòng đời tiến trình,
  singleton, env chung, enable/disable atomic, backup và log allowlist.
- `mvc/cli/password_recovery_status.php`: preflight/queue read-only, không lộ email/secret.
- `tools/tests/local_recovery_manager_test.py`: 10 test Windows cô lập.
- `.gitignore`: bỏ qua Python cache và `.htaccess` runtime; chỉ chia sẻ mẫu OFF.
- Tài liệu vận hành này và liên kết trạng thái hiện tại trong các tài liệu trước.

Các thay đổi liên quan giai đoạn 1–3 vẫn chờ review: `Database.php`, recovery config,
mailer, SMTP smoke test, Mailpit script, các regression test và tài liệu local.
Danh sách đầy đủ cần duyệt được lưu trong `evidence/source-review.json` của lần
triển khai. Thư mục skill `.agents/`/`.claude/` có sẵn được giữ nguyên, không gộp
vào đề xuất commit chức năng.

Đợt 5A bổ sung mẫu Apache OFF, mẫu đường dẫn tools rỗng, fail-closed khi thiếu
session mode, test cấu hình tương ứng và hướng dẫn triển khai portable. Danh sách
23 file giai đoạn 4 là baseline review; `.htaccess` thật bị loại khỏi commit và
các file bổ sung phục vụ an toàn cấu hình được liệt kê trong báo cáo 5A.

Không đưa `vendor`, runtime, dump, key, private settings, log, PID/state hoặc dữ
liệu fixture sinh ra vào commit. Chỉ stage source/test đã kiểm tra, không stage
8 thư mục skill có sẵn. Chưa commit, push hoặc merge.
**Giữ Recovery ON local và dừng chờ phê duyệt commit/push.**
