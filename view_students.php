<?php
session_start();
require_once "../../db.php"; 
include "../../auth.php";
restrictTo(1);

$message = "";
$error = "";

// Handle Edit
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$student = null;

if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$edit_id]);
    $student = $stmt->fetch();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    try {
        $pdo->beginTransaction();
        
        // Get user_id associated with student
        $s_stmt = $pdo->prepare("SELECT user_id FROM students WHERE student_id = ?");
        $s_stmt->execute([$delete_id]);
        $student_user = $s_stmt->fetch();
        
        if ($student_user) {
            $u_stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $u_stmt->execute([$student_user['user_id']]);
        }
        
        // Delete student_parents associations
        $sp_stmt = $pdo->prepare("DELETE FROM student_parents WHERE student_id = ?");
        $sp_stmt->execute([$delete_id]);
        
        // Delete student
        $d_stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
        $d_stmt->execute([$delete_id]);
        
        $pdo->commit();
        $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Student deleted successfully.</div>";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error deleting student: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Handle Update/Create
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : null;
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $gender = $_POST['gender'];
    $dob = $_POST['dob'];
    $phone = trim($_POST['phone']);
    $class_id = (int)$_POST['class_id'];
    $admission_no = trim($_POST['admission_no']);

    $valid = true;
    $errors = [];

    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (empty($phone)) $errors[] = "Phone is required";
    if (empty($admission_no)) $errors[] = "Admission number is required";

    if (!empty($errors)) {
        $error = "<div class='alert error'>" . implode("<br>", $errors) . "</div>";
    } else {
        try {
            if ($student_id) {
                // Update
                $u_stmt = $pdo->prepare("UPDATE students SET first_name=?, last_name=?, gender=?, dob=?, phone=?, class_id=?, admission_no=? WHERE student_id=?");
                $u_stmt->execute([$first_name, $last_name, $gender, $dob, $phone, $class_id, $admission_no, $student_id]);
                $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Student updated successfully.</div>";
            } else {
                // Create
                $c_stmt = $pdo->prepare("INSERT INTO students (first_name, last_name, gender, dob, phone, class_id, admission_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $c_stmt->execute([$first_name, $last_name, $gender, $dob, $phone, $class_id, $admission_no]);
                $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Student created successfully.</div>";
            }
            $edit_id = null;
            $student = null;
        } catch (Exception $e) {
            $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Fetch students
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$class_filter = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';

$query = "SELECT s.*, c.class_name FROM students s LEFT JOIN classes c ON s.class_id = c.class_id WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($class_filter > 0) {
    $query .= " AND s.class_id = ?";
    $params[] = $class_filter;
}

if (!empty($gender_filter)) {
    $query .= " AND s.gender = ?";
    $params[] = $gender_filter;
}

$query .= " ORDER BY c.class_name ASC, s.first_name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

$classes = $pdo->query("SELECT class_id, class_name FROM classes ORDER BY class_id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Management - Bright Horizon</title>
    <link rel="stylesheet" href="../../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content-wrapper { margin-left: 260px; padding: 30px; }
        .filters { background: white; padding: 20px; border-radius: 12px; margin-bottom: 25px; display: flex; gap: 15px; align-items: end; flex-wrap: wrap; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .filter-group { display: flex; flex-direction: column; }
        .filter-group label { font-weight: 600; margin-bottom: 5px; font-size: 0.9rem; }
        .filter-group input, .filter-group select { padding: 8px 12px; border: 1px solid #ddd; border-radius: 6px; }
        .table-container { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .table-header { padding: 20px; border-bottom: 2px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--primary-dark); color: white; padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #f9f9f9; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: white; padding: 40px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { font-size: 1.5rem; font-weight: 600; color: var(--primary-dark); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; }
        .btn-small { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
        .btn-edit { background: #3498db; color: white; }
        .btn-delete { background: #e74c3c; color: white; }
        .btn-save { background: var(--primary-dark); color: white; }
        .btn-cancel { background: #95a5a6; color: white; }
        .btn-close { color: #999; background: none; border: none; font-size: 1.5rem; cursor: pointer; float: right; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
        .gender-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; }
        .gender-badge.male { background: #d4e6f1; color: #0d47a1; }
        .gender-badge.female { background: #fce4ec; color: #880e4f; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="text-align: center; margin-bottom: 30px;">
             <i class="fas fa-university fa-3x" style="color: var(--accent-color);"></i>
             <h2 style="margin-top: 10px;">Bright Horizon</h2>
        </div>
        <ul>
            <li><a href="../admin_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="../register_family.php"><i class="fas fa-user-plus"></i> Register Family</a></li>
            <li><a href="view_students.php" class="active"><i class="fas fa-user-graduate"></i> View Students</a></li>
            <li><a href="../admin_dashboard.php?role=2"><i class="fas fa-chalkboard-teacher"></i> Teachers</a></li>
            <li><a href="../teacher_assignments.php"><i class="fas fa-link"></i> Assign Teachers</a></li>
        </ul>
        <a href="../../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="content-wrapper">
        <div style="margin-bottom: 30px;">
            <h1 style="margin: 0;"><i class="fas fa-users-cog"></i> Student Management</h1>
            <p style="color: #666; margin: 5px 0 0 0;">View, edit, and manage all student records</p>
        </div>

        <?php if ($message): echo $message; endif; ?>
        <?php if ($error): echo $error; endif; ?>

        <div class="filters">
            <form style="display: flex; gap: 15px; flex: 1; align-items: end;">
                <div class="filter-group">
                    <label for="search"><i class="fas fa-search"></i> Search</label>
                    <input type="text" id="search" name="search" placeholder="Name or Admission No" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group">
                    <label for="class"><i class="fas fa-book"></i> Class</label>
                    <select id="class" name="class">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php if ($class_filter == $c['class_id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="gender"><i class="fas fa-venus-mars"></i> Gender</label>
                    <select id="gender" name="gender">
                        <option value="">All</option>
                        <option value="Male" <?php if ($gender_filter == 'Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if ($gender_filter == 'Female') echo 'selected'; ?>>Female</option>
                    </select>
                </div>
                <button type="submit" class="btn-small btn-edit" style="padding: 8px 20px;"><i class="fas fa-filter"></i> Filter</button>
                <button type="button" onclick="resetFilters()" class="btn-small btn-cancel" style="padding: 8px 20px;"><i class="fas fa-times"></i> Reset</button>
            </form>
            <button type="button" onclick="openModal('new')" class="btn-small btn-edit" style="padding: 10px 20px; background: var(--primary-dark);"><i class="fas fa-plus"></i> New Student</button>
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
                        <th>DOB</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 30px; color: #999;"><i class="fas fa-inbox"></i> No students found</td></tr>
                    <?php else: ?>
                        <?php foreach ($students as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['admission_no']); ?></td>
                                <td><?php echo htmlspecialchars($s['first_name']); ?></td>
                                <td><?php echo htmlspecialchars($s['last_name']); ?></td>
                                <td><span class="gender-badge <?php echo strtolower($s['gender']); ?>"><?php echo $s['gender']; ?></span></td>
                                <td><?php echo htmlspecialchars($s['class_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($s['phone']); ?></td>
                                <td><?php echo $s['dob'] ? date('M d, Y', strtotime($s['dob'])) : 'N/A'; ?></td>
                                <td>
                                    <button class="btn-small btn-edit" onclick="editStudent(<?php echo $s['student_id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                    <button class="btn-small btn-delete" onclick="deleteStudent(<?php echo $s['student_id']; ?>)"><i class="fas fa-trash"></i> Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Edit/Create Modal -->
    <div id="studentModal" class="modal">
        <div class="modal-content">
            <button class="btn-close" onclick="closeModal()">&times;</button>
            <div class="modal-header"><i class="fas fa-graduation-cap"></i> <span id="modalTitle">New Student</span></div>
            
            <form method="POST" id="studentForm">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="student_id" id="studentId" value="">

                <div class="form-group">
                    <label for="modalFirstName">First Name *</label>
                    <input type="text" id="modalFirstName" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="modalLastName">Last Name *</label>
                    <input type="text" id="modalLastName" name="last_name" required>
                </div>
                <div class="form-group">
                    <label for="modalGender">Gender *</label>
                    <select id="modalGender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modalDOB">Date of Birth *</label>
                    <input type="date" id="modalDOB" name="dob" required>
                </div>
                <div class="form-group">
                    <label for="modalPhone">Phone Number *</label>
                    <input type="tel" id="modalPhone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="modalClass">Class *</label>
                    <select id="modalClass" name="class_id" required>
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>"><?php echo htmlspecialchars($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="modalAdmissionNo">Admission Number *</label>
                    <input type="text" id="modalAdmissionNo" name="admission_no" required>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="button" onclick="closeModal()" class="btn-small btn-cancel" style="flex: 1; padding: 10px;">Cancel</button>
                    <button type="submit" class="btn-small btn-save" style="flex: 1; padding: 10px;">Save Student</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(action) {
            document.getElementById('studentModal').classList.add('active');
            if (action === 'new') {
                document.getElementById('modalTitle').textContent = 'New Student';
                document.getElementById('studentForm').reset();
                document.getElementById('studentId').value = '';
            }
        }

        function closeModal() {
            document.getElementById('studentModal').classList.remove('active');
        }

        function editStudent(id) {
            window.location.href = '?edit=' + id;
        }

        function deleteStudent(id) {
            if (confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
                window.location.href = '?delete=' + id;
            }
        }

        function resetFilters() {
            window.location.href = 'view_students.php';
        }

        // Load student data if editing
        <?php if ($student): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('studentModal').classList.add('active');
            document.getElementById('modalTitle').textContent = 'Edit Student';
            document.getElementById('studentId').value = '<?php echo $student['student_id']; ?>';
            document.getElementById('modalFirstName').value = '<?php echo htmlspecialchars($student['first_name']); ?>';
            document.getElementById('modalLastName').value = '<?php echo htmlspecialchars($student['last_name']); ?>';
            document.getElementById('modalGender').value = '<?php echo $student['gender']; ?>';
            document.getElementById('modalDOB').value = '<?php echo $student['dob']; ?>';
            document.getElementById('modalPhone').value = '<?php echo htmlspecialchars($student['phone']); ?>';
            document.getElementById('modalClass').value = '<?php echo $student['class_id']; ?>';
            document.getElementById('modalAdmissionNo').value = '<?php echo htmlspecialchars($student['admission_no']); ?>';
        });
        <?php endif; ?>
    </script>
</body>
</html>