<?php
session_start();
require_once('db.php'); // Pulls in the $pdo variable

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        // 1. Look for the user by username
        $stmt = $pdo->prepare("SELECT user_id, password_hash, role_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // 2. If user exists, check the password
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // 3. Success! Save user info into the Session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['username'] = $username;

            // 4. Send them to the correct dashboard based on their role
switch ($_SESSION['role_id']) {
    case 1:
        header("Location: dashboards/admin_dashboard.php");
        break;
    case 2:
        header("Location: dashboards/teacher_dashboard.php");
        break;
    case 3:
        header("Location: dashboards/parent_dashboard.php");
        break;
    default:
        // If the role is unknown, send them back to login or an error page
        $error_message = "Your account role is not recognized.";
        break;
        }
        exit();
        
        } else {
            $error_message = "Invalid username or password.";
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Bright Horizon</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: linear-gradient(135deg, #2042d8, #ecff43);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-card { 
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 400px;
        }
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-section i {
            font-size: 3rem;
            color: #002347;
            margin-bottom: 10px;
        }
        h2 {
            text-align: center;
            color: #002347;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        .subtitle {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #f39c12;
            box-shadow: 0 0 5px rgba(243, 156, 18, 0.3);
        }
        button {
            width: 100%;
            padding: 12px;
            background: #002347;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: 0.3s;
        }
        button:hover {
            background: #f39c12;
            color: #002347;
        }
        .error {
            color: #d93025;
            background: #fce8e6;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            border-left: 4px solid #d93025;
        }
        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }
        .forgot-password a {
            color: #002347;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        .forgot-password a:hover {
            color: #f39c12;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo-section">
        <i class="fas fa-graduation-cap"></i>
        <h2>Bright Horizon</h2>
        <p class="subtitle">Primary School Management System</p>
    </div>

    <?php if ($error_message): ?>
        <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="username"><i class="fas fa-user"></i> Username</label>
            <input type="text" id="username" name="username" required placeholder="Enter your username">
        </div>

        <div class="form-group">
            <label for="password"><i class="fas fa-lock"></i> Password</label>
            <input type="password" id="password" name="password" required placeholder="Enter your password">
        </div>

        <button type="submit"><i class="fas fa-sign-in-alt"></i> Login</button>
    </form>

    <div class="forgot-password">
        <a href="password_reset.php"><i class="fas fa-lock"></i> Forgot Password?</a>
    </div>
</div>

</body>
</html>