<?php
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$auth->logout();

header('Location: ../index.php?page=login');
exit();
?>
