<header class="client-header">

    <div class="container client-header-inner">

        <a
            class="client-logo"
            href="?client=home"
        >
            <span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
            <span class="brand-word">Four<span>Smart</span></span>
        </a>

        <form
            class="client-search"
            role="search"
            aria-label="Tìm kiếm sản phẩm"
            method="GET"
            action=""
        >

            <input
                type="hidden"
                name="client"
                value="productSearch"
            >

            <label class="sr-only" for="site-search">Tìm điện thoại</label>
            <input
                type="text"
                id="site-search"
                name="q"
                placeholder="Bạn đang tìm điện thoại nào?"
                value="<?= htmlspecialchars(
                    $_GET['q'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <button type="submit">
                Tìm kiếm
            </button>

        </form>

        <nav class="client-nav" aria-label="Điều hướng chính">

            <a href="?client=home">
                Trang chủ
            </a>

            <a class="nav-cart" href="?client=cart">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3h2l2.4 12h10.9l2-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                Giỏ hàng
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>

                <a href="?client=orderHistory">
                    Đơn hàng
                </a>

                <a href="?client=profile">
                    Tài khoản
                </a>

                <?php if (
                    (int) ($_SESSION['role_id'] ?? 0)
                    === 1
                ): ?>

                    <a href="?act=admin">
                        Quản trị
                    </a>

                <?php endif; ?>

                <a href="?client=logout">
                    Đăng xuất
                </a>

            <?php else: ?>

                <a href="?client=login">
                    Đăng nhập
                </a>

                <a class="nav-register" href="?client=register">
                    Đăng ký
                </a>

            <?php endif; ?>

        </nav>

    </div>

</header>
