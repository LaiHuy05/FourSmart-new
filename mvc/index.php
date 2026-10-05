<?php

require_once __DIR__ . '/core/bootstrap.php';

$act = $_GET['act'] ?? 'client';

if ($act === 'admin') {
    require __DIR__ . '/controller/admin/admin_controller.php';
} else {
    require __DIR__ . '/controller/client/client_controller.php';
}