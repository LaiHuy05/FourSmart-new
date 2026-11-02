# FourSmart-new — báo cáo kiểm thử sau nâng cấp giao diện

Ngày: **02/11/2026**, múi giờ Asia/Saigon.

## 1. Kết quả review thay đổi frontend

**No findings.**

Đã đọc toàn bộ diff chưa commit so với HEAD, hai file frontend mới và các đoạn mã liên quan trong View, CSS, JavaScript, router, Controller/Model xác thực, sản phẩm, giỏ hàng và thanh toán. Chưa xác nhận được lỗi hồi quy mới đáp ứng tiêu chí của Review Agent. Điều này không có nghĩa toàn bộ nghiệp vụ đã được kiểm chứng: có lỗi tài nguyên ảnh tồn tại sẵn và các luồng tích hợp còn BLOCKED bên dưới.

Đã áp dụng:

- `.agents/skills/verification-before-completion/SKILL.md`: chạy lại kiểm tra trong phiên này, đọc output và exit code; không dùng kết quả phiên nâng cấp trước để đánh dấu PASS.
- `C:/Users/Dell/.codex/skills/.system/review-agent/SKILL.md`: review trực tiếp, không giao review cho agent khác, không sửa lỗi; chỉ xem lỗi phát sinh từ diff là finding hồi quy.

## 2. Git, phạm vi và môi trường

| Hạng mục | Kết quả | Bằng chứng |
| --- | --- | --- |
| Nhánh đầu/cuối phiên | PASS | `git branch --show-current`: `feature/foursmart-skill` |
| HEAD được review | PASS | `731041399b1452cfe67ce43674a92e475cd7f665` |
| Bảo toàn mã nguồn và thay đổi có sẵn | PASS | Chụp SHA-256 các file tracked/untracked không bị ignore trước/sau; không có khác biệt, ngoại trừ báo cáo này được loại khỏi phép so sánh |
| Không commit/push/merge/chuyển nhánh | PASS | Không thực hiện các thao tác này; HEAD và nhánh được đối chiếu lại |

Repository: `C:/xampp/htdocs/FourSmart-new`.

Website thực: `http://localhost/FourSmart-new/mvc/index.php`.

Runtime đã xác minh: PHP **8.2.12**, Node **v24.22.1** đi kèm công cụ Playwright, Playwright **1.64.1**, Google Chrome headless có sẵn trên máy. Không cài dependency vào repository ứng dụng.

Kích thước kiểm tra: **1440×1000** (desktop), **768×1024** (tablet), **390×844** (mobile). Có đo bổ sung header đã đăng nhập bằng fixture tại 1151px và 1200px.

Phạm vi thay đổi gồm 28 file PHP View/partial đã sửa, `mvc/view/assets/css/theme.css`, `mvc/view/assets/js/interface.js` và tài liệu nâng cấp đã có. Các View tích hợp theme/script, bổ sung viewport, nội dung hero và một số thuộc tính accessibility/autocomplete. Không có Controller, Model hoặc schema thay đổi trong diff đang review.

Quy ước:

- **PASS**: đã chạy và kết quả đáp ứng điều kiện được mô tả, chỉ có giá trị trong phạm vi đó.
- **FAIL**: hành vi hoặc tài nguyên thực tế không đáp ứng yêu cầu; phân biệt lỗi có sẵn với hồi quy.
- **BLOCKED**: chưa thực thi được ca đầy đủ vì thiếu điều kiện hoặc vượt giới hạn không ghi database. Không xem fixture/mock là PASS nghiệp vụ thực.

## 3. Kiểm tra bằng công cụ hiện có

| ID | Kiểm tra đã chạy | Kết quả | Output |
| --- | --- | --- | --- |
| S01 | `php -l` từng file trong `mvc/**/*.php` | PASS | **98 file**, 0 lỗi cú pháp |
| S02 | `node --check` cho `client-password.js`, `client-password-recovery.js`, `interface.js` | PASS | 3 file, exit 0 |
| S03 | `node mvc/tests/client_password_recovery_js_test.js` | PASS | **5 passed, 0 failed**; đây là mô phỏng DOM |
| S04 | `php mvc/tests/database_config_test.php` | PASS | **12 passed, 0 failed**; cấu hình giả và endpoint không có DB, không ghi database thực |
| S05 | `php mvc/tests/password_recovery_secret_config_test.php` | PASS | **10 passed, 0 failed**; chỉ file tạm và biến môi trường trong tiến trình test |
| S06 | `git -c core.safecrlf=false diff --check` | PASS | Exit 0, không có lỗi whitespace |
| S07 | Đối chiếu các giá trị `name`, `id`, `action`, `href` có sẵn trong View với HEAD | PASS | Không mất giá trị thuộc tính có sẵn; không thay thế việc kiểm tra mọi phụ thuộc nghiệp vụ |

