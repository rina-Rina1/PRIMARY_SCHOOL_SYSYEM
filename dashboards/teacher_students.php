<?php
session_start();
require_once "../db.php"; 
include "../auth.php";
restrictTo(2); // Teacher only

$teacher_id = $_SESSION['user_id'];
$teacher_stmt = $pdo->prepare("SELECT t.teacher_id FROM teachers t JOIN users u ON t.user_id = u.user_id WHERE u.user_id = ?");
$teacher_stmt->execute([$teacher_id]);
$teacher = $teacher_stmt->fetch();
$teacher_id = $teacher ? $teacher['teacher_id'] : 0;

$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$query = "SELECT s.*, c.class_name FROM students s LEFT JOIN classes c ON s.class_id = c.class_id WHERE 1=1";
$params = [];

if ($class_filter > 0) {
    // Check if teacher is assigned to this class
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_class_assignments WHERE teacher_id = ? AND class_id = ?");
    $check_stmt->execute([$teacher_id, $class_filter]);
    if ($check_stmt->fetchColumn() == 0) {
        die("<h2 style='color: red; margin-top: 50px;'>Unauthorized: You are not assigned to this class.</h2>");
    }
    $query .= " AND s.class_id = ?";
    $params[] = $class_filter;
}

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

$query .= " ORDER BY s.first_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get teacher's classes
$classes_stmt = $pdo->prepare("SELECT c.class_id, c.class_name FROM teacher_class_assignments tca JOIN classes c ON tca.class_id = c.class_id WHERE tca.teacher_id = ? ORDER BY c.class_name");
$classes_stmt->execute([$teacher_id]);
$assigned_classes = $classes_stmt->fetchAll();

// View student profile
$view_student_id = isset($_GET['view']) ? (int)$_GET['view'] : null;
$view_student = null;
$student_grades = [];
$parent_info = null;

