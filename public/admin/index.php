<?php
require dirname(__DIR__) . '/bootstrap.php';
if (class_exists('Auth')) {
    Auth::requireAdmin('../login.php');
}
require __DIR__ . '/admin_app.full.php';
