<?php 
include "../auth.php"; 
require_once "../db.php"; 
restrictTo(1); 

$message = "";

// --- 1. LOGIC: REGISTER FAMILY ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_family'])) {
    $parent_first_name = trim($_POST['parent_first_name']);
    $parent_last_name = trim($_POST['parent_last_name']);
    $parent_phone = trim($_POST['parent_phone']);
    $parent_email = trim($_POST['parent_email']);
    $parent_username = trim($_POST['parent_username']);
    $parent_password = $_POST['parent_password'];
    $parent_confirm_password = $_POST['parent_confirm_password'];
    $parent_security_question = $_POST['parent_security_question'] ?? '';
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
    if (empty($student_class)) {
        $errors[] = "Student class is required.";
        $valid = false;
    }
    if (empty($student_admission_no)) {
        $errors[] = "Student admission number is required.";
        $valid = false;
    }

    if (!$valid) {
        $message = "<div class='alert error'>Error: " . implode("<br>", $errors) . "</div>";
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
            $message = "<div class='alert success'>Family successfully registered! Parent username: <strong>" . htmlspecialchars($parent_username) . "</strong><br>Student username: <strong>" . htmlspecialchars($student_username) . "</strong></div>";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert error'>System Error: " . $e->getMessage() . "</div>";
        }
    }
}

// --- 2. LOGIC: CREATE/EDIT/DELETE TEACHER ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['teacher_action'])) {
    $action = $_POST['teacher_action'];
    
    if ($action == 'create') {
        $first_name = trim($_POST['teacher_first_name']);
        $last_name = trim($_POST['teacher_last_name']);
        $email = trim($_POST['teacher_email']);
        $phone = trim($_POST['teacher_phone']);
        $username = trim($_POST['teacher_username']);
        $password = $_POST['teacher_password'];
        
        $valid = true;
        $errors = [];
        
        if (empty($first_name) || empty($last_name) || empty($username) || empty($password)) {
            $errors[] = "All fields are required.";
            $valid = false;
        }
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters.";
            $valid = false;
        }
        
        if ($valid) {
            try {
                $pdo->beginTransaction();
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Create user account
                $user_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role_id) VALUES (?, ?, 2)");
                $user_stmt->execute([$username, $hashed_password]);
                $user_id = $pdo->lastInsertId();
                
                // Create teacher record
                $teacher_stmt = $pdo->prepare("INSERT INTO teachers (user_id, first_name, last_name, email, phone) VALUES (?, ?, ?, ?, ?)");
                $teacher_stmt->execute([$user_id, $first_name, $last_name, $email, $phone]);
                
                $pdo->commit();
                $message = "<div class='alert success'>Teacher account created successfully! Username: <strong>" . htmlspecialchars($username) . "</strong></div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "<div class='alert error'>Error creating teacher: " . $e->getMessage() . "</div>";
            }
        } else {
            $message = "<div class='alert error'>Error: " . implode("<br>", $errors) . "</div>";
        }
    } elseif ($action == 'edit' && isset($_POST['teacher_id'])) {
        $teacher_id = (int)$_POST['teacher_id'];
        $first_name = trim($_POST['teacher_first_name']);
        $last_name = trim($_POST['teacher_last_name']);
        $email = trim($_POST['teacher_email']);
        $phone = trim($_POST['teacher_phone']);
        
        try {
            $stmt = $pdo->prepare("UPDATE teachers SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE teacher_id = ?");
            $stmt->execute([$first_name, $last_name, $email, $phone]);
            $message = "<div class='alert success'>Teacher information updated successfully!</div>";
        } catch (Exception $e) {
            $message = "<div class='alert error'>Error updating teacher: " . $e->getMessage() . "</div>";
        }
    } elseif ($action == 'delete' && isset($_POST['teacher_id'])) {
        $teacher_id = (int)$_POST['teacher_id'];
        
        try {
            $pdo->beginTransaction();
            
            // Get user_id first
            $user_stmt = $pdo->prepare("SELECT user_id FROM teachers WHERE teacher_id = ?");
            $user_stmt->execute([$teacher_id]);
            $user = $user_stmt->fetch();
            
            if ($user) {
                // Delete from related tables first
                $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?")->execute([$teacher_id]);
                $pdo->prepare("DELETE FROM teacher_class_assignments WHERE teacher_id = ?")->execute([$teacher_id]);
                $pdo->prepare("DELETE FROM grades WHERE teacher_id = ?")->execute([$teacher_id]);
                $pdo->prepare("DELETE FROM assessments WHERE teacher_id = ?")->execute([$teacher_id]);
                
                // Delete teacher record
                $pdo->prepare("DELETE FROM teachers WHERE teacher_id = ?")->execute([$teacher_id]);
                
                // Delete user account
                $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user['user_id']]);
                
                $pdo->commit();
                $message = "<div class='alert success'>Teacher account deleted successfully!</div>";
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "<div class='alert error'>Error deleting teacher: " . $e->getMessage() . "</div>";
        }
    }
}
$view_role = isset($_GET['role']) ? (int)$_GET['role'] : null;
$query = "SELECT user_id, username, role_id FROM users";
if ($view_role) {
    $query .= " WHERE role_id = $view_role";
}
$query .= " ORDER BY user_id DESC LIMIT 10";
$user_list = $pdo->query($query)->fetchAll();

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

