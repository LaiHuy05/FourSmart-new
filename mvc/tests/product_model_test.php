<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../model/ProductModel.php';

echo '<pre>';

print_r(ProductModel::all());

echo '</pre>';