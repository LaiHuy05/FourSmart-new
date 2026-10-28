<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../model/CategoryModel.php';

echo '<pre>';

print_r(CategoryModel::all());

echo '</pre>';
