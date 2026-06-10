<?php
/**
 * SiteCatalogo - Logout
 */
require_once __DIR__ . '/../includes/functions.php';
session_check();

if (isset($_SESSION['admin_id'])) {
    log_activity('logout', 'auth', "Usuario {$_SESSION['admin_login']} saiu do sistema");
}

session_destroy();
header('Location: login.php');
exit;
