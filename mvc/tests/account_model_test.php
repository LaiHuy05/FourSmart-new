<?php

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../model/AccountModel.php';

echo '<pre>';

print_r(AccountModel::all());

echo '</pre>';