Tổng ba bộ test sẵn có đã chạy: **27 passed, 0 failed**. Không chạy trọn bộ test repository: các suite cần SQLite/MariaDB fixture, provision schema, worker hoặc SMTP không thuộc phạm vi lần kiểm thử không thay đổi database. Các file như `account_model_test.php`, `cart_model_test.php`, `order_model_test.php` hiện là script dump dữ liệu, không phải suite assertion chứng minh luồng nghiệp vụ.

Lệnh runner dùng trong phiên này:

```powershell
python G:\Chapter8_Demo\foursmart-verification\run_checks.py
python G:\Chapter8_Demo\foursmart-verification\browser_checks.py
python G:\Chapter8_Demo\foursmart-verification\focused_checks.py
python G:\Chapter8_Demo\foursmart-verification\payload_checks.py
python G:\Chapter8_Demo\foursmart-verification\payload_checks.py --retest
```

Các script này là công cụ kiểm thử ngoài repository, không phải mã nguồn tính năng mới.

## 4. Browser automation trên website thực

Đã tải **27 URL công khai × 3 viewport = 81 lượt kiểm tra trang thực**: trang chủ, đăng nhập, đăng ký, tìm kiếm có kết quả, từ khóa rỗng, không có kết quả, toàn bộ **16 trang chi tiết sản phẩm** và **5 danh mục** lấy từ liên kết trên trang chủ.

Cả 81 lượt: HTTP 200, theme có nạp vào stylesheet của trang, không có dấu hiệu PHP warning/fatal trong nội dung trang và không phát hiện tràn ngang ở mức document. Tiêu chí này không thay thế kiểm tra mọi phần tử hoặc mọi thiết bị.

| ID | Chức năng / trường hợp | Kết quả | Bằng chứng và giới hạn |
| --- | --- | --- | --- |
| F01 | Trang chủ, danh mục, danh sách và chi tiết sản phẩm | PASS | 81 lượt trên; dữ liệu hiện tại được render qua Controller/Model thật |
| F02 | Submit tìm kiếm từ header | PASS | Điền `iphone`, bấm nút, đến `?client=productSearch&q=iphone` và có product card |
| F03 | Từ khóa rỗng / không khớp | PASS | Trang trả 200; trạng thái rỗng được kiểm tra trong lượt tải trang |
| F04 | Ký tự HTML trong từ khóa | PASS | `<script>alert(1)</script>` nằm trong giá trị text được escape; không có script inline được chèn |
| F05 | Đăng nhập để trống trường | PASS | Submit form thật, hiển thị “Vui lòng nhập tài khoản và mật khẩu” |
| F06 | Đăng nhập tài khoản không tồn tại | PASS | Truy vấn chỉ đọc xác nhận `demo_client` không có trong DB trước khi thử; form trả “Tài khoản hoặc mật khẩu không đúng” |
| F07 | Đăng nhập CSRF không hợp lệ | PASS | Form thật bị từ chối; HTTP 403, thông báo CSRF; trường tài khoản/mật khẩu để trống |
| F08 | Đăng ký thiếu dữ liệu | PASS | Form thật trả “Vui lòng nhập đầy đủ thông tin”; nhánh dừng trước thao tác ghi |
| F09 | Đăng ký mật khẩu ngắn | PASS | Dữ liệu đầy đủ, mật khẩu 3 ký tự; trả “Mật khẩu phải có ít nhất 6 ký tự”, dừng trước truy vấn tạo tài khoản |
| F10 | Email sai định dạng tại form đăng ký | PASS | `input.checkValidity()` trả false cho `invalid-email`; chỉ chứng minh validation phía trình duyệt |
| F11 | Mở giỏ hàng khi chưa đăng nhập | PASS | HTTP 401 và “Vui lòng đăng nhập”; đây là hành vi hiện có trong Controller |
| F12 | Mở checkout, profile, orderHistory hoặc admin khi chưa đăng nhập | PASS | Chuyển về trang đăng nhập; không truy cập nội dung được bảo vệ |
| F13 | Đăng xuất phiên khách | PASS | Route logout kết thúc phiên khách và chuyển về login; **không chứng minh đăng xuất phiên đã xác thực** |
| F14 | GET quên mật khẩu / đặt lại mật khẩu | PASS | Cấu hình local bật recovery; cả hai trả 200. Reset không có token giữ nút submit disabled |
| F15 | Hero CTA và link footer về trang chủ | PASS | Bấm CTA tới `#products`; link footer tải lại trang chủ |
| F16 | Hai ảnh upload không tải được | FAIL | Xem lỗi D01; placeholder hoạt động nhưng tài nguyên gốc vẫn 404 |

