<?php
//return [
//    'DB_HOST'=>getenv('DB_HOST'),
//    'DB_PORT'=>getenv('DB_PORT') ?: '3306',
//    'DB_USER'=>getenv('DB_USER'),
//    'DB_PASSWORD'=>getenv('DB_PASSWORD'),
//    'DB_NAME'=>getenv('DB_TEST_DATABASE_NAME') ?: 'objectiphy_test'
//];

return [
    'DB_HOST'=>'127.0.0.1',
    'DB_PORT'=>getenv('DB_PORT') ?: '3306',
    'DB_USER'=>'objectiphy',
    'DB_PASSWORD'=>'RandomDonkey!',
    'DB_NAME'=>getenv('DB_TEST_DATABASE_NAME') ?: 'objectiphy_test'
];