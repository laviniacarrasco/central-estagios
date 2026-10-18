<?php
require 'vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();
foreach (['GMAIL_CLIENT_ID','GMAIL_CLIENT_SECRET','GMAIL_REFRESH_TOKEN'] as $k) {
    echo $k . ': ' . (empty($_ENV[$k]) ? 'VAZIO' : 'preenchido (' . strlen($_ENV[$k]) . ' caracteres)')
       . ' | getenv: ' . (getenv($k) ? 'preenchido' : 'VAZIO') . PHP_EOL;
}
