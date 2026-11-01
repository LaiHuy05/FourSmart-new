---
name: foursmart-project-assistant
description: Phân tích mã nguồn FourSmart-new, tạo sơ đồ UML, ERD và viết báo cáo kỹ thuật dựa trên triển khai thực tế. Dùng cho tài liệu kiến trúc, nghiệp vụ và cơ sở dữ liệu của dự án FourSmart-new.
---

# FourSmart Project Assistant

Hỗ trợ phân tích và lập tài liệu bằng tiếng Việt; giữ nguyên tên lớp, phương thức, route, bảng và cột trong mã nguồn. Mọi mô tả hiện trạng phải có bằng chứng từ checkout đang làm việc.

## Phạm vi và Git

- Xác định repository FourSmart-new bằng `git rev-parse --show-toplevel`, kiểm tra `git branch --show-current` và `git status --short` trước khi tạo hoặc sửa tệp. Đọc AGENTS.md áp dụng nếu có.
- Chỉ làm việc trên nhánh `feature/foursmart-skill`. Nếu đang ở nhánh khác, dừng thao tác ghi và thông báo cần chuyển sang nhánh được phép; không tự chuyển nhánh. Không chuyển sang hoặc chỉnh sửa `main`.
- Chỉ tạo hoặc cập nhật Skill và tài liệu/sơ đồ thuộc yêu cầu. Không sửa chức năng hiện có, mã PHP/JS/CSS, schema, migration, cấu hình runtime hoặc dependency để làm tài liệu khớp với giả định.
- Không tự ý commit, push hoặc merge. Giữ nguyên thay đổi có sẵn của người dùng; không reset, clean hoặc ghi đè chúng.
- Không cần chạy website, kết nối database, import SQL, gửi mail hoặc bật recovery để phân tích tĩnh. Không đọc hay đưa cấu hình private, mật khẩu, token, cookie, khóa HMAC hoặc dữ liệu người dùng thật vào tài liệu.

## Bản đồ mã nguồn để bắt đầu

Các đường dẫn dưới đây tương đối với gốc repository, đã được đối chiếu khi tạo Skill; phải đọc lại phần liên quan trước mỗi nhiệm vụ vì mã có thể thay đổi.

| Nội dung | Nguồn cần đọc |
| --- | --- |
| Điểm vào, bootstrap, điều phối | `mvc/index.php`, `mvc/core/bootstrap.php`, `mvc/core/ActionRouter.php` |
| Route client/admin | `mvc/controller/client/client_controller.php`, `mvc/controller/admin/admin_controller.php` |
| Xử lý yêu cầu và giao diện | `mvc/controller/client/`, `mvc/controller/admin/`, `mvc/view/` |
| Truy cập dữ liệu qua PDO | `mvc/core/Database.php`, `mvc/model/` |
| Đặt hàng | `mvc/controller/client/checkout_controller.php`, `mvc/service/CheckoutService.php`, `mvc/model/CartModel.php`, `mvc/model/OrderModel.php`, `mvc/model/ProductModel.php` |
| Phiên, phân quyền, CSRF | `mvc/core/SessionGuard.php`, `mvc/core/SessionVersionPolicy.php`, `mvc/core/Csrf.php`, `mvc/middleware/require_admin.php` |
| Khôi phục mật khẩu | `mvc/controller/client/password_recovery_controller.php`, `mvc/model/PasswordRecoveryModel.php`, `mvc/service/PasswordRecoveryService.php`, `mvc/service/PasswordRecoveryMailer.php`, `mvc/config/password_recovery.php`, `mvc/cli/` |
| Schema demo và thay đổi schema | `database/foursmart-demo.sql`, `mvc/migrations/` |
| Kiểm thử, cách cài đặt và vận hành | `mvc/tests/`, `README.md`, `docs/`, `composer.json`, `composer.lock` |

Không giả định đây là Laravel hoặc có ORM: router hiện tại dùng `act`, `client`, `admin` và gọi controller qua `ActionRouter::dispatch`; thư mục là `controller` và `model` ở dạng số ít. Thư viện mail được khai báo trong Composer là PHPMailer; phiên bản cụ thể phải đọc lockfile.

## Phân tích mã nguồn

1. Xác định chức năng hoặc câu hỏi cần giải thích. Dùng `rg` để tìm route, lớp, phương thức và truy vấn liên quan; tránh quét `vendor/`, upload và cấu hình local.
2. Theo luồng từ `mvc/index.php` đến router, controller, service/model, SQL và view. Đọc thân phương thức và các hàm được gọi; tên route hay tên tệp chưa đủ chứng minh hành vi.
3. Ghi nhận đầu vào, điều kiện đăng nhập/phân quyền, kiểm tra dữ liệu, thao tác dữ liệu, transaction và nhánh lỗi thực sự có trong mã. Phân biệt điều kiện ở controller với bảo đảm ở database.
4. Với mỗi kết luận quan trọng, dẫn `đường/dẫn:tên_lớp::phương_thức` và số dòng của checkout hiện tại khi có thể. Ghi rõ phần chưa xác minh, mâu thuẫn giữa SQL/mã/tài liệu và đề xuất cải tiến; không trình bày đề xuất như tính năng đã triển khai.

