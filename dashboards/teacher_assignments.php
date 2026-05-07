<?php
session_start();
require_once "../db.php"; 
include "../auth.php";
restrictTo(1); // Admin only

$message = "";
$error = "";

// Get all teachers
$teachers = $pdo->query("SELECT t.teacher_id, t.first_name, t.last_name, u.user_id FROM teachers t JOIN users u ON t.user_id = u.user_id ORDER BY t.first_name")->fetchAll();

// Get all classes
$classes = $pdo->query("SELECT class_id, class_name FROM classes ORDER BY class_id")->fetchAll();

// Handle assignment
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'assign') {
    $teacher_id = (int)$_POST['teacher_id'];
    $class_id = (int)$_POST['class_id'];
    $role_in_class = $_POST['role_in_class'] ?? 'lead';

    // Check if teacher already has 5 classes
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_class_assignments WHERE teacher_id = ?");
    $count_stmt->execute([$teacher_id]);
    $class_count = $count_stmt->fetchColumn();

    if ($class_count >= 5) {
        $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> This teacher already has 5 classes assigned. Teachers can teach a maximum of 5 classes.</div>";
    } else {
        // Check if assignment already exists
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_class_assignments WHERE teacher_id = ? AND class_id = ?");
        $check_stmt->execute([$teacher_id, $class_id]);
        
        if ($check_stmt->fetchColumn() > 0) {
            $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> This teacher is already assigned to this class.</div>";
        } else {
            try {
                $ins_stmt = $pdo->prepare("INSERT INTO teacher_class_assignments (teacher_id, class_id, role_in_class) VALUES (?, ?, ?)");
                $ins_stmt->execute([$teacher_id, $class_id, $role_in_class]);
                $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Teacher assigned to class successfully!</div>";
            } catch (Exception $e) {
                $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }
}

// Handle assignment removal
if (isset($_GET['remove'])) {
    $parts = explode('-', $_GET['remove']);
    if (count($parts) == 2) {
        $teacher_id = (int)$parts[0];
        $class_id = (int)$parts[1];
        try {
            $del_stmt = $pdo->prepare("DELETE FROM teacher_class_assignments WHERE teacher_id = ? AND class_id = ?");
            $del_stmt->execute([$teacher_id, $class_id]);
            $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Assignment removed successfully!</div>";
        } catch (Exception $e) {
            $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error removing assignment.</div>";
        }
    }
}

// Fetch all assignments
$assignments_stmt = $pdo->prepare("SELECT tca.*, t.first_name, t.last_name, c.class_name FROM teacher_class_assignments tca JOIN teachers t ON tca.teacher_id = t.teacher_id JOIN classes c ON tca.class_id = c.class_id ORDER BY t.first_name, c.class_name");
$assignments_stmt->execute();
$assignments = $assignments_stmt->fetchAll();

// Count classes per teacher
$teacher_class_count = [];
foreach ($assignments as $a) {
    $key = $a['teacher_id'];
    if (!isset($teacher_class_count[$key])) {
        $teacher_class_count[$key] = 0;
    }
    $teacher_class_count[$key]++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Teachers - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content-wrapper { margin-left: 260px; padding: 30px; }
        .form-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 8px; }
        .form-group select, .form-group input { padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .btn-primary { background: var(--primary-dark); color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background: var(--accent-color); color: var(--primary-dark); }
        .table-container { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--primary-dark); color: white; padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #f9f9f9; }
        .role-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; }
        .role-lead { background: #c8e6c9; color: #1b5e20; }
        .role-assistant { background: #bbdefb; color: #0d47a1; }
        .btn-small { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
        .btn-remove { background: #e74c3c; color: white; }
        .btn-remove:hover { background: #c0392b; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .count-badge { background: #f39c12; color: white; padding: 4px 10px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .count-full { background: #e74c3c; }
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
            <li><a href="register_family.php"><i class="fas fa-user-plus"></i> Register Family</a></li>
            <li><a href="crud_students/view_students.php"><i class="fas fa-user-graduate"></i> View Students</a></li>
            <li><a href="admin_dashboard.php?role=2"><i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
            <li><a href="teacher_assignments.php" class="active"><i class="fas fa-link"></i> Assign Teachers</a></li>
            <li><a href="student_list_by_class.php"><i class="fas fa-list"></i> Students by Class</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="content-wrapper">
        <div style="margin-bottom: 30px;">
            <h1 style="margin: 0;"><i class="fas fa-users-cog"></i> Assign Teachers to Classes</h1>
            <p style="color: #666; margin: 5px 0 0 0;">Manage teacher-class assignments (Max 5 classes per teacher)</p>
        </div>

        <?php if ($message): echo $message; endif; ?>
        <?php if ($error): echo $error; endif; ?>

        <!-- Assignment Form -->
        <div class="form-container">
            <h2 style="margin-top: 0;"><i class="fas fa-plus-circle"></i> New Assignment</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="assign">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="teacher_id">Teacher *</label>
                        <select id="teacher_id" name="teacher_id" required>
                            <option value="">-- Select Teacher --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?php echo $t['teacher_id']; ?>">
                                    <?php echo htmlspecialchars($t['first_name'] . ' ' . $t['last_name']); ?>
                                    <?php 
                                        $count = $teacher_class_count[$t['teacher_id']] ?? 0;
                                        $class = $count >= 5 ? 'count-full' : '';
                                        echo " <span class='count-badge $class'>$count/5</span>";
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="class_id">Class *</label>
                        <select id="class_id" name="class_id" required>
                            <option value="">-- Select Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo $c['class_id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="role_in_class">Role in Class *</label>
                        <select id="role_in_class" name="role_in_class" required>
                            <option value="lead">Lead Teacher</option>
                            <option value="assistant">Assistant Teacher</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn-primary"><i class="fas fa-link"></i> Assign Teacher</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Assignments List -->
        <div class="table-container">
            <div style="padding: 20px; border-bottom: 2px solid #f0f0f0;">
                <h2 style="margin: 0;"><i class="fas fa-list"></i> Current Assignments</h2>
            </div>

            <?php if (empty($assignments)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                    <p>No assignments yet. Create your first assignment above!</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Teacher Name</th>
                            <th>Class</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($a['class_name']); ?></td>
                                <td><span class="role-badge role-<?php echo $a['role_in_class']; ?>"><?php echo ucfirst($a['role_in_class']); ?></span></td>
                                <td>
                                    <a href="?remove=<?php echo $a['teacher_id'] . '-' . $a['class_id']; ?>" onclick="return confirm('Remove this assignment?')" class="btn-small btn-remove"><i class="fas fa-unlink"></i> Remove</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