Không submit yêu cầu khôi phục mật khẩu, không gửi email và không đổi mật khẩu trên deployment thực.

## 5. Giao diện và tương tác tách biệt backend

Đã render **26 View PHP thật × 3 viewport = 78 lượt** bằng dữ liệu minh họa, gồm tất cả trang client/admin hiện có. Runner thực thi PHP CLI để lấy HTML, Chrome nhận HTML và asset qua route fixture của Playwright. Không bỏ qua guard của website thực và không tạo phiên đăng nhập giả trên localhost.

| ID | Nội dung đã thực thi | Kết quả | Phạm vi |
| --- | --- | --- | --- |
| U01 | 26 View ở desktop/tablet/mobile | PASS | 78 lượt không tràn ngang document, không duplicate DOM ID và không có PHP warning/fatal trong HTML fixture |
| U02 | Header, navbar, footer, auth card, checkout, sidebar và form biến thể | PASS | Đã đọc ảnh chụp đại diện desktop 1440px, tablet 768px và mobile 390px; kiểm tra tự động toàn bộ View theo U01 |
| U03 | Tab giữa username/password, label liên kết control và focus | PASS | Kiểm tra bằng bàn phím và DOM; focus outline hiện trên link card. Không phải chứng nhận accessibility toàn website |
| U04 | Hover card và button | PASS | Card có transform khi hover; button thay đổi background khi hover |
| U05 | Thanh danh mục trên mobile | PASS | `scrollWidth > clientWidth`, thay đổi `scrollLeft` thành công, không làm document tràn ngang |
| U06 | Reduced motion | PASS | Emulate `prefers-reduced-motion: reduce`; scrollBehavior thành auto, transition card về 0 |
| U07 | Ảnh dự phòng | PASS | Hai ảnh lỗi chuyển thành placeholder; placeholder nằm trong khung card ở mobile |
| U08 | Loading / quay lại trang | PASS | Submit form fixture vào iframe: `aria-busy=true`, submitter vẫn enabled; phát `pageshow` xóa busy. Không gửi POST đến backend thật |
| U09 | Active menu quản trị trên trang sửa sản phẩm | PASS | Chỉ một link section sản phẩm có `aria-current`; URL fixture có query `act=admin&admin=productUpdate` |
| U10 | Header ở 1151/1200/1440px với nav đăng nhập | PASS | Fixture admin/client: header cao 89px, offset sticky 100px, không tràn ngang |
| U11 | Chọn biến thể và submit thêm giỏ hàng | PASS | Fixture gửi đủ `csrf_token`, `product_id`, `color`, `memory` đến route hiện có; chỉ kiểm tra form/payload |
| U12 | Nút giảm/tăng/xóa giỏ hàng | PASS | Fixture lần lượt gửi `cartDecrease`, `cartIncrease`, `cartDelete`, giữ `csrf_token` và `detail_id`; không cập nhật giỏ thực |
| U13 | Form checkout và đăng ký | PASS | Fixture gửi đúng các trường được kiểm tra. Không tạo account hoặc order |
| U14 | Form admin thêm/sửa sản phẩm, thêm/sửa danh mục, sửa account, thêm màu, đổi trạng thái đơn | PASS | 7 form được submit trong fixture, đúng query route và các trường bắt buộc được runner đối chiếu; upload là buffer test trong bộ nhớ, không lưu lên server |
| U15 | Xác nhận mật khẩu khôi phục không khớp | PASS | Fixture token minh họa, `checkValidity()` chặn xác nhận sai; không đặt lại mật khẩu thực |
| U16 | Exception JavaScript trong browser suite chính | PASS | 0 sự kiện `pageerror`; không đồng nghĩa console hoàn toàn không có HTTP error |

