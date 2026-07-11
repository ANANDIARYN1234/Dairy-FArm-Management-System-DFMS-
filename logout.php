<?php
/**
 * =========================================================
 * DAIRY FARM MANAGEMENT SYSTEM (DFMS)
 * Logout Handler
 * =========================================================
 */

session_start();
define('DFMS_EXEC', true);
require_once 'includes/config.php';
require_once 'includes/auth.php';

logout_user();
?>