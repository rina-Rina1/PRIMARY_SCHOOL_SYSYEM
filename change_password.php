<?php
session_start();

// 1. SECURITY: If the user isn't logged in, kick them out to the login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';

$message = "";

// 3. PROCESS THE FORM DATA
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_password = $_POST['current_password'];
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic Checks
    if ($new_password !== $confirm_password) {
        $message = "New passwords do not match!";
    } elseif (strlen($new_password) < 6) {
        $message = "New password is too short (min 6 chars).";
    } else {
        // Find the user in the database
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();

        // Verify if the 'Current Password' they typed matches the hash in the DB
        if ($userData && password_verify($current_password, $userData['password_hash'])) {
            
            // Success! Hash the NEW password
            $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update the database
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $update->execute([$new_hashed_password, $_SESSION['user_id']]);
            
            $message = "Password updated successfully!";
        } else {
            $message = "The current password you entered is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; display: flex; justify-content: center; margin-top: 50px; }
        .box { background: white; padding: 25px; border-radius: 5px; box-shadow: 0px 0px 10px rgba(0,0,0,0.1); width: 320px; }
        input { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .error { color: red; font-size: 14px; }
        .success { color: green; font-size: 14px; }
    </style>
</head>
<body>

<div class="box">
    <h2>Change Password</h2>

    <?php if ($message): ?>
        <p class="<?php echo (strpos($message, 'successfully') !== false) ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <form method="POST">
        <label>Current Password</label>
        <input type="password" name="current_password" required>

        <label>New Password</label>
        <input type="password" name="new_password" required>

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" required>

        <button type="submit">Update Password</button>
    </form>
    <br>
    <a href="dashboard.php">Back to Dashboard</a>
</div>

</body>
</html>