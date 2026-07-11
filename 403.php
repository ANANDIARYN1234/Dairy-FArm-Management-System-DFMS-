<?php
/**
 * 403.php - Access Denied Page
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
    <style>
        .error-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--bg-primary);
        }
        .error-content {
            text-align: center;
            max-width: 500px;
            padding: 2rem;
        }
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            color: var(--danger);
            line-height: 1;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 2rem;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }
        .error-message {
            color: var(--text-medium);
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-content">
            <div class="error-code">403</div>
            <h1 class="error-title">Access Denied</h1>
            <p class="error-message">
                You don't have permission to access this page.
            </p>
            <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
            <a href="index.php" class="btn btn-secondary">Go Home</a>
        </div>
    </div>
</body>
</html>

<?php
/**
 * Save this file as 403.php
 * 
 * For 404.php, create a similar file with:
 * - Error code: 404
 * - Title: Page Not Found
 * - Message: The page you're looking for doesn't exist.
 */
?>