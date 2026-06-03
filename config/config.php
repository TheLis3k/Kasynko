<?php

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '5432',
        'name' => getenv('DB_NAME') ?: 'kasynko',
        'user' => getenv('DB_USER') ?: 'kasynko',
        'pass' => getenv('DB_PASS') ?: 'secret',
    ],
    'app' => [
        'env'          => getenv('APP_ENV') ?: 'production',
        'default_lang' => 'pl',
        'upload_dir'   => dirname(__DIR__) . '/storage/uploads',
        'max_upload'   => 5 * 1024 * 1024,
    ],
];
