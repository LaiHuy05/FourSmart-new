<header class="client-header">

    <div class="container client-header-inner">

        <a
            class="client-logo"
            href="?client=home"
        >
            Four<span>Smart</span>
        </a>

        <form
            class="client-search"
            role="search"
            method="GET"
            action=""
        >

            <input
                type="hidden"
                name="client"
                value="productSearch"
            >

            <input
                type="text"
                name="q"
                aria-label="Tìm kiếm sản phẩm"
                placeholder="Tìm sản phẩm..."
                value="<?= htmlspecialchars(
                    $_GET['q'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <button type="submit">
                <svg class="fs-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>
                Tìm kiếm
            </button>

        </form>

        <nav class="client-nav" aria-label="Điều hướng chính">

            <a href="?client=home">
                Trang chủ
            </a>

            <a href="?client=cart">
                <svg class="fs-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h2l3 12h10l3-9H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
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

                <a href="?client=register">
                    Đăng ký
                </a>

            <?php endif; ?>

        </nav>

    </div>

</header>
