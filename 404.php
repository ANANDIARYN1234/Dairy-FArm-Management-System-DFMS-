<?php
/**
 * =========================================================
 * 403.php - Access Denied Page
 * =========================================================
 */
session_start();
define('DFMS_EXEC', true);
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Denied</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="error-page">
        <div class="error-content">
            <div class="error-code">403</div>
            <h1 class="error-title">Access Denied</h1>
            <p class="error-message">
                You don't have permission to access this page.
            </p>
            <div>
                <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
                <a href="index.php" class="btn btn-primary">Go Home</a>
            </div>
        </div>
    </div>
</body>
</html>

<?php
/**
 * =========================================================
 * 404.php - Page Not Found
 * =========================================================
 * Save as separate file: 404.php
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="error-page">
        <div class="error-content">
            <div class="error-code">404</div>
            <h1 class="error-title">Page Not Found</h1>
            <p class="error-message">
                The page you're looking for doesn't exist or has been moved.
            </p>
            <div>
                <a href="javascript:history.back()" class="btn btn-secondary">Go Back</a>
                <a href="index.php" class="btn btn-primary">Go Home</a>
            </div>
        </div>
    </div>
</body>
</html>