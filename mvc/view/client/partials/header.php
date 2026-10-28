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
                placeholder="Tìm sản phẩm..."
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

        <nav class="client-nav">

            <a href="?client=home">
                Trang chủ
            </a>

            <a href="?client=cart">
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