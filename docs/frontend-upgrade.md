# FourSmart — nâng cấp giao diện

Ngày thực hiện: 02/11/2026. Nhánh: `feature/foursmart-skill`.

## Khảo sát và phạm vi

Áp dụng `.agents/skills/senior-frontend/SKILL.md` và tài liệu best practices đi kèm theo công nghệ thực tế: PHP MVC, CSS, JavaScript thuần. Không sử dụng các scaffold React/Next vì không phù hợp kiến trúc hiện tại.

Đã đọc các View, partial, 7 stylesheet hiện có, JavaScript mật khẩu và router client/admin để xác định các trang thực sự tồn tại. Website có 26 tài liệu HTML: 13 trang client và 13 trang admin.

Những điểm cần cải thiện từ mã nguồn trước chỉnh sửa:

- Màu sắc, bo góc và shadow khai báo rải rác; component thiếu hệ thống chung.
- Banner trang chủ chỉ có tên thương hiệu và một dòng mô tả; phân cấp nội dung còn đơn giản.
- Card sản phẩm trên mobile chuyển thành một cột rộng; bố cục thiếu tính cô đọng.
- Các trang đăng nhập, đăng ký, hồ sơ và nhiều trang admin thiếu viewport.
- Form có label chưa liên kết với input; thiếu trạng thái focus thống nhất và phản hồi chờ xử lý chung.
- Tổng tiền giỏ hàng sticky ở `top: 20px`, có thể nằm sau header sticky.
- Sidebar admin chưa đánh dấu khu vực đang mở. Ảnh sản phẩm lỗi không có trạng thái dự phòng.

Danh mục/tìm kiếm hiện chưa triển khai bộ lọc hoặc sắp xếp. Không thêm nghiệp vụ này trong lần nâng cấp giao diện.

## Hệ thống thiết kế và thứ tự triển khai

1. Thành phần dùng chung: token màu, typography, button, form, card, table, header, footer, sidebar.
2. Trang chủ, danh mục, tìm kiếm và chi tiết sản phẩm.
3. Giỏ hàng, thanh toán, tài khoản và đơn hàng.
4. Dashboard, bảng và form quản trị; kiểm tra các kích thước màn hình.

| Thành phần | Quyết định |
| --- | --- |
| Màu chính | Cobalt `#2457df`, hover `#1941b5` |
| Chữ/nền | Ink `#14213d`, muted `#59677d`, canvas `#f5f7fc`, surface trắng |
| Màu trạng thái | Đỏ cho xóa/lỗi, xanh lá cho thành công; giữ nội dung trạng thái bằng chữ |
| Typography | Segoe UI/system-ui, hỗ trợ tiếng Việt; không tải font bên ngoài |
| Spacing | Chủ yếu 8/12/16/20/24/28/32/40/48px |
| Bố cục | Container tối đa 1200px; grid co giãn; mobile 2 cột sản phẩm, dưới 360px về 1 cột |
| Component | Bo góc 11px cho control, 20px cho panel; viền nhẹ và shadow mềm |
| Tương tác | Hover/active/focus-visible; loading trực quan; hỗ trợ prefers-reduced-motion |

`theme.css` được nạp sau CSS hiện có trên tất cả 26 trang. Đây là lớp thiết kế tập trung, giữ stylesheet và class cũ để hạn chế ảnh hưởng các phần phụ thuộc. Banner dùng hình minh họa CSS, không thêm thư viện hoặc tài nguyên ảnh ngoài.

`interface.js` bổ sung menu active theo URL, liên kết label chưa có `for` với control liền kề, lazy-load ảnh trong card, ảnh dự phòng và phản hồi submit. Không thay đổi payload form, không vô hiệu hóa submitter chung và không chặn submit. Script mật khẩu/khôi phục hiện có được giữ nguyên.

## Các file đã thay đổi

Đường dẫn dưới đây tính từ gốc repository:

