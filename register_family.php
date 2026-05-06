<?php 
include "../auth.php"; 
require_once "../db.php"; 
restrictTo(1); 

$message = "";
$error = "";

// Handle Family Registration
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'register_family') {
    $parent_first_name = trim($_POST['parent_first_name']);
    $parent_last_name = trim($_POST['parent_last_name']);
    $parent_phone = trim($_POST['parent_phone']);
    $parent_email = trim($_POST['parent_email']);
    $parent_username = trim($_POST['parent_username']);
    $parent_password = $_POST['parent_password'];
    $parent_confirm_password = $_POST['parent_confirm_password'];
    $parent_security_question = $_POST['parent_security_question'];
    $parent_security_answer = trim($_POST['parent_security_answer']);

    $student_first_name = trim($_POST['student_first_name']);
    $student_last_name = trim($_POST['student_last_name']);
    $student_gender = $_POST['student_gender'];
    $student_dob = $_POST['student_dob'];
    $student_phone = trim($_POST['student_phone']);
    $student_class = $_POST['student_class'];
    $student_admission_no = trim($_POST['student_admission_no']);

    $valid = true;
    $errors = [];

    // Validation
    if (empty($parent_first_name) || empty($parent_last_name)) {
        $errors[] = "Parent name is required.";
        $valid = false;
    }
    if (empty($parent_username)) {
        $errors[] = "Parent username is required.";
        $valid = false;
    }
    if (empty($parent_password) || empty($parent_confirm_password)) {
        $errors[] = "Parent password is required.";
        $valid = false;
    }
    if ($parent_password !== $parent_confirm_password) {
        $errors[] = "Passwords do not match.";
        $valid = false;
    }
    if (strlen($parent_password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
        $valid = false;
    }
    if (empty($parent_phone)) {
        $errors[] = "Parent phone number is required.";
        $valid = false;
    }
    if (empty($parent_security_answer)) {
        $errors[] = "Security answer is required.";
        $valid = false;
    }
    if (empty($student_first_name) || empty($student_last_name)) {
        $errors[] = "Student name is required.";
        $valid = false;
    }
    if (empty($student_phone)) {
        $errors[] = "Student phone number is required.";
        $valid = false;
    }

    if (!$valid) {
        $error = "<div class='alert error'><strong>Errors:</strong><br>" . implode("<br>", $errors) . "</div>";
    } else {
        try {
            $pdo->beginTransaction();

            // Create parent user
            $hashed_password = password_hash($parent_password, PASSWORD_DEFAULT);
            $hashed_answer = hash('sha256', strtolower($parent_security_answer));
            
            $p_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role_id, security_question, security_answer) VALUES (?, ?, 3, ?, ?)");
            $p_stmt->execute([$parent_username, $hashed_password, $parent_security_question, $hashed_answer]);
            $parent_user_id = $pdo->lastInsertId();

            // Create parent record
            $parent_stmt = $pdo->prepare("INSERT INTO parents (user_id, first_name, last_name, email, phone) VALUES (?, ?, ?, ?, ?)");
            $parent_stmt->execute([$parent_user_id, $parent_first_name, $parent_last_name, $parent_email, $parent_phone]);
            $parent_id = $pdo->lastInsertId();

            // Create student user
            $student_username = strtolower($student_first_name . $student_last_name . rand(1000, 9999));
            $temp_password = 'Student@' . date('Y');
            $hashed_student_password = password_hash($temp_password, PASSWORD_DEFAULT);

            $s_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role_id, parent_id, security_question, security_answer) VALUES (?, ?, 3, ?, ?, ?)");
            $s_stmt->execute([$student_username, $hashed_student_password, $parent_user_id, $parent_security_question, $hashed_answer]);
            $student_user_id = $pdo->lastInsertId();

            // Create student record
            $student_create_stmt = $pdo->prepare("INSERT INTO students (user_id, class_id, admission_no, first_name, last_name, gender, dob, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $student_create_stmt->execute([$student_user_id, $student_class, $student_admission_no, $student_first_name, $student_last_name, $student_gender, $student_dob, $student_phone]);
            $student_id = $pdo->lastInsertId();

            // Link student to parent
            $link_stmt = $pdo->prepare("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES (?, ?, 'Father')");
            $link_stmt->execute([$student_id, $parent_id]);

            $pdo->commit();
            $message = "<div class='alert success'><strong>Success!</strong> Family registered successfully! Parent username: <strong>" . htmlspecialchars($parent_username) . "</strong></div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "<div class='alert error'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Fetch classes for dropdown
$classes = $pdo->query("SELECT class_id, class_name FROM classes ORDER BY class_id ASC")->fetchAll();
$security_questions = [
    "What is your mother's maiden name?",
    "What was the name of your first pet?",
    "In what city were you born?",
    "What is your favorite movie?",
    "What was the name of your primary school?",
    "What is your favorite sports team?",
    "What was your childhood nickname?"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register Family - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .form-container { max-width: 900px; margin: 0 auto; background: white; padding: 40px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 40px; border-bottom: 2px solid #f0f0f0; padding-bottom: 30px; }
        .form-section h3 { color: var(--primary-dark); margin-top: 0; display: flex; align-items: center; gap: 10px; }
        .form-section h3 i { color: var(--accent-color); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px; }
        .form-row.full { grid-template-columns: 1fr; }
        .form-group { display: flex; flex-direction: column; }
        label { font-weight: 600; color: var(--primary-dark); margin-bottom: 8px; font-size: 0.95rem; }
        input, select, textarea { padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; transition: border-color 0.3s; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent-color); box-shadow: 0 0 5px rgba(243, 156, 18, 0.2); }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid; }
        .alert.success { background: #d4edda; color: #155724; border-color: #28a745; }
        .alert.error { background: #f8d7da; color: #721c24; border-color: #dc3545; }
        .btn-group { display: flex; gap: 10px; justify-content: flex-end; margin-top: 40px; }
        .btn { padding: 12px 30px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; transition: 0.3s; }
        .btn-primary { background: var(--primary-dark); color: white; }
        .btn-primary:hover { background: var(--accent-color); color: var(--primary-dark); }
        .btn-secondary { background: #ddd; color: var(--primary-dark); }
        .btn-secondary:hover { background: #ccc; }
        .required { color: #e74c3c; }
        .help-text { font-size: 0.85rem; color: #666; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="text-align: center; margin-bottom: 30px;">
             <i class="fas fa-university fa-3x" style="color: var(--accent-color);"></i>
             <h2 style="margin-top: 10px;">Bright Horizon</h2>
        </div>
        <ul>
            <li><a href="admin_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="register_family.php" class="active"><i class="fas fa-user-plus"></i> Register Family</a></li>
            <li><a href="crud_students/view_students.php"><i class="fas fa-user-graduate"></i> View Students</a></li>
            <li><a href="admin_dashboard.php?role=2"><i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
            <li><a href="teacher_assignments.php"><i class="fas fa-link"></i> Assign Teachers</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="main-content" style="margin-left: 260px; padding: 30px;">
        <div class="header-bar">
            <div>
                <h1><i class="fas fa-user-plus"></i> Register New Family</h1>
                <p style="color: #666;">Create parent and student accounts with complete information</p>
            </div>
        </div>

        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <?php echo $error; ?>
        <?php endif; ?>

        <form method="POST" class="form-container">
            <input type="hidden" name="action" value="register_family">

            <!-- Parent Information -->
            <div class="form-section">
                <h3><i class="fas fa-user"></i> Parent/Guardian Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="parent_first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="parent_first_name" name="parent_first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="parent_last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="parent_last_name" name="parent_last_name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="parent_email">Email Address</label>
                        <input type="email" id="parent_email" name="parent_email">
                    </div>
                    <div class="form-group">
                        <label for="parent_phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="parent_phone" name="parent_phone" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="parent_username">Username <span class="required">*</span></label>
                        <input type="text" id="parent_username" name="parent_username" required>
                        <div class="help-text">This will be used to login</div>
                    </div>
                    <div class="form-group">
                        <label for="parent_password">Password <span class="required">*</span></label>
                        <input type="password" id="parent_password" name="parent_password" required>
                        <div class="help-text">Minimum 6 characters</div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="parent_confirm_password">Confirm Password <span class="required">*</span></label>
                        <input type="password" id="parent_confirm_password" name="parent_confirm_password" required>
                    </div>
                    <div class="form-group">
                        <label for="parent_security_question">Security Question <span class="required">*</span></label>
                        <select id="parent_security_question" name="parent_security_question" required>
                            <option value="">-- Select a security question --</option>
                            <?php foreach ($security_questions as $q): ?>
                                <option value="<?php echo htmlspecialchars($q); ?>"><?php echo htmlspecialchars($q); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label for="parent_security_answer">Security Answer <span class="required">*</span></label>
                        <input type="text" id="parent_security_answer" name="parent_security_answer" required>
                        <div class="help-text">Answer to your security question (used for password recovery)</div>
                    </div>
                </div>
            </div>

            <!-- Student Information -->
            <div class="form-section">
                <h3><i class="fas fa-graduation-cap"></i> Student Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="student_first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="student_first_name" name="student_first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="student_last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="student_last_name" name="student_last_name" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="student_gender">Gender <span class="required">*</span></label>
                        <select id="student_gender" name="student_gender" required>
                            <option value="">-- Select Gender --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="student_dob">Date of Birth <span class="required">*</span></label>
                        <input type="date" id="student_dob" name="student_dob" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="student_phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" id="student_phone" name="student_phone" required>
                    </div>
                    <div class="form-group">
                        <label for="student_class">Class <span class="required">*</span></label>
                        <select id="student_class" name="student_class" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>"><?php echo htmlspecialchars($class['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="student_admission_no">Admission Number <span class="required">*</span></label>
                        <input type="text" id="student_admission_no" name="student_admission_no" required>
                        <div class="help-text">Unique identifier for the student</div>
                    </div>
                </div>
            </div>

            <div class="btn-group">
                <a href="admin_dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Register Family</button>
            </div>
        </form>
    </div>
</body>
</html>