**Giới hạn của fixture:** chỉ xác nhận khả năng render, tương tác trình duyệt và cấu trúc request. Không kiểm chứng đăng nhập thành công, phân quyền trong phiên thật, tồn kho, tổng tiền nghiệp vụ, persistence hoặc transaction thanh toán.

Console/network ghi nhận HTTP 404 của hai ảnh D01. HTTP 401 giỏ hàng và HTTP 403 CSRF là kết quả mong đợi của các ca kiểm tra; không xếp là lỗi hồi quy.

## 6. FAIL — lỗi thực tế và cách tái hiện

### D01 — [P2] Khôi phục hai tài nguyên ảnh sản phẩm bị thiếu

**Trạng thái:** FAIL, lỗi dữ liệu/tài nguyên có sẵn; **không phải finding hồi quy do diff frontend**.

**Ảnh hưởng:** sản phẩm “IP 17 PRM” và “iP 14 Pro Max” không hiện ảnh gốc; trang chủ và các trang dùng cùng `sp_image` có request 404. Theme mới có fallback nên không còn biểu tượng ảnh hỏng, nhưng người mua vẫn không xem được ảnh sản phẩm.

**Cách tái hiện đã thực thi:**

1. Mở `http://localhost/FourSmart-new/mvc/index.php` bằng Chrome.
2. Cuộn tới hai card trên trong danh mục iPhone.
3. Mở Network, thấy GET của hai tài nguyên dưới trả HTTP 404.
4. Card hiển thị “Hình ảnh đang cập nhật” thay cho ảnh gốc.

**Tài nguyên:**

```text
mvc/upload/product_58978f923295af54.jpg
mvc/upload/product_4dbb3d5e045d19e5.jpg
```

**File liên quan:** `mvc/view/client/home.php:135`, `mvc/view/client/productDetail.php:43`, cùng các View danh mục/tìm kiếm dùng `sp_image`. `mvc/view/assets/js/interface.js` chỉ xử lý trạng thái dự phòng; không phải nguồn gây 404. HEAD trước nâng cấp cũng render cùng giá trị `sp_image` từ dữ liệu.

**Kỳ vọng:** ảnh sản phẩm truy cập được với HTTP 200. Chưa sửa hoặc bổ sung file ảnh theo yêu cầu chỉ kiểm thử.

## 7. BLOCKED — không đánh dấu PASS các luồng chưa thực thi

| ID | Ca chưa thực thi đầy đủ | Trạng thái | Nguyên nhân / điều kiện cần |
| --- | --- | --- | --- |
| B01 | Đăng nhập thành công client và admin | BLOCKED | Read-only preflight kết nối được DB nhưng không có `demo_client`/`demo_admin` trong README. Không có thông tin đăng nhập hợp lệ cho môi trường hiện tại; không tạo/reset tài khoản |
| B02 | Đăng xuất từ phiên đã đăng nhập và kiểm tra session sau logout | BLOCKED | Phụ thuộc B01; chỉ thử logout phiên khách |
| B03 | Đăng ký thành công / tài khoản hoặc email trùng | BLOCKED | Tạo account thành công ghi DB tại `auth_controller.php:126`; không thử dữ liệu hợp lệ có thể tạo account. Ca trùng chưa có fixture backend đã xác thực |
| B04 | Mở giỏ hàng trong phiên thật; thêm/tăng/giảm/xóa và persistence | BLOCKED | Thiếu B01; ngay GET cart có thể tự tạo cart tại `cart_controller.php:48`. Các thao tác còn lại ghi DB nên không thực thi |
| B05 | Checkout với giỏ thật, tạo đơn, trừ tồn kho, xóa giỏ | BLOCKED | Thiếu phiên/giỏ thật; `CheckoutService::createOrder` tại `checkout_controller.php:69` ghi dữ liệu |
| B06 | Dashboard và quyền admin trong phiên thật | BLOCKED | Thiếu admin credential; guest guard đã kiểm tra, nội dung admin mới kiểm tra bằng fixture |
| B07 | CRUD admin / biến thể / trạng thái đơn / tài khoản / bình luận | BLOCKED | Không thực hiện ghi DB. Các form được chọn chỉ thử payload trong fixture |
| B08 | Hồ sơ, lịch sử/chi tiết đơn trong phiên thật, gửi bình luận, đổi mật khẩu | BLOCKED | Thiếu phiên client; các thao tác ghi dữ liệu nằm ngoài giới hạn phiên này |
| B09 | Recovery gửi mail / token thực / reset thành công | BLOCKED | Sẽ ghi token/job/rate-limit hoặc mật khẩu và gửi mail; chỉ thử GET cùng validation fixture |
| B10 | Các suite test DB/worker/SMTP chưa chạy | BLOCKED | Cần DB fixture hoặc dịch vụ và có thể ghi dữ liệu; không chạy khi người dùng yêu cầu không thay đổi database |
| B11 | Safari/Firefox, thiết bị vật lý và kiểm thử accessibility đầy đủ | BLOCKED | Phiên này dùng Chrome headless với viewport mô phỏng; chưa thực thi các môi trường đó |

