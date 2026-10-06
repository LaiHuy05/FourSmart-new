<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../model/OrderModel.php';

echo '<pre>';

print_r(OrderModel::all());

echo '</pre>';
