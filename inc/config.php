<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'zaca06');
define('DB_USER', 'zaca06');
define('DB_PASS', 'aemohmaseiXaevu4vu');

spl_autoload_register(function ($class_name) {
    $file = __DIR__ . '/../classes/' . $class_name . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
?>