Ví dụ luồng để khảo sát: `checkout` gọi `ClientCheckoutController::handle`, sau đó dùng `CheckoutService` và các model; service có transaction tạo đơn, thêm chi tiết, giảm tồn kho và xóa chi tiết giỏ hàng. Đọc lại các phương thức trước khi mô tả tính nguyên tử, nhánh rollback hoặc khả năng xử lý đồng thời. Không suy ra có cổng thanh toán trực tuyến chỉ từ tên trang thanh toán.

## Tạo UML

- Chọn sơ đồ theo mục đích: use case cho tác nhân/chức năng, activity cho quy trình, sequence cho tương tác theo thời gian, class cho cấu trúc lớp thực tế. Chỉ tạo các loại được yêu cầu hoặc cần để trả lời.
- Dùng Mermaid cho sequence/class/activity (flowchart), PlantUML khi cần use case hoặc cú pháp UML phù hợp hơn. Trả mã nguồn sơ đồ có thể chỉnh sửa, kèm giải thích và nguồn đối chiếu. Theo định dạng/đường dẫn người dùng yêu cầu; nếu chưa chỉ định, dùng tệp riêng dưới `docs/foursmart/` với tên mô tả nội dung.
- Giữ đúng tên lớp và phương thức, cách gọi static, chiều gọi, điều kiện rẽ nhánh, transaction và lỗi liên quan. Không tự thêm lớp cha, interface, controller/service hoặc quan hệ kế thừa không có trong mã.
- Với use case, kiểm tra quyền từ middleware/controller; phân biệt khách chưa đăng nhập, client và admin theo hành vi đã xác minh. Với sequence, đánh dấu `alt`/`opt` theo nhánh thật thay vì chỉ vẽ luồng thành công.
- Phân biệt mô hình phân tích nghiệp vụ với class diagram phản ánh triển khai; đặt nhãn rõ nếu người dùng yêu cầu mô hình đề xuất.

## Tạo ERD

- Đọc DDL trong `database/foursmart-demo.sql`, đối chiếu truy vấn model và migration liên quan. Đây là schema demo trong repository, không mặc nhiên là schema database đang chạy. File migration có hậu tố `proposed` không chứng minh đã được áp dụng.
- Snapshot khi tạo Skill có 14 bảng: `role`, `account`, `category`, `product`, `productcolor`, `productmemory`, `cart`, `cartdetail`, `order`, `orderdetail`, `comment`, `password_reset_tokens`, `password_reset_jobs`, `password_reset_rate_limits`. Đếm lại nếu cần báo cáo số lượng; không áp dụng migration recovery lên SQL demo đã chứa schema recovery.
- Vẽ bằng Mermaid `erDiagram` hoặc định dạng người dùng yêu cầu; thể hiện PK, FK, khóa ghép, UNIQUE, kiểu dữ liệu và tính nullable liên quan. Suy ra cardinality từ FK, UNIQUE/PK và NULL; không ép quan hệ một-một theo kỳ vọng nghiệp vụ.
- Ví dụ cần chú ý: `cart.id_tk` không có UNIQUE trong snapshot; `password_reset_tokens.account_id` vừa là PK vừa là FK; jobs và rate limits không có FK tới account. Không nối bảng chỉ vì tên cột gợi ý liên hệ.
- `productcolor` và `productmemory` liên kết product; các cột lựa chọn trong cartdetail/orderdetail không tự trở thành FK đến hai bảng biến thể này. Nếu mô tả liên hệ logic từ mã, ghi rõ khác với FK được khai báo.
- Kèm từ điển dữ liệu hoặc bảng quan hệ khi được yêu cầu; ghi riêng CHECK, ON DELETE/UPDATE và các giới hạn biểu diễn của sơ đồ.

## Viết báo cáo và bàn giao

- Điều chỉnh bố cục theo yêu cầu báo cáo: mục tiêu/phạm vi, công nghệ và kiến trúc, tác nhân/chức năng, luồng xử lý, thiết kế dữ liệu, sơ đồ, kiểm thử và hạn chế/đề xuất. Chỉ đưa các phần phù hợp nhiệm vụ.
- Liên kết hình/sơ đồ với chức năng và nguồn triển khai. Không bịa số liệu hiệu năng, độ bao phủ, kết quả kiểm thử, ảnh giao diện hoặc trạng thái triển khai. Có tệp test không có nghĩa test đã chạy hoặc đã đạt.
- Kiểm tra tên, chữ ký phương thức, route, bảng/cột và quan hệ giữa báo cáo với sơ đồ. Nếu có công cụ render sẵn, dùng để kiểm tra cú pháp sơ đồ; nếu chưa render, nói rõ mức kiểm tra đã thực hiện.
- Trước khi kết thúc, kiểm tra lại nhánh và diff; bảo đảm chỉ tệp thuộc phạm vi được thay đổi. Thông báo danh sách tệp đã tạo, tệp đã sửa, kiểm tra đã thực hiện và phần chưa xác minh. Không tự commit, push hoặc merge.