if ($view_student_id) {
    // Verify teacher has access to this student
    $vs_stmt = $pdo->prepare("
        SELECT s.*, c.class_name 
        FROM students s 
        LEFT JOIN classes c ON s.class_id = c.class_id 
        JOIN teacher_class_assignments tca ON s.class_id = tca.class_id 
        WHERE s.student_id = ? AND tca.teacher_id = ?
    ");
    $vs_stmt->execute([$view_student_id, $teacher_id]);
    $view_student = $vs_stmt->fetch();
    
    if ($view_student) {
        // Get student grades
        $grades_stmt = $pdo->prepare("
            SELECT g.*, sub.subject_name 
            FROM grades g 
            JOIN subjects sub ON g.subject_id = sub.subject_id 
            WHERE g.student_id = ? 
            ORDER BY sub.subject_name
        ");
        $grades_stmt->execute([$view_student_id]);
        $student_grades = $grades_stmt->fetchAll();
        
        // Get parent information
        $parent_stmt = $pdo->prepare("
            SELECT p.*, u.username 
            FROM parents p 
            JOIN student_parents sp ON p.parent_id = sp.parent_id 
            JOIN users u ON p.user_id = u.user_id 
            WHERE sp.student_id = ?
        ");
        $parent_stmt->execute([$view_student_id]);
        $parent_info = $parent_stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Students - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content-wrapper { margin-left: 260px; padding: 30px; }
        .filters { background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 15px; align-items: end; flex-wrap: wrap; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: 600; margin-bottom: 5px; font-size: 0.9rem; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; width: 200px; }
        .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--primary-dark); color: white; padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #f9f9f9; }
        .btn-small { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; margin-right: 5px; }
        .btn-view { background: #3498db; color: white; }
        .btn-edit { background: #27ae60; color: white; }
        .btn-delete { background: #e74c3c; color: white; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 40px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 1.5rem; font-weight: 600; color: var(--primary-dark); margin-bottom: 20px; }
        .btn-close { color: #999; background: none; border: none; font-size: 1.5rem; cursor: pointer; float: right; }
        .info-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .info-group { }
        .info-label { font-weight: 600; color: #666; font-size: 0.9rem; }
        .info-value { font-size: 1.1rem; color: var(--primary-dark); margin-top: 5px; }
        .gender-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; }
        .gender-badge.male { background: #d4e6f1; color: #0d47a1; }
        .gender-badge.female { background: #fce4ec; color: #880e4f; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; }
        .alert.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="text-align: center; margin-bottom: 30px;">
             <i class="fas fa-chalkboard-teacher fa-3x" style="color: var(--accent-color);"></i>
             <h2 style="margin-top: 10px;">Teacher Portal</h2>
        </div>
        <ul>
            <li><a href="teacher_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="teacher_students.php" class="active"><i class="fas fa-user-graduate"></i> My Students</a></li>
            <li><a href="teacher_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="teacher_marks.php"><i class="fas fa-pen-nib"></i> Enter Marks</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="content-wrapper">
        <div style="margin-bottom: 30px;">
            <h1 style="margin: 0;"><i class="fas fa-users"></i> My Students</h1>
            <p style="color: #666; margin: 5px 0 0 0;">View and manage your class students</p>
        </div>

        <div class="filters">
            <form style="display: flex; gap: 15px; align-items: end;">
                <div class="filter-group">
                    <label for="class"><i class="fas fa-book"></i> Class</label>
                    <select id="class" name="class_id">
                        <option value="">All My Classes</option>
                        <?php foreach ($assigned_classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php if ($class_filter == $c['class_id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="search"><i class="fas fa-search"></i> Search</label>
                    <input type="text" id="search" name="search" placeholder="Name or Admission No" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button type="submit" class="btn-small" style="background: var(--primary-dark); color: white; padding: 8px 20px;"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Admission No</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Gender</th>
                        <th>Class</th>
                        <th>Phone</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 30px; color: #999;"><i class="fas fa-inbox"></i> No students found</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['admission_no']); ?></td>
                                <td><?php echo htmlspecialchars($s['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($s['last_name']); ?></td>
                                <td><span class="gender-badge <?php echo strtolower($s['gender']); ?>"><?php echo $s['gender']; ?></span></td>
                                <td><?php echo htmlspecialchars($s['class_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td>
                                    <button class="btn-small btn-view" onclick="viewStudent(<?php echo $s['student_id']; ?>)"><i class="fas fa-eye"></i> Profile</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Student Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <button class="btn-close" onclick="closeModal()">&times;</button>
            <div class="modal-header"><i class="fas fa-id-card"></i> Student Profile</div>
            
            <?php if ($view_student): ?>
                <div class="info-row">
                    <div class="info-group">
                        <div class="info-label">First Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($view_student['first_name']); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Last Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($view_student['last_name']); ?></div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-group">
                        <div class="info-label">Admission Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($view_student['admission_no']); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Class</div>
                        <div class="info-value"><?php echo htmlspecialchars($view_student['class_name']); ?></div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-group">
                        <div class="info-label">Gender</div>
                        <div class="info-value"><span class="gender-badge <?php echo strtolower($view_student['gender']); ?>"><?php echo $view_student['gender']; ?></span></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Date of Birth</div>
                        <div class="info-value"><?php echo $view_student['dob'] ? date('M d, Y', strtotime($view_student['dob'])) : 'N/A'; ?></div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-group">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($view_student['phone']); ?></div>
                    </div>
                    <div class="info-group">
                        <div class="info-label">Registration Date</div>
                        <div class="info-value"><?php echo date('M d, Y', strtotime($view_student['created_at'])); ?></div>
                    </div>
                </div>

                <?php if ($parent_info): ?>
                <div style="margin-top: 25px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <h4 style="margin: 0 0 15px 0; color: var(--primary-dark);"><i class="fas fa-user-friends"></i> Parent/Guardian Information</h4>
                    <div class="info-row">
                        <div class="info-group">
                            <div class="info-label">Parent Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($parent_info['first_name'] . ' ' . $parent_info['last_name']); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Username</div>
                            <div class="info-value"><?php echo htmlspecialchars($parent_info['username']); ?></div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-group">
                            <div class="info-label">Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($parent_info['phone']); ?></div>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($parent_info['email'] ?: 'N/A'); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div style="margin-top: 25px;">
                    <h4 style="margin: 0 0 15px 0; color: var(--primary-dark);"><i class="fas fa-chart-bar"></i> Academic Performance</h4>
                    <?php if (empty($student_grades)): ?>
                        <p style="color: #666; font-style: italic;">No grades recorded yet.</p>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 10px; border: 1px solid #dee2e6; text-align: left; font-weight: 600;">Subject</th>
                                    <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center; font-weight: 600;">Mark (%)</th>
                                    <th style="padding: 10px; border: 1px solid #dee2e6; text-align: center; font-weight: 600;">Term</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_marks = 0;
                                $grade_count = 0;
                                foreach ($student_grades as $grade): 
                                    $total_marks += $grade['mark'];
                                    $grade_count++;
                                ?>
                                    <tr>
                                        <td style="padding: 10px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                        <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center; font-weight: 600;"><?php echo number_format($grade['mark'], 1); ?>%</td>
                                        <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;"><?php echo htmlspecialchars($grade['term']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($grade_count > 0): ?>
                                    <tr style="background: #e3f2fd; font-weight: 600;">
                                        <td style="padding: 10px; border: 1px solid #dee2e6;">Average Performance</td>
                                        <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center; color: var(--primary-dark);"><?php echo number_format($total_marks / $grade_count, 1); ?>%</td>
                                        <td style="padding: 10px; border: 1px solid #dee2e6; text-align: center;">Overall</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 25px; text-align: center;">
                    <button type="button" onclick="closeModal()" style="padding: 10px 30px; background: var(--primary-dark); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">Close</button>
                </div>
            <?php else: ?>
                <div class="alert error">Student not found or access denied.</div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function viewStudent(id) {
            window.location.href = '?view=' + id + '&class_id=<?php echo $class_filter; ?>';
        }

        function closeModal() {
            window.location.href = 'teacher_students.php<?php echo $class_filter ? '?class_id=' . $class_filter : ''; ?>';
        }

        // Open modal if viewing
        <?php if ($view_student): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('profileModal').classList.add('active');
        });
        <?php endif; ?>
    </script>
</body>
</html>
