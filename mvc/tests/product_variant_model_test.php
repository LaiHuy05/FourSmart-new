<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../model/ProductVariantModel.php';

echo '<pre>';

echo "MÀU:\n";
print_r(ProductVariantModel::colors());

echo "\nBỘ NHỚ:\n";
print_r(ProductVariantModel::memories());

echo '</pre>';