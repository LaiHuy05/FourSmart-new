# Giai đoạn 3: Worker và Client password recovery E2E local

Ngày kiểm chứng: **24/10/2026**, Asia/Bangkok.
Nhánh: `feature/client-password-recovery`. Kết quả: **PASS**.

Cập nhật sau giai đoạn này: [giai đoạn 4 đã bật Recovery local](local-recovery-operations.md),
giữ session required và vận hành worker bằng supervisor thủ công.

Đã chứng minh luồng trên Chrome thật, PHP Apache, MariaDB mới và Mailpit.
Kết thúc thử nghiệm: **Recovery OFF**, **SessionVersionPolicy required**,
không còn worker chạy, fixture đã dọn. Chờ phê duyệt bật Recovery chính thức local.

## Kiểm tra trước khi bật

- Đã rà controller, router, views, JavaScript fragment, config, model, service,
  PHPMailer, CLI worker và SessionGuard. Giữ các thay đổi có sẵn trong workspace.
- MariaDB 10.4.32 ở `127.0.0.1:33367/duan1`, identity
  `foursmart_app@127.0.0.1`, datadir dưới `D:\FourSmartMariaDB`.
  Database class khai báo cổng cụ thể và kiểm tra identity, không fallback 3306.
- Cả ba bảng `password_reset_tokens`, `password_reset_jobs`,
  `password_reset_rate_limits` rỗng trước thử nghiệm. Không có job tài khoản thật
  hoặc token/rate window cũ để worker cleanup tác động tới.
- 14 bảng dùng InnoDB; không có trigger, event hoặc routine của ứng dụng.
  General query log và slow query log tắt.
- Mailpit giữ `127.0.0.1:1025` và `127.0.0.1:8025`, không relay/forwarding.
  Reset URL cố định `http://localhost/FourSmart/mvc/index.php`.
- Backup mới đã được phục hồi thực vào schema tạm riêng trên cổng 33367.
  So sánh đủ dữ liệu/cấu trúc 14 bảng thành công rồi xóa schema tạm.
  Không chạy file migration và không restore vào `duan1`.

Backup và bằng chứng được khóa ACL tại:

```text
<BACKUP_ROOT>\recovery-phase3-<deployment-id>\
  private\duan1-before-phase3.sql
  private\context.json
  private\config-before\
  private\tools\
  evidence\backup.json
  evidence\restore-verification.json
  evidence\preflight.json
  evidence\database-final.json
  evidence\final-verification.json
```

`backup.json` ghi SHA-256 và kích thước dump. Dump, cấu hình fixture và công cụ
được giữ ngoài Git/DocumentRoot. Không đưa nội dung private vào log hoặc báo cáo.

## Thay đổi cấu hình và mã

[mvc/config/password_recovery.php](../mvc/config/password_recovery.php) hỗ trợ
`FOURSMART_RECOVERY_HMAC_KEY_FILE`. File phải đọc được, tối đa 4096 byte,
ở ngoài repository/DocumentRoot và chứa base64 của ít nhất 32 byte key.
Vẫn hỗ trợ biến secret trực tiếp cũ; cấu hình đồng thời cả hai nguồn bị từ chối.
Lỗi cấu hình không in key, đường dẫn secret hoặc exception gốc.

Key random 256-bit ổn định nằm tại:
`<recovery_runtime>\hmac.key`, với ACL giới hạn tài khoản runtime,
SYSTEM và Administrators. Đã xác minh Apache và CLI giải mã đúng cùng key bằng
HMAC challenge; không in key ra terminal hoặc ghi key vào source.

`FOURSMART_RECOVERY_LOCAL_ONLY=1` giới hạn Recovery HTTP theo `REMOTE_ADDR`
loopback, không tin `X-Forwarded-For`. CLI vẫn dùng cấu hình worker riêng.
Các thiết lập không chứa secret trong `mvc/.htaccess` hiện tại:

