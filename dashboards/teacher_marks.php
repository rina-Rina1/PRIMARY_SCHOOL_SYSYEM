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

$message = "";
$error = "";

// Get classes assigned to this teacher
$classes_stmt = $pdo->prepare("SELECT DISTINCT c.class_id, c.class_name FROM teacher_class_assignments tca JOIN classes c ON tca.class_id = c.class_id WHERE tca.teacher_id = ? ORDER BY c.class_name");
$classes_stmt->execute([$teacher_id]);
$assigned_classes = $classes_stmt->fetchAll();

// Get subjects
$subjects = $pdo->query("SELECT subject_id, subject_name FROM subjects ORDER BY subject_name")->fetchAll();

$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$grade_term = isset($_GET['term']) ? $_GET['term'] : 'Term 1';
$students = [];
$selected_student = null;
$existing_grades = [];

if ($class_filter) {
    // Check if teacher is assigned to this class
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_class_assignments WHERE teacher_id = ? AND class_id = ?");
    $check_stmt->execute([$teacher_id, $class_filter]);
    if ($check_stmt->fetchColumn() == 0) {
        die("<h2 style='color: red; margin-top: 50px;'>Unauthorized: You are not assigned to this class.</h2>");
    }

    // Get students in this class
    $s_stmt = $pdo->prepare("SELECT student_id, first_name, last_name, admission_no FROM students WHERE class_id = ? ORDER BY first_name");
    $s_stmt->execute([$class_filter]);
    $students = $s_stmt->fetchAll();

    if ($selected_student_id) {
        $student_stmt = $pdo->prepare("SELECT student_id, first_name, last_name, admission_no FROM students WHERE student_id = ? AND class_id = ?");
        $student_stmt->execute([$selected_student_id, $class_filter]);
        $selected_student = $student_stmt->fetch();

        if ($selected_student) {
            $grades_stmt = $pdo->prepare("SELECT subject_id, mark FROM grades WHERE student_id = ? AND term = ?");
            $grades_stmt->execute([$selected_student_id, $grade_term]);
            $existing_grades = $grades_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'save_marks') {
    $class_filter = isset($_POST['class_id']) ? (int)$_POST['class_id'] : $class_filter;
    $selected_student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : $selected_student_id;
    $grade_term = $_POST['term'] ?? $grade_term;
    $marks = $_POST['marks'] ?? [];

    if ($class_filter) {
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_class_assignments WHERE teacher_id = ? AND class_id = ?");
        $check_stmt->execute([$teacher_id, $class_filter]);
        if ($check_stmt->fetchColumn() == 0) {
            $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Unauthorized: You are not assigned to this class.</div>";
        } else {
            try {
                $pdo->beginTransaction();

                foreach ($marks as $subject_id => $mark_value) {
                    if ($mark_value === '' || $mark_value === null) {
                        continue;
                    }

                    $mark = (float)$mark_value;
                    if ($mark < 0 || $mark > 100) {
                        throw new Exception("All marks must be between 0 and 100.");
                    }

                    $check = $pdo->prepare("SELECT grade_id FROM grades WHERE student_id = ? AND subject_id = ? AND term = ?");
                    $check->execute([$selected_student_id, $subject_id, $grade_term]);
                    $existing = $check->fetch();

                    if ($existing) {
                        $update = $pdo->prepare("UPDATE grades SET mark = ?, teacher_id = ? WHERE grade_id = ?");
                        $update->execute([$mark, $teacher_id, $existing['grade_id']]);
                    } else {
                        $insert = $pdo->prepare("INSERT INTO grades (student_id, subject_id, teacher_id, mark, term) VALUES (?, ?, ?, ?, ?)");
                        $insert->execute([$selected_student_id, $subject_id, $teacher_id, $mark, $grade_term]);
                    }
                }

                $pdo->commit();
                $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Marks saved successfully!</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error saving marks: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
        }
    }

    if ($class_filter) {
        $s_stmt = $pdo->prepare("SELECT student_id, first_name, last_name, admission_no FROM students WHERE class_id = ? ORDER BY first_name");
        $s_stmt->execute([$class_filter]);
        $students = $s_stmt->fetchAll();
    }

    if ($selected_student_id && $class_filter) {
        $student_stmt = $pdo->prepare("SELECT student_id, first_name, last_name, admission_no FROM students WHERE student_id = ? AND class_id = ?");
        $student_stmt->execute([$selected_student_id, $class_filter]);
        $selected_student = $student_stmt->fetch();

        if ($selected_student) {
            $grades_stmt = $pdo->prepare("SELECT subject_id, mark FROM grades WHERE student_id = ? AND term = ?");
            $grades_stmt->execute([$selected_student_id, $grade_term]);
            $existing_grades = $grades_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
    }
}

// Handle report generation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'generate_report') {
    $report_class = (int)$_POST['report_class'];
    $report_term = $_POST['report_term'];

    // Check if teacher is assigned to this class
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM teacher_class_assignments WHERE teacher_id = ? AND class_id = ?");
    $check_stmt->execute([$teacher_id, $report_class]);
    if ($check_stmt->fetchColumn() == 0) {
        $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> You are not assigned to this class.</div>";
    } else {
        // Get students and their grades
        $report_students = $pdo->prepare("
            SELECT s.student_id, s.first_name, s.last_name, s.admission_no,
                   GROUP_CONCAT(CONCAT(sub.subject_name, ':', g.mark) SEPARATOR '|') as grades
            FROM students s
            LEFT JOIN grades g ON s.student_id = g.student_id AND g.term = ?
            LEFT JOIN subjects sub ON g.subject_id = sub.subject_id
            WHERE s.class_id = ?
            GROUP BY s.student_id
            ORDER BY s.first_name
        ");
        $report_students->execute([$report_term, $report_class]);
        $report_data = $report_students->fetchAll();

        $class_name = 'Unknown Class';
        foreach ($assigned_classes as $classItem) {
            if ($classItem['class_id'] == $report_class) {
                $class_name = $classItem['class_name'];
                break;
            }
        }

        $logo_data = '';
        if (file_exists(__DIR__ . '/../logo.png')) {
            $logo_data = base64_encode(file_get_contents(__DIR__ . '/../logo.png'));
        }

        $subject_names = array_column($subjects, 'subject_name');
        $subject_headers = '';
        foreach ($subject_names as $subject_name) {
            $subject_headers .= '<th>' . htmlspecialchars($subject_name) . '</th>';
        }

        // Generate HTML report
        $report_html = "
        <html>
        <head>
            <title>Student Report - $report_term</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
                .logo { max-height: 90px; margin-bottom: 15px; }
                .school-name { font-size: 24px; font-weight: bold; color: #2c3e50; }
                .report-title { font-size: 18px; margin-top: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .average-row { background-color: #e8f4f8; font-weight: bold; }
                .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='header'>
                " . ($logo_data ? "<img class='logo' src=\"data:image/png;base64,$logo_data\" alt=\"Bright Horizon Logo\" />" : "") . "
                <div class='school-name'>Bright Horizon Primary School</div>
                <div class='report-title'>Academic Report - $report_term</div>
                <div>Class: " . htmlspecialchars($class_name) . "</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Admission No</th>
                        <th>Student Name</th>
                        " . $subject_headers . "
                        <th>Average</th>
                    </tr>
                </thead>
                <tbody>";

        foreach ($report_data as $student) {
            $grades = [];
            if ($student['grades']) {
                $grade_pairs = explode('|', $student['grades']);
                foreach ($grade_pairs as $pair) {
                    list($subject, $mark) = explode(':', $pair);
                    $grades[$subject] = $mark;
                }
            }

            $marks = [];
            $total = 0;
            $count = 0;

            foreach ($subject_names as $subj) {
                $mark = isset($grades[$subj]) ? $grades[$subj] : '-';
                $marks[] = $mark;
                if ($mark !== '-') {
                    $total += $mark;
                    $count++;
                }
            }

            $average = $count > 0 ? number_format($total / $count, 1) : '-';

            $report_html .= "
                    <tr>
                        <td>" . htmlspecialchars($student['admission_no']) . "</td>
                        <td>" . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . "</td>";

            foreach ($marks as $mark_value) {
                $report_html .= "<td>" . $mark_value . "</td>";
            }

            $report_html .= "
                        <td class='average-row'>" . $average . "</td>
                    </tr>";
        }

        $report_html .= "
                </tbody>
            </table>
            <div class='footer'>
                <p>Report generated on " . date('F d, Y') . " by " . htmlspecialchars($teacher_id) . "</p>
            </div>
        </body>
        </html>";

        // Output the report
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="report_' . $report_term . '_' . date('Y-m-d') . '.html"');
        echo $report_html;
        exit;
    }
}

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'save_mark') {
        $class_filter = isset($_POST['class_id']) ? (int)$_POST['class_id'] : $class_filter;
        $student_id = (int)$_POST['student_id'];
        $subject_id = (int)$_POST['subject_id'];
        $mark = (float)$_POST['mark'];
        $term = $_POST['term'] ?? 'Term 1';

        if ($mark < 0 || $mark > 100) {
            $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Mark must be between 0 and 100</div>";
        } else {
        try {
            $check = $pdo->prepare("SELECT grade_id FROM grades WHERE student_id = ? AND subject_id = ? AND term = ?");
            $check->execute([$student_id, $subject_id, $term]);
            $existing = $check->fetch();

            if ($existing) {
                $update = $pdo->prepare("UPDATE grades SET mark = ? WHERE grade_id = ?");
                $update->execute([$mark, $existing['grade_id']]);
            } else {
                $insert = $pdo->prepare("INSERT INTO grades (student_id, subject_id, teacher_id, mark, term) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([$student_id, $subject_id, $teacher_id, $mark, $term]);
            }
            $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Mark saved successfully!</div>";
        } catch (Exception $e) {
            $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error saving mark: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enter Marks - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content-wrapper { margin-left: 260px; padding: 30px; }
        .form-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; }
        .form-group select { padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 100%; max-width: 300px; }
        .marks-table { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th { background: var(--primary-dark); color: white; padding: 15px; text-align: left; font-weight: 600; }
        td { padding: 15px; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #f9f9f9; }
        .marks-form { display: flex; gap: 10px; align-items: end; }
        .marks-form input { padding: 8px; border: 1px solid #ddd; border-radius: 6px; width: 80px; }
        .marks-form button { padding: 8px 15px; background: #27ae60; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .alert { padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.error { background: #f8d7da; color: #721c24; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
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
            <li><a href="teacher_students.php"><i class="fas fa-user-graduate"></i> My Students</a></li>
            <li><a href="teacher_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="teacher_marks.php" class="active"><i class="fas fa-pen-nib"></i> Enter Marks</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="content-wrapper">
        <div style="margin-bottom: 30px;">
            <h1 style="margin: 0;"><i class="fas fa-pen-nib"></i> Enter Student Marks</h1>
            <p style="color: #666; margin: 5px 0 0 0;">Record and update student assessment marks</p>
        </div>

        <?php if ($message): echo $message; endif; ?>
        <?php if ($error): echo $error; endif; ?>

        <!-- Class Selection -->
        <div class="form-container">
            <h3 style="margin-top: 0;"><i class="fas fa-book"></i> Select Class and Student</h3>
            <form method="GET" style="display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end;">
                <div class="form-group">
                    <label for="class_id">Choose Class:</label>
                    <select id="class_id" name="class_id" required onchange="this.form.submit()">
                        <option value="">-- Select Class --</option>
                        <?php foreach ($assigned_classes as $c): ?>
                            <option value="<?php echo $c['class_id']; ?>" <?php if ($class_filter == $c['class_id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($class_filter && !empty($students)): ?>
                    <div class="form-group">
                        <label for="student_id">Choose Student:</label>
                        <select id="student_id" name="student_id" onchange="this.form.submit()" style="width: 250px;">
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>" <?php if ($selected_student_id == $student['student_id']) echo 'selected'; ?>><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="term">Term:</label>
                        <select id="term" name="term" onchange="this.form.submit()" style="width: 150px;">
                            <option value="Term 1" <?php if ($grade_term == 'Term 1') echo 'selected'; ?>>Term 1</option>
                            <option value="Term 2" <?php if ($grade_term == 'Term 2') echo 'selected'; ?>>Term 2</option>
                            <option value="Term 3" <?php if ($grade_term == 'Term 3') echo 'selected'; ?>>Term 3</option>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($class_filter && !empty($students) && $selected_student): ?>
            <div class="marks-table">
                <div style="padding: 20px; border-bottom: 2px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h2 style="margin: 0; color: var(--primary-dark);">Enter Marks for <?php echo htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']); ?></h2>
                        <p style="color: #666; margin: 5px 0 0 0;">Term: <?php echo htmlspecialchars($grade_term); ?></p>
                    </div>
                    <a href="../generate_report.php?student_id=<?php echo $selected_student_id; ?>&term=<?php echo urlencode($grade_term); ?>" target="_blank" style="background: #3498db; color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-size: 0.95rem;"><i class="fas fa-print"></i> Open Printable Report</a>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_marks">
                    <input type="hidden" name="class_id" value="<?php echo $class_filter; ?>">
                    <input type="hidden" name="student_id" value="<?php echo $selected_student_id; ?>">
                    <input type="hidden" name="term" value="<?php echo htmlspecialchars($grade_term); ?>">

                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Current Mark</th>
                                <th>Enter New Mark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                    <td style="width: 130px; text-align: center; font-weight: 600;"><?php echo isset($existing_grades[$subject['subject_id']]) ? number_format($existing_grades[$subject['subject_id']], 1) . '%' : '-'; ?></td>
                                    <td style="width: 180px;">
                                        <input type="number" name="marks[<?php echo $subject['subject_id']; ?>]" min="0" max="100" placeholder="0-100" value="<?php echo isset($existing_grades[$subject['subject_id']]) ? htmlspecialchars($existing_grades[$subject['subject_id']]) : ''; ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div style="padding: 20px; display: flex; justify-content: flex-end; gap: 10px; flex-wrap: wrap;">
                        <button type="submit" style="padding: 12px 20px; background: #27ae60; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;"><i class="fas fa-save"></i> Save All Marks</button>
                    </div>
                </form>
            </div>
        <?php elseif ($class_filter && !empty($students)): ?>
            <div class="empty-state">
                <i class="fas fa-info-circle fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                <p>Select a student to enter marks for this class.</p>
            </div>
        <?php elseif ($class_filter): ?>
            <div class="empty-state">
                <i class="fas fa-inbox fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                <p>No students in this class yet.</p>
            </div>
        <?php endif; ?>

        <!-- Report Generation -->
        <div class="form-container" style="margin-top: 40px;">
            <h3 style="margin-top: 0;"><i class="fas fa-file-pdf"></i> Open Report Generator</h3>
            <form method="GET" action="../generate_report.php">
                <div style="display: flex; gap: 20px; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="report_class">Select Student:</label>
                        <select id="report_class" name="student_id" required style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 230px;">
                            <option value="">-- Select Student --</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>" <?php if ($selected_student_id == $student['student_id']) echo 'selected'; ?>><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="report_term">Term:</label>
                        <select id="report_term" name="term" required style="padding: 10px; border: 1px solid #ddd; border-radius: 6px; width: 150px;">
                            <option value="Term 1" <?php if ($grade_term == 'Term 1') echo 'selected'; ?>>Term 1</option>
                            <option value="Term 2" <?php if ($grade_term == 'Term 2') echo 'selected'; ?>>Term 2</option>
                            <option value="Term 3" <?php if ($grade_term == 'Term 3') echo 'selected'; ?>>Term 3</option>
                        </select>
                    </div>
                    <button type="submit" style="padding: 10px 20px; background: #e74c3c; color: white; border: none; border-radius: 6px; cursor: pointer;"><i class="fas fa-download"></i> Open Report Generator</button>
                </div>
            </form>
        </div>
    </div>
