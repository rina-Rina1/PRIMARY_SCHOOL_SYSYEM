<?php 
include "../auth.php"; 
require_once "../db.php"; 
restrictTo(2); // Role 2 = Teacher

$teacher_id = $_SESSION['user_id'];
$message = "";

// 1. FETCH TEACHER'S AUTHORIZED SUBJECTS
try {
    $assign_stmt = $pdo->prepare("SELECT ts.subject_id, s.subject_name FROM teacher_subjects ts JOIN subjects s ON ts.subject_id = s.subject_id WHERE ts.teacher_id = ? ORDER BY s.subject_name");
    $assign_stmt->execute([$teacher_id]);
    $assignments = $assign_stmt->fetchAll();
} catch (Exception $e) {
    $message = "<div class='alert error'>Database Error: " . $e->getMessage() . "</div>";
    $assignments = [];
}

// 2. HANDLE GRADE SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_grade'])) {
    $student_id = $_POST['student_id'];
    $subject = $_POST['subject'];
    $mark = $_POST['mark'];
    $term = $_POST['term'];

    if (!empty($student_id) && !empty($subject) && $mark !== "") {
        try {
            $stmt = $pdo->prepare("INSERT INTO grades (student_id, subject_id, teacher_id, mark, term) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$student_id, $subject, $teacher_id, $mark, $term])) {
                $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Grade recorded successfully!</div>";
            }
        } catch (Exception $e) {
            $message = "<div class='alert error'>Submission Error: " . $e->getMessage() . "</div>";
        }
    } else {
        $message = "<div class='alert error'>Please ensure all fields are filled correctly.</div>";
    }
}

// 3. FETCH ALL STUDENTS
$students = $pdo->query("SELECT user_id, username FROM users WHERE role_id = 3 AND parent_id IS NOT NULL ORDER BY username ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Marks - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: bold; border: 1px solid transparent; }
        .success { background: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        
        .entry-card {
            background: white;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border-top: 5px solid var(--accent-color);
        }

        label { display: block; font-weight: bold; margin-bottom: 8px; color: var(--primary-dark); font-size: 0.9rem; }
        select, input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            background-color: #fdfdfd;
        }
        select:focus, input:focus { border-color: var(--accent-color); outline: none; background: white; }
    </style>
</head>
<body>

    <div class="sidebar">
        <div style="text-align: center; margin-bottom: 30px;">
             <i class="fas fa-chalkboard-teacher fa-3x" style="color: var(--accent-color);"></i>
             <h2 style="margin-top: 10px;">Teacher Portal</h2>
        </div>
        <ul>
            <li><a href="teacher_dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="teacher_marks.php" class="active"><i class="fas fa-pen-nib"></i> Enter Marks</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <div class="header-bar">
            <h1>Grade Entry</h1>
            <p style="color: #666;">Enter student marks for your authorized subjects.</p>
        </div>

        <?php echo $message; ?>

        <?php if (empty($assignments)): ?>
            <div class="alert error" style="background: #fff5f5; border-left: 5px solid #e74c3c;">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>No Assignments Found:</strong> You are not currently in the <code>teacher_class_assignments</code> table. 
                Please contact Admin to assign you to a subject.
            </div>
        <?php else: ?>
            <div class="entry-card">
                <form method="POST">
                    <input type="hidden" name="save_grade" value="1">
                    
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label>Student Name *</label>
                            <select name="student_id" required>
                                <option value="" disabled selected>-- Select Student --</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?php echo $s['user_id']; ?>"><?php echo $s['username']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label>Subject (Assigned) *</label>
                            <select name="subject" required>
                                <?php foreach ($assignments as $a): ?>
                                    <option value="<?php echo $a['subject_id']; ?>"><?php echo $a['subject_name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 25px;">
                        <div>
                            <label>Mark (%) *</label>
                            <input type="number" name="mark" min="0" max="100" required placeholder="Score">
                        </div>

                        <div>
                            <label>Term *</label>
                            <select name="term" required>
                                <option value="Term 1">Term 1</option>
                                <option value="Term 2">Term 2</option>
                                <option value="Term 3">Term 3</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="width:100%; padding:15px; font-size:1.1rem;">
                        <i class="fas fa-save"></i> Save Performance Record
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>