```text
FOURSMART_PASSWORD_RECOVERY_ENABLED=0
FOURSMART_SESSION_VERSION_MODE=required
FOURSMART_RECOVERY_LOCAL_ONLY=1
FOURSMART_RECOVERY_HMAC_KEY_FILE=<absolute private key-file path>
FOURSMART_APP_URL=http://localhost/FourSmart/mvc/index.php
```

SMTP/From/Reply-To giữ cấu hình Mailpit của giai đoạn 2. Không sửa SessionGuard,
SessionVersionPolicy, migration, database endpoint hoặc dependency.

Thêm hai bộ test:

- `password_recovery_secret_config_test.php`: 8 test nguồn secret, độ dài,
  encoding, đường dẫn, file quá lớn và tương thích biến môi trường cũ.
- `password_recovery_worker_transport_test.php`: 3 test entrypoint từ chối
  khi OFF, PHPMailer lỗi kết nối với retry/backoff tối đa ba lần và SMTP timeout
  giải phóng lease. Mọi queue/token trong bộ test này thuộc SQLite tạm.

## Worker và E2E thực tế

Gate trước bật: **45/45 PASS**, gồm config, model/service/concurrency, mailer
và worker transport. Chrome dùng profile tạm và các browser context tách biệt.

1. Tạo Client fixture riêng, bcrypt password, role 2; tạo thêm Admin fixture
   riêng chỉ để kiểm tra âm tính và hồi quy quyền. Không dùng tài khoản thật.
2. Bật Recovery tạm. Probe qua Apache xác nhận bật thật, session `required`,
   key/URL/database đúng. Hai phiên Chrome đăng nhập Client trước reset.
3. Mở trang Quên mật khẩu từ liên kết đăng nhập, gửi email Client fixture.
   Request tạo đúng một job pending, chưa có token. Gửi lại ngay không thêm job
   và vẫn trả thông báo chung.
4. Kiểm tra queue ngay trước chạy; gọi CLI entrypoint thật với **`--once`**.
   Exit 0, stdout `sent`, stderr rỗng. Chỉ xử lý **một job**; không chạy daemon.
5. Job `sent`, attempts 1, lease được giải phóng. Worker sinh token 256-bit,
   chỉ lưu SHA-256 binary 32 byte. TTL đúng 900 giây.
6. Mailpit nhận đúng một email recovery. Đối chiếu hash email token với DB
   trong bộ nhớ, không ghi token/reset URL vào file bằng chứng.
7. Chrome mở email qua Mailpit Web renderer và click link reset thật.
   JavaScript chuyển fragment vào hidden POST field và xóa fragment khỏi URL/history.
8. Đặt mật khẩu mới thành công, token có `used_at`, session version tăng 0 → 1.
   Mật khẩu cũ không đăng nhập được; mật khẩu mới đăng nhập và mở profile được.
9. GET từ phiên cũ chuyển về login; POST từ phiên cũ bị 403 trước mutation.
   Dùng lại cùng token bị từ chối, password/version không đổi lần hai.

Không lưu DOM reset có hidden token hoặc URL token trong report. Bốn ảnh chỉ
chụp trang Quên mật khẩu, nội dung HTML email có link hiển thị bằng nhãn,
form reset trống và thông báo reset thành công.

## Kiểm thử bảo mật và hồi quy

| Nhóm | Kết quả |
| --- | --- |
| E2E/runtime thực qua Apache, Chrome, MariaDB và Mailpit | **36/36 PASS** |
| Model/service/config/concurrency SQLite | 21/21 PASS |
| PHPMailer/SMTP lỗi, timeout, bảo mật log | 13/13 PASS |
| Worker transport/entrypoint cô lập | 3/3 PASS |
| Nguồn secret ngoài repository | 8/8 PASS |
| SessionVersionPolicy SQLite/HTTP | 21/21 PASS |
| Recovery HTTP SQLite | 10/10 PASS |
| Đổi mật khẩu/profile/login SQLite/HTTP | 41/41 PASS |
| Database config/fallback guard | 12/12 PASS |
| JavaScript DOM simulation | 5/5 PASS |
| Tổng regression chạy lại sau E2E | **134/134 PASS** |
| Kiểm tra trạng thái cuối | **15/15 PASS** |

