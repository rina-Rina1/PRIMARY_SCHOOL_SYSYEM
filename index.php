<?php
// Redirect to login if not already logged in
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: welcome.php');
} else {
    header('Location: login.php');
}
exit;
?>
