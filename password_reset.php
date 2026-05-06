<?php
session_start();
require_once 'db.php';

$message = "";
$error = "";
$step = isset($_GET['step']) ? $_GET['step'] : 1;

// Step 1: Username entry
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step == 1) {
    $username = trim($_POST['username']);
    
    if (empty($username)) {
        $error = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Please enter your username.</div>";
    } else {
        $stmt = $pdo->prepare("SELECT user_id, security_question FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Username not found.</div>";
        } else {
            if (empty($user['security_question'])) {
                $error = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Security question not set. Please contact admin.</div>";
            } else {
                $_SESSION['reset_username'] = $username;
                $_SESSION['reset_user_id'] = $user['user_id'];
                $_SESSION['reset_question'] = $user['security_question'];
                header("Location: password_reset.php?step=2");
                exit;
            }
        }
    }
}

// Step 2: Security question answer
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step == 2) {
    $answer = trim($_POST['security_answer']);
    
    if (empty($answer)) {
        $error = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Please answer the security question.</div>";
    } else {
        $user_id = $_SESSION['reset_user_id'] ?? null;
        if (!$user_id) {
            $error = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Session expired. Please start over.</div>";
        } else {
            $stmt = $pdo->prepare("SELECT security_answer FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            $hashed_answer = hash('sha256', strtolower($answer));
            
            if ($user['security_answer'] !== $hashed_answer) {
                $error = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Incorrect answer. Please try again.</div>";
            } else {
                header("Location: password_reset.php?step=3");
                exit;
            }
        }
    }
}

// Step 3: New password entry
if ($_SERVER["REQUEST_METHOD"] == "POST" && $step == 3) {
    $password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    if (empty($password)) $errors[] = "Password is required";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters";
    
    if (!empty($errors)) {
        $error = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> " . implode("<br>", $errors) . "</div>";
    } else {
        $user_id = $_SESSION['reset_user_id'] ?? null;
        if (!$user_id) {
            $error = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Session expired. Please start over.</div>";
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                // Clear session
                unset($_SESSION['reset_username']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_question']);
                
                $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Password reset successfully! <a href='login.php'>Return to login</a></div>";
                $step = 'complete';
            } catch (Exception $e) {
                $error = "<div class='alert alert-error'><i class='fas fa-exclamation-circle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// Check session for step navigation
if ($step == 2 && !isset($_SESSION['reset_user_id'])) {
    header("Location: password_reset.php?step=1");
    exit;
}
if ($step == 3 && !isset($_SESSION['reset_user_id'])) {
    header("Location: password_reset.php?step=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Reset - Bright Horizon</title>
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
        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
        }
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-section h1 {
            color: #002347;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        .logo-section p {
            color: #666;
            font-size: 0.9rem;
        }
        .progress-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
        }
        .step-number {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            border-radius: 50%;
            background: #ddd;
            color: white;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .step.active .step-number {
            background: #002347;
        }
        .step.completed .step-number {
            background: #27ae60;
        }
        .step p {
            font-size: 0.85rem;
            color: #666;
            margin: 0;
        }
        .step.active p {
            color: #002347;
            font-weight: 600;
        }
        .step-connector {
            flex: 1;
            margin-top: 19px;
            border-top: 2px solid #ddd;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #002347;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #f39c12;
            box-shadow: 0 0 5px rgba(243, 156, 18, 0.3);
        }
        textarea {
            resize: none;
            min-height: 60px;
        }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-primary {
            background: #002347;
            color: white;
        }
        .btn-primary:hover {
            background: #f39c12;
            color: #002347;
        }
        .btn-secondary {
            background: #ddd;
            color: #333;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            background: #ccc;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #28a745;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-color: #dc3545;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #002347;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        .back-link a:hover {
            color: #f39c12;
        }
        .security-question {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 600;
            color: #002347;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo-section">
            <h1><i class="fas fa-lock"></i> Password Reset</h1>
            <p>Bright Horizon Primary School</p>
        </div>

        <?php if ($message): echo $message; endif; ?>
        <?php if ($error): echo $error; endif; ?>

        <?php if ($step == 1): ?>
            <!-- Step 1: Username Entry -->
            <div class="progress-steps">
                <div class="step active">
                    <div class="step-number">1</div>
                    <p>Username</p>
                </div>
                <div class="step-connector"></div>
                <div class="step">
                    <div class="step-number">2</div>
                    <p>Verify</p>
                </div>
                <div class="step-connector"></div>
                <div class="step">
                    <div class="step-number">3</div>
                    <p>New Password</p>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> Username *</label>
                    <input type="text" id="username" name="username" required placeholder="Enter your username">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Next Step</button>
                <div class="back-link">
                    <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                </div>
            </form>

        <?php elseif ($step == 2): ?>
            <!-- Step 2: Security Question -->
            <div class="progress-steps">
                <div class="step completed">
                    <div class="step-number"><i class="fas fa-check"></i></div>
                    <p>Username</p>
                </div>
                <div class="step-connector"></div>
                <div class="step active">
                    <div class="step-number">2</div>
                    <p>Verify</p>
                </div>
                <div class="step-connector"></div>
                <div class="step">
                    <div class="step-number">3</div>
                    <p>New Password</p>
                </div>
            </div>

            <form method="POST">
                <div class="security-question">
                    <i class="fas fa-question-circle"></i> <?php echo htmlspecialchars($_SESSION['reset_question'] ?? ''); ?>
                </div>
                
                <div class="form-group">
                    <label for="security_answer"><i class="fas fa-shield-alt"></i> Your Answer *</label>
                    <input type="text" id="security_answer" name="security_answer" required placeholder="Enter your answer">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Verify Answer</button>
                <button type="button" onclick="window.location='password_reset.php?step=1'" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Start Over</button>
            </form>

        <?php elseif ($step == 3): ?>
            <!-- Step 3: New Password -->
            <div class="progress-steps">
                <div class="step completed">
                    <div class="step-number"><i class="fas fa-check"></i></div>
                    <p>Username</p>
                </div>
                <div class="step-connector"></div>
                <div class="step completed">
                    <div class="step-number"><i class="fas fa-check"></i></div>
                    <p>Verify</p>
                </div>
                <div class="step-connector"></div>
                <div class="step active">
                    <div class="step-number">3</div>
                    <p>New Password</p>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="new_password"><i class="fas fa-key"></i> New Password *</label>
                    <input type="password" id="new_password" name="new_password" required placeholder="Enter new password (min 6 characters)">
                </div>
                <div class="form-group">
                    <label for="confirm_password"><i class="fas fa-key"></i> Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm new password">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Reset Password</button>
                <button type="button" onclick="window.location='login.php'" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</button>
            </form>

        <?php endif; ?>
    </div>
</body>
</html>
