<?php

return [
    'ftp' => [
        'host' => env('FTP_HOST', 'ftp.example.com'),
        'username' => env('FTP_USERNAME', 'your-username'),
        'password' => env('FTP_PASSWORD', 'your-password'),
        'port' => env('FTP_PORT', 21),
        'root' => env('FTP_ROOT', '/'),
    ],
];