// Handle edit teacher data loading
$edit_teacher = null;
if (isset($_GET['edit_teacher'])) {
    $edit_user_id = (int)$_GET['edit_teacher'];
    $edit_stmt = $pdo->prepare("SELECT t.* FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.user_id = ? AND u.role_id = 2");
    $edit_stmt->execute([$edit_user_id]);
    $edit_teacher = $edit_stmt->fetch();
}
$student_count = $pdo->query("SELECT count(*) FROM users WHERE role_id=3 AND parent_id IS NOT NULL")->fetchColumn();
$teacher_count = $pdo->query("SELECT count(*) FROM users WHERE role_id=2")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold; border: 1px solid transparent; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .hidden-section { display: none; background: white; padding: 30px; border-radius: 15px; margin-top: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid #eee; }
        
        .role-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .role-1 { background: #e3f2fd; color: #0d47a1; }
        .role-2 { background: #e8f5e9; color: #2e7d32; }
        .role-3 { background: #fff3e0; color: #e65100; }

        .manage-link { color: var(--primary-dark); text-decoration: none; font-weight: 600; margin-left: 15px; font-size: 0.9rem; transition: 0.3s; }
        .manage-link:hover { color: var(--accent-color); }
        input:required { border-left: 3px solid #e74c3c; } /* Visual cue for required fields */
    </style>
</head>
<body>

    <div class="sidebar">
        <div style="text-align: center; margin-bottom: 30px;">
             <i class="fas fa-university fa-3x" style="color: var(--accent-color);"></i>
             <h2 style="margin-top: 10px;">Bright Horizon</h2>
        </div>
        <ul>
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="crud_students/view_students.php">
        <i class="fas fa-user-graduate"></i> View Student List
    </a>
            </li>
            <li><a href="admin_dashboard.php?role=2"><i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
            <li><a href="admin_dashboard.php?role=3"><i class="fas fa-user-friends"></i> Families</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <div class="header-bar">
            <div>
                <h1>Admin Control Center</h1>
                <p style="color: #666;">Institution Management Portal</p>
            </div>
            <div style="text-align: right;">
        
         <a href="admin_dashboard.php" class="manage-link" style="color: #aaa;">Show All</a>
            </div>
        </div>

        <?php echo $message; ?>

        <div style="display: flex; gap: 20px; margin-bottom: 30px;">
            <button onclick="toggleSection('familyForm')" style="background: var(--primary-dark); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600;"><i class="fas fa-user-plus"></i> Register Family</button>
            <button onclick="toggleSection('teacherForm')" style="background: #27ae60; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600;"><i class="fas fa-chalkboard-teacher"></i> Create Teacher</button>
        </div>

        <!-- REGISTRATION FORM -->
        <div id="familyForm" class="hidden-section">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="color: var(--primary-dark);"><i class="fas fa-id-card"></i> Register New Family</h2>
                <button onclick="toggleSection('familyForm')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color: #999;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="register_family" value="1">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <!-- Parent Information -->
                    <div style="grid-column: span 2; background: #f9f9f9; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                        <h3 style="color: var(--primary-dark); margin-top: 0;"><i class="fas fa-user"></i> Parent/Guardian Information</h3>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">First Name *</label>
                                <input type="text" name="parent_first_name" required placeholder="Enter first name" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Last Name *</label>
                                <input type="text" name="parent_last_name" required placeholder="Enter last name" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Phone Number *</label>
                                <input type="text" name="student_phone" maxlength="10" pattern="[0-9]{10}" required placeholder="e.g., +265 999 123 456" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Email (Optional)</label>
                                <input type="email" name="parent_email" placeholder="parent@example.com" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Username *</label>
                                <input type="text" name="parent_username" required placeholder="Choose a username" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Security Question *</label>
                                <select name="parent_security_question" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px; background: white;">
                                    <option value="">-- Select a security question --</option>
                                    <?php foreach ($security_questions as $q): ?>
                                        <option value="<?php echo htmlspecialchars($q); ?>"><?php echo htmlspecialchars($q); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Password *</label>
                                <input type="password" name="parent_password" required placeholder="Create password" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Confirm Password *</label>
                                <input type="password" name="parent_confirm_password" required placeholder="Confirm password" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                        </div>
                        <div style="margin-top: 15px;">
                            <label style="font-weight:bold; font-size: 0.9rem;">Security Answer *</label>
                            <input type="text" name="parent_security_answer" required placeholder="Answer to your security question" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                        </div>
                    </div>
                    
                    <!-- Student Information -->
                    <div style="grid-column: span 2; background: #f0f8ff; padding: 20px; border-radius: 10px;">
                        <h3 style="color: var(--primary-dark); margin-top: 0;"><i class="fas fa-user-graduate"></i> Student Information</h3>
                        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">First Name *</label>
                                <input type="text" name="student_first_name" required placeholder="Enter first name" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Last Name *</label>
                                <input type="text" name="student_last_name" required placeholder="Enter last name" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Gender *</label>
                                <select name="student_gender" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px; background: white;">
                                    <option value="">-- Select Gender --</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Date of Birth</label>
                                <input type="date" name="student_dob" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Phone Number *</label>
                                <input type="text" name="parent_phone" maxlength="10" pattern="[0-9]{10}" required placeholder="e.g., +265 999 123 456" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                            <div>
                                <label style="font-weight:bold; font-size: 0.9rem;">Class *</label>
                                <select name="student_class" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px; background: white;">
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($classes as $c): ?>
                                        <option value="<?php echo $c['class_id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="grid-column: span 3;">
                                <label style="font-weight:bold; font-size: 0.9rem;">Admission Number *</label>
                                <input type="text" name="student_admission_no" required placeholder="Enter admission number" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:25px; width:100%; padding:15px; font-weight:bold;">Register Family</button>
            </form>
        </div>

        <!-- CREATE TEACHER FORM -->
        <div id="teacherForm" class="hidden-section">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="color: var(--primary-dark);"><i class="fas fa-chalkboard-teacher"></i> Create Teacher Account</h2>
                <button onclick="toggleSection('teacherForm')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color: #999;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="teacher_action" value="create">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">First Name *</label>
                        <input type="text" name="teacher_first_name" required placeholder="Enter first name" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">Last Name *</label>
                        <input type="text" name="teacher_last_name" required placeholder="Enter last name" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">Email</label>
                        <input type="email" name="teacher_email" placeholder="teacher@example.com" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">Phone Number</label>
                        <input type="tel" name="teacher_phone" placeholder="+265 999 123 456" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">Username *</label>
                        <input type="text" name="teacher_username" required placeholder="Choose username" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">Password *</label>
                        <input type="password" name="teacher_password" required placeholder="Create password" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:25px; width:100%; padding:15px; font-weight:bold;">Create Teacher Account</button>
            </form>
        </div>

        <!-- EDIT TEACHER FORM -->
        <div id="editTeacherForm" class="hidden-section">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="color: var(--primary-dark);"><i class="fas fa-edit"></i> Edit Teacher</h2>
                <button onclick="toggleSection('editTeacherForm')" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color: #999;">&times;</button>
            </div>
            <form method="POST" id="editTeacherFormData">
                <input type="hidden" name="teacher_action" value="edit">
                <input type="hidden" name="teacher_id" id="edit_teacher_id" value="<?php echo $edit_teacher ? $edit_teacher['teacher_id'] : ''; ?>">
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">First Name *</label>
                        <input type="text" name="teacher_first_name" id="edit_first_name" value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['first_name']) : ''; ?>" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">Last Name *</label>
                        <input type="text" name="teacher_last_name" id="edit_last_name" value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['last_name']) : ''; ?>" required style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">Email</label>
                        <input type="email" name="teacher_email" id="edit_email" value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['email']) : ''; ?>" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                    <div>
                        <label style="font-weight:bold; font-size: 0.9rem;">Phone Number</label>
                        <input type="tel" name="teacher_phone" id="edit_phone" value="<?php echo $edit_teacher ? htmlspecialchars($edit_teacher['phone']) : ''; ?>" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; margin-top:5px;">
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="margin-top:25px; width:100%; padding:15px; font-weight:bold;">Update Teacher</button>
            </form>
        </div>

        <!-- DATA TABLE -->
        <div style="background: white; border-radius: 15px; padding: 25px; margin-top: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.02);">
            <h2 style="color: var(--primary-dark); margin-bottom: 15px;">User Directory</h2>
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align:left; border-bottom: 2px solid #f4f4f4;">
                        <th style="padding:15px; color: #999;">ID</th>
                        <th style="padding:15px; color: #999;">Name</th>
                        <th style="padding:15px; color: #999;">Role</th>
                        <th style="padding:15px; color: #999;">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($user_list as $row): ?>
                        <tr style="border-bottom: 1px solid #fdfdfd;">
                            <td style="padding:15px; color: #bbb;">#<?php echo $row['user_id']; ?></td>
                            <td style="padding:15px; font-weight: 600;"><?php echo $row['username']; ?></td>
                            <td style="padding:15px;"><span class="role-badge role-<?php echo $row['role_id']; ?>"><?php echo ($row['role_id'] == 1 ? 'Admin' : ($row['role_id'] == 2 ? 'Teacher' : 'Parent/Student')); ?></span></td>
                            <td style="padding:15px;">
                                <?php if ($row['role_id'] == 2): // Teacher ?>
                                    <button onclick="editTeacher(<?php echo $row['user_id']; ?>)" style="background: #3498db; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-right: 5px;"><i class="fas fa-edit"></i> Edit</button>
                                    <button onclick="deleteTeacher(<?php echo $row['user_id']; ?>)" style="background: #e74c3c; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;"><i class="fas fa-trash"></i> Delete</button>
                                <?php else: ?>
                                    <span style="color: #999;">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleSection(id) {
            const section = document.getElementById(id);
            section.style.display = (section.style.display === "none" || section.style.display === "") ? "block" : "none";
        }

        function editTeacher(userId) {
            // Fetch teacher data via AJAX or redirect to edit page
            // For simplicity, let's redirect to an edit page or use a modal with data
            window.location.href = '?edit_teacher=' + userId;
        }

        function deleteTeacher(userId) {
            if (confirm('Are you sure you want to delete this teacher? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="teacher_action" value="delete">
                    <input type="hidden" name="teacher_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Handle edit teacher loading
        <?php if (isset($_GET['edit_teacher'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            toggleSection('editTeacherForm');
        });
        <?php endif; ?>
    </script>
</body>
</html>