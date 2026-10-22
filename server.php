<?php
// Development router, run with: php -S 127.0.0.1:8000 -t public server.php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path!=='/' && is_file(__DIR__.'/public'.$path)) return false;
require __DIR__.'/public/index.php';
