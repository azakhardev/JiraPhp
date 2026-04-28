<?php
require_once 'inc/config.php';

$_SESSION = [];

session_destroy();

header('Location: login.php');
exit();
?>