<?php
require_once __DIR__ . '/bootstrap.php';
session_destroy();
header('Location: ' . APP_URL . '/login.php');
exit;