| Nhóm | File |
| --- | --- |
| Hệ thống thiết kế mới | `mvc/view/assets/css/theme.css`, `mvc/view/assets/js/interface.js` |
| Partial | `mvc/view/client/partials/header.php`, `mvc/view/admin/partials/sidebar.php` |
| Trang chủ/sản phẩm | `mvc/view/client/home.php`, `mvc/view/client/category.php`, `mvc/view/client/productSearch.php`, `mvc/view/client/productDetail.php` |
| Giỏ hàng/thanh toán | `mvc/view/client/cart.php`, `mvc/view/client/pay.php` |
| Tài khoản | `mvc/view/client/login/login.php`, `mvc/view/client/login/register.php`, `mvc/view/client/login/forgot_password.php`, `mvc/view/client/login/reset_password.php`, `mvc/view/client/profile/profile.php` |
| Đơn hàng client | `mvc/view/client/order/list.php`, `mvc/view/client/order/detail.php` |
| Dashboard | `mvc/view/admin/home.php` |
| Sản phẩm admin | `mvc/view/admin/product/list.php`, `mvc/view/admin/product/add.php`, `mvc/view/admin/product/update.php`, `mvc/view/admin/product/variant.php` |
| Danh mục admin | `mvc/view/admin/category/list.php`, `mvc/view/admin/category/add.php`, `mvc/view/admin/category/update.php` |
| Đơn hàng admin | `mvc/view/admin/order/list.php`, `mvc/view/admin/order/detail.php` |
| Tài khoản/bình luận admin | `mvc/view/admin/account/list.php`, `mvc/view/admin/account/update.php`, `mvc/view/admin/comment/list.php` |
| Báo cáo | `docs/frontend-upgrade.md` |

Phần lớn View chỉ thêm link theme/script và viewport khi còn thiếu. Footer được nâng cấp qua selector hiện có, không cần sửa template. Controller, Model, database, route và logic xác thực/CSRF không thay đổi. Các thuộc tính `name`, `id`, `action` có sẵn được đối chiếu với HEAD và vẫn còn nguyên. Thư mục `docs/uml/` có sẵn của người dùng được giữ nguyên. Không commit, push hoặc merge.

## Kiểm tra và kết quả

- `php -l` trên toàn bộ View: đạt.
- `node --check` với JavaScript giao diện mới: đạt, sử dụng Node đi kèm công cụ Playwright ngoài repository.
- Bộ kiểm tra hiện có `mvc/tests/client_password_recovery_js_test.js`: 5/5 đạt (mô phỏng DOM).
- `git diff --check`: đạt.
- Chrome headless qua Playwright ở **1440×1000, 768×1024, 390×844**: 102 lượt kiểm tra trang, không phát hiện tràn ngang toàn trang.
- Trong đó 24 lượt truy cập 8 URL localhost: trang chủ, đăng nhập, đăng ký, giỏ hàng, tìm kiếm có/rỗng từ khóa, một trang sản phẩm và một danh mục. Giỏ hàng khi chưa đăng nhập trả 401 theo hành vi hiện tại.
- 78 lượt kiểm tra render của cả 26 View với dữ liệu minh họa tách biệt, bao gồm trang cần đăng nhập, thanh toán và toàn bộ admin. Đây là kiểm tra hiển thị và JavaScript của template, không phải kiểm thử tích hợp Controller/database.
- Tìm kiếm bằng form GET trên localhost: đạt, đến URL tìm kiếm hiện có.
- Kiểm tra bổ sung: liên kết label, Tab giữa các ô đăng nhập, active menu, bảo toàn trường CSRF và name/value submitter qua POST được chặn bởi bộ kiểm tra, reduced-motion và ảnh dự phòng. Không gửi POST thử nghiệm đến backend thực.
- Không phát hiện exception JavaScript trong lượt kiểm tra bổ sung. Console vẫn có HTTP 404 của hai ảnh có sẵn bị thiếu và HTTP 401 của giỏ hàng khi chưa đăng nhập.

Ảnh chụp và kết quả thô được lưu ngoài repository tại `G:\Chapter8_Demo\frontend-review\`:

- `results.json`: 102 lượt kiểm tra hiển thị.
- `smoke-results.json`: kiểm tra bổ sung sau các sửa đổi cuối.
- `home-final-1440.png`, `home-final-768.png`, `home-final-390.png`, `login-final-390.png`.
- Các ảnh `admin-home.php-*.png`, `client-pay.php-*.png`, `client-cart.php-*.png`, `client-productDetail.php-*.png` dùng dữ liệu minh họa.

## Giới hạn và vấn đề còn lại

- Hai ảnh upload hiện không tồn tại: `product_58978f923295af54.jpg`, `product_4dbb3d5e045d19e5.jpg`. Đã hiển thị placeholder; cần bổ sung file ảnh hợp lệ qua luồng quản lý hiện có để hết HTTP 404.
- Chưa kiểm thử tích hợp đăng nhập thành công, đăng ký, mua hàng, thanh toán, gửi bình luận, đổi mật khẩu hoặc ghi dữ liệu quản trị. Không thể khẳng định toàn bộ các luồng này đã được kiểm chứng chỉ từ kiểm tra template.
- Chưa kiểm tra trên thiết bị vật lý, Safari hoặc Firefox. Các kết quả responsive ở trên là viewport Chrome mô phỏng.
- Email/hotline trong footer là nội dung có sẵn của dự án; cần dữ liệu liên hệ thực tế trước khi đưa vào sử dụng.
