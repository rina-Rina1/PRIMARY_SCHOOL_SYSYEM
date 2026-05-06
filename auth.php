<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    $loginUrl = '/bright_horizon_primary_school/login.php';
    header("Location: $loginUrl");
    exit();
}

/**
 * 2. Role Security Function
 * This checks the session's role_id against the required role for the page.
 */
function restrictTo($allowed_role) {
    // If the session role isn't set, or doesn't match, block access
    if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != $allowed_role) {
        // You can make this look nicer with a link back to the correct dashboard
        echo "<h2>Access Denied</h2>";
        echo "<p>You do not have permission to view this page.</p>";
        echo "<a href='../login.php'>Return to Login</a>";
        exit();
    }
}
?>