Read-only preflight chỉ thực hiện truy vấn trong transaction `READ ONLY` rồi rollback. Không đọc/ghi dữ liệu bí mật ra báo cáo. Không thử đăng nhập dạng mật khẩu legacy vì `AccountModel::verifyPassword()` có thể nâng cấp hash và ghi database.

## 8. Sai lệch bộ kiểm tra đã xử lý

Các trường hợp sau được giữ trong log thô để truy vết, nhưng không tính là lỗi dự án:

- Browser suite chính ban đầu có 178 PASS và 2 FAIL do runner kỳ vọng recovery OFF/503. Đọc cấu hình local xác nhận `FOURSMART_PASSWORD_RECOVERY_ENABLED=1`; kiểm thử lại hai GET với điều kiện đúng đều PASS/200, nút reset thiếu token vẫn disabled.
- Lượt thử payload bổ sung ban đầu dừng ở form upload vì bộ dữ liệu test chưa gắn file bắt buộc. Bổ sung buffer upload trong runner; không sửa View.
- Bốn form admin không khai báo `action` có kết quả query route rỗng khi URL fixture thiếu query của Controller. Mở lại fixture với `act=admin&admin=...&id=1`, rồi chạy riêng bốn ca: cả bốn PASS. Các form thật giữ nguyên hành vi submit về URL hiện tại.

Trạng thái cuối phải đọc kết hợp log chính và log retest, không dùng riêng các FAIL do sai fixture để kết luận sản phẩm lỗi.

## 9. Bằng chứng lưu ngoài repository

Thư mục: `G:/Chapter8_Demo/foursmart-verification/`.

| Artifact | Nội dung |
| --- | --- |
| `baseline.json` | SHA-256 trạng thái mã nguồn khi bắt đầu kiểm thử |
| `checks.json` | Lệnh, exit code, output test có sẵn và kiểm tra contract |
| `preflight.json` | Kết quả truy vấn chỉ đọc: DB kết nối được, hai demo account không tồn tại |
| `browser-results.json`, `browser-progress.json` | 180 ca browser ban đầu: 81 lượt trang thực, 78 lượt View và 21 ca chức năng/tương tác; chứa hai kỳ vọng recovery sai đã retest |
| `focused-results.json` | 12 ca bổ sung/retest, tất cả PASS; bao gồm hai GET recovery chạy lại |
| `payload-results.json` | Lượt payload bổ sung; xem retest cho bốn form thiếu query fixture |
| `payload-retest-results.json` | Bốn form admin chạy lại với query đúng, tất cả PASS |
| `home-1440.png`, `home-768.png`, `home-390.png` | Ảnh chụp trang chủ thực |
| `login-*.png`, `product-0-*.png` | Ảnh trang đăng nhập và sản phẩm thực |
| `client-cart.php-*.png`, `client-pay.php-*.png`, `admin-home.php-*.png`, `admin-product-variant.php-*.png` | Ảnh View dùng dữ liệu minh họa; không phải phiên đăng nhập thực |

## 10. Đánh giá cuối

Các kiểm tra đã thực thi xác nhận phần cú pháp, hiển thị tại ba viewport, luồng đọc sản phẩm/tìm kiếm, một số validation xác thực và payload giao diện hoạt động trong phạm vi nêu trên. Có một lỗi tài nguyên ảnh tồn tại sẵn (hai file 404). Chưa có finding hồi quy frontend được xác nhận.

**Chưa đủ bằng chứng kết luận toàn bộ dự án hoặc các luồng mua hàng/admin đã PASS.** Cần tài khoản test hợp lệ và một môi trường được phép ghi dữ liệu để thực thi các ca tích hợp đang BLOCKED. Trong phiên này chỉ tạo báo cáo trong repository; không sửa lỗi, Controller, Model, CSS, JavaScript, database, không commit/push/merge và giữ nguyên các thay đổi có sẵn.
