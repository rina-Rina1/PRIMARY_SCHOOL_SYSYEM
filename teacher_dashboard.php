<?php 
include "../auth.php"; 
require_once "../db.php"; 
restrictTo(2); // Role 2 = Teacher

// Get teacher info and assigned classes
$teacher_id = $_SESSION['user_id'];
$teacher_stmt = $pdo->prepare("SELECT t.teacher_id FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.user_id = ?");
$teacher_stmt->execute([$teacher_id]);
$teacher = $teacher_stmt->fetch();
$teacher_id = $teacher ? $teacher['teacher_id'] : 0;

// Get assigned classes
$classes_stmt = $pdo->prepare("SELECT DISTINCT c.class_id, c.class_name FROM teacher_class_assignments tca JOIN classes c ON tca.class_id = c.class_id WHERE tca.teacher_id = ?");
$classes_stmt->execute([$teacher_id]);
$assigned_classes = $classes_stmt->fetchAll();

// Count students
$student_count = $pdo->query("SELECT count(*) FROM users WHERE role_id=3")->fetchColumn();
$announcement_count = $pdo->query("SELECT count(*) FROM announcements")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Teacher Portal - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div style="text-align: center; margin-bottom: 30px;">
             <i class="fas fa-chalkboard-teacher fa-3x" style="color: var(--accent-color);"></i>
             <h2 style="margin-top: 10px;">Teacher Portal</h2>
        </div>
        <ul>
            <li><a href="teacher_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="teacher_students.php"><i class="fas fa-user-graduate"></i> My Students</a></li>
            <li><a href="teacher_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="teacher_marks.php"><i class="fas fa-pen-nib"></i> Enter Marks</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <div class="header-bar">
            <div>
                <h1><i class="fas fa-chart-line"></i> Teacher Dashboard</h1>
                <p style="color: #666;">Welcome back! Manage your classes and students here.</p>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card">
                <h3 style="color:#666; font-size: 0.85rem; text-transform: uppercase;"><i class="fas fa-users"></i> Total Students</h3>
                <p style="font-size: 2.2rem; font-weight: 800; color: var(--primary-dark);"><?php echo $student_count; ?></p>
            </div>
            <div class="stat-card" style="border-top-color: #27ae60;">
                <h3 style="color:#666; font-size: 0.85rem; text-transform: uppercase;"><i class="fas fa-book"></i> My Classes</h3>
                <p style="font-size: 2.2rem; font-weight: 800; color: #27ae60;"><?php echo count($assigned_classes); ?></p>
            </div>
            <div class="stat-card" style="border-top-color: #e74c3c;">
                <h3 style="color:#666; font-size: 0.85rem; text-transform: uppercase;"><i class="fas fa-bullhorn"></i> Announcements</h3>
                <p style="font-size: 2.2rem; font-weight: 800; color: #e74c3c;"><?php echo $announcement_count; ?></p>
            </div>
        </div>

        <!-- Assigned Classes -->
        <div style="background: white; border-radius: 15px; padding: 25px; margin-top: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.02);">
            <h2><i class="fas fa-list"></i> My Assigned Classes</h2>
            <?php if (empty($assigned_classes)): ?>
                <p style="color: #999; margin-top: 15px;"><i class="fas fa-inbox"></i> No classes assigned yet. Contact your admin.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee;">
                            <th style="padding: 12px;">Class Name</th>
                            <th style="padding: 12px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assigned_classes as $class): ?>
                            <tr style="border-bottom: 1px solid #f9f9f9;">
                                <td style="padding: 12px; font-weight: 600;"><i class="fas fa-book"></i> <?php echo htmlspecialchars($class['class_name']); ?></td>
                                <td style="padding: 12px;">
                                    <a href="teacher_students.php?class_id=<?php echo $class['class_id']; ?>" style="padding: 6px 12px; background: #3498db; color: white; border-radius: 5px; text-decoration: none; font-size: 0.85rem; margin-right: 5px;"><i class="fas fa-users"></i> View Students</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div style="background: white; border-radius: 15px; padding: 25px; margin-top: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.02);">
            <h2><i class="fas fa-lightning-bolt"></i> Quick Actions</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                <a href="teacher_marks.php" style="padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s;">
                    <i class="fas fa-pen-nib fa-2x"></i>
                    <div><strong>Enter Marks</strong><p style="font-size: 0.85rem; margin: 0; opacity: 0.9;">Record student assessments</p></div>
                </a>
                <a href="teacher_announcements.php" style="padding: 15px; background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s;">
                    <i class="fas fa-bullhorn fa-2x"></i>
                    <div><strong>Post Announcement</strong><p style="font-size: 0.85rem; margin: 0; opacity: 0.9;">Share updates with parents</p></div>
                </a>
                <a href="teacher_students.php" style="padding: 15px; background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; border-radius: 8px; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: 0.3s;">
                    <i class="fas fa-user-graduate fa-2x"></i>
                    <div><strong>Manage Students</strong><p style="font-size: 0.85rem; margin: 0; opacity: 0.9;">View student details and info</p></div>
                </a>
            </div>
        </div>
    </div>
</body>
</html>