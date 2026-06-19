<?php
/**
 * Admin Logout
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
session_start();

Auth::logout();
header('Location: login.php');
exit;
