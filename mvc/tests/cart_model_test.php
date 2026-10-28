<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../model/CartModel.php';

echo '<pre>';

print_r(CartModel::all());

echo '</pre>';