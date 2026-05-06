<?php
session_start();
require_once "../db.php"; 
include "../auth.php";
restrictTo(1); // Admin only

// Get all classes with students
$classes_stmt = $pdo->prepare("
    SELECT c.class_id, c.class_name, 
           COUNT(s.student_id) as total_students,
           SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
           SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as female_count
    FROM classes c
    LEFT JOIN students s ON c.class_id = s.class_id
    GROUP BY c.class_id, c.class_name
    ORDER BY c.class_id
");
$classes_stmt->execute();
$classes = $classes_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Students by Class - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content-wrapper { margin-left: 260px; padding: 30px; }
        .class-card { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; overflow: hidden; }
        .class-header { background: var(--primary-dark); color: white; padding: 20px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: background 0.3s; }
        .class-header:hover { background: var(--accent-color); color: var(--primary-dark); }
        .class-title { font-size: 1.2rem; font-weight: 600; }
        .class-stats { display: flex; gap: 20px; font-size: 0.9rem; }
        .stat { display: flex; align-items: center; gap: 5px; }
        .stat-badge { background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 20px; }
        .class-content { display: none; padding: 20px; }
        .class-content.active { display: block; }
        .gender-section { margin-bottom: 20px; }
        .gender-header { background: #f0f0f0; padding: 12px 15px; border-radius: 8px; font-weight: 600; color: var(--primary-dark); margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .gender-icon-male { color: #0d47a1; }
        .gender-icon-female { color: #880e4f; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f9f9f9; padding: 12px 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; }
        td { padding: 12px 15px; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #f9f9f9; }
        .gender-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; }
        .gender-badge.male { background: #d4e6f1; color: #0d47a1; }
        .gender-badge.female { background: #fce4ec; color: #880e4f; }
        .empty-state { text-align: center; padding: 30px; color: #999; }
        .toggle-icon { transition: transform 0.3s; }
        .toggle-icon.open { transform: rotate(180deg); }
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
            <li><a href="teacher_assignments.php"><i class="fas fa-link"></i> Assign Teachers</a></li>
            <li><a href="student_list_by_class.php" class="active"><i class="fas fa-list"></i> Students by Class</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="content-wrapper">
        <div style="margin-bottom: 30px;">
            <h1 style="margin: 0;"><i class="fas fa-layer-group"></i> Students by Class</h1>
            <p style="color: #666; margin: 5px 0 0 0;">View all students organized by class and gender</p>
        </div>

        <?php foreach ($classes as $class): ?>
            <div class="class-card">
                <div class="class-header" onclick="toggleClass(this)">
                    <div>
                        <div class="class-title"><i class="fas fa-book"></i> <?php echo htmlspecialchars($class['class_name']); ?></div>
                    </div>
                    <div class="class-stats">
                        <div class="stat">
                            <span>Total: <span class="stat-badge"><?php echo $class['total_students']; ?></span></span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-mars" style="color: #0d47a1;"></i>
                            <span class="stat-badge"><?php echo $class['male_count'] ?? 0; ?></span>
                        </div>
                        <div class="stat">
                            <i class="fas fa-venus" style="color: #880e4f;"></i>
                            <span class="stat-badge"><?php echo $class['female_count'] ?? 0; ?></span>
                        </div>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                </div>

                <div class="class-content" id="class-<?php echo $class['class_id']; ?>">
                    <?php
                    // Get male students
                    $male_stmt = $pdo->prepare("SELECT * FROM students WHERE class_id = ? AND gender = 'Male' ORDER BY first_name");
                    $male_stmt->execute([$class['class_id']]);
                    $male_students = $male_stmt->fetchAll();

                    // Get female students
                    $female_stmt = $pdo->prepare("SELECT * FROM students WHERE class_id = ? AND gender = 'Female' ORDER BY first_name");
                    $female_stmt->execute([$class['class_id']]);
                    $female_students = $female_stmt->fetchAll();
                    ?>

                    <!-- Boys Section -->
                    <?php if (!empty($male_students)): ?>
                        <div class="gender-section">
                            <div class="gender-header"><i class="fas fa-mars gender-icon-male"></i> Boys (<?php echo count($male_students); ?>)</div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Admission No</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>DOB</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($male_students as $s): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s['admission_no']); ?></td>
                                            <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                            <td><?php echo $s['dob'] ? date('M d, Y', strtotime($s['dob'])) : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- Girls Section -->
                    <?php if (!empty($female_students)): ?>
                        <div class="gender-section">
                            <div class="gender-header"><i class="fas fa-venus gender-icon-female"></i> Girls (<?php echo count($female_students); ?>)</div>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Admission No</th>
                                        <th>Name</th>
                                        <th>Phone</th>
                                        <th>DOB</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($female_students as $s): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($s['admission_no']); ?></td>
                                            <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                            <td><?php echo $s['dob'] ? date('M d, Y', strtotime($s['dob'])) : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if (empty($male_students) && empty($female_students)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                            <p>No students in this class yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        function toggleClass(header) {
            const content = header.nextElementSibling;
            const icon = header.querySelector('.toggle-icon');
            
            // Close all other class contents
            document.querySelectorAll('.class-content.active').forEach(el => {
                if (el !== content) {
                    el.classList.remove('active');
                    el.previousElementSibling.querySelector('.toggle-icon').classList.remove('open');
                }
            });
            
            // Toggle current
            content.classList.toggle('active');
            icon.classList.toggle('open');
        }
    </script>
</body>
</html>
