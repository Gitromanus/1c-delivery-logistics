<?php
require __DIR__ . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::logout();
}
header('Location: login.php');
exit;