Không cộng lại 45 test gate vì chúng là tập con của regression 134 test.
Không tuyên bố chạy lại nguyên bộ 288 kiểm tra của giai đoạn triển khai session.

E2E thực kiểm tra CSRF sai, email sai, forged token, replay, thông báo đồng nhất
cho Client/Admin fixture/email không tồn tại; request IP limit bão hòa tại 20;
reset verification trả 429 cùng `Retry-After: 900` khi quá giới hạn 30.
Admin fixture không đổi password/version và không có token Admin; Client vẫn
không truy cập được Admin. Login và account listing của Admin fixture hoạt động.
Không chạy worker cho các job âm tính Admin/unknown; chúng được dọn theo marker.
Việc worker loại Admin/unknown được kiểm thử sâu trong fixture SQLite.

TTL của token MariaDB thật được kiểm tra read-only bằng clock ở ngay trước
expiry và đúng expiry: trước hạn hợp lệ, đúng hạn bị từ chối. Không sửa clock
server, không đổi TTL, không chờ 15 phút hoặc sửa timestamp token thật.
Retry/lease/race/job quá hạn được kiểm thử bằng fixture, không fault-inject
vào tài khoản thật hoặc tắt MariaDB.

Quét phần log phát sinh của Apache access/error, PHP, MariaDB và Mailpit:
không có token, password fixture, HMAC key hoặc reset URL đầy đủ. Worker chỉ
ghi kết quả allowlist. SMTP tới loopback, không có relay/gửi Internet.

Các test MariaDB lịch sử trong `support/guarded_mariadb.php` cố định cổng 3306;
không chạy chúng trong giai đoạn này. E2E trên `duan1` dùng Database class thật
và kiểm chứng cổng 33367; test cô lập dùng SQLite, không fallback về MySQL cũ.

## Dọn dữ liệu và trạng thái cuối

- Dọn chính xác tài khoản Client/Admin fixture bằng ID + username + email + marker;
  dọn job/token và rate keys thuộc fixture/IP thử. Không DELETE rộng dữ liệu thật.
- Ba bảng recovery trở lại rỗng. So sánh đủ số dòng, digest dữ liệu và cấu trúc
  của 14 bảng `duan1` với baseline: khớp. AUTO_INCREMENT có thể tăng do fixture;
  không reset counter hoặc tái sử dụng ID để tránh ảnh hưởng ghi đồng thời.
- Inventory/hash toàn bộ file `C:\xampp\mysql\data` trước/sau khớp hoàn toàn.
- Xóa đúng email recovery của lần thử trong Mailpit; giữ hai email smoke test cũ.
- Đóng Chrome/profile tạm, dọn session thử được theo dõi, xóa probe Apache tạm.
- Không có worker còn chạy. Recovery về OFF, session policy vẫn `required`.
- Website cuối: Client home/login 200, Admin chưa đăng nhập về login;
  recovery routes 503 đúng trạng thái OFF. Apache syntax, PHP syntax và Git diff PASS.
- Không commit, push, merge, migration hoặc thay đổi MySQL cũ.

## Trước khi bật Recovery chính thức local

Không có lỗi chức năng còn tồn tại trong phạm vi kiểm thử này. Cần phê duyệt:

1. Bật flag Recovery của Apache local, giữ `required`, `LOCAL_ONLY=1`, key file
   ổn định và Mailpit loopback. Không tạo hoặc thay key khi đang có token/rate window.
2. Chốt cách vận hành worker hữu hạn: thủ công `--once` hoặc lịch chạy được duyệt.
   CLI phải nhận database config ngoài web root, key file, session required,
   recovery flag và cùng APP_URL; không tự kế thừa `SetEnv` của Apache.
3. Chốt theo dõi lỗi allowlist, nhịp cleanup và bảo vệ Mailpit inbox chứa link reset.
   Delivery có retry, không bảo đảm exactly-once khi worker chết sau SMTP acceptance;
   chỉ token mới nhất hợp lệ. Không tự cấu hình daemon, scheduler hoặc SMTP Internet.

**Dừng chờ phê duyệt bật chính thức local.**
