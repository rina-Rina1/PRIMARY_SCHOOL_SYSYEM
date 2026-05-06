<?php 
include "../auth.php"; 
require_once "../db.php"; 
restrictTo(3); 

$parent_user_id = $_SESSION['user_id'];

// Get parent record if one exists
$p_stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
$p_stmt->execute([$parent_user_id]);
$parent = $p_stmt->fetch();
$parent_id = $parent ? $parent['parent_id'] : null;

$children_map = [];

// Students linked via the parent record table
if ($parent_id) {
    $students_stmt = $pdo->prepare("
        SELECT s.student_id, s.first_name, s.last_name, s.admission_no, c.class_name, s.class_id, s.gender
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.class_id
        LEFT JOIN student_parents sp ON s.student_id = sp.student_id
        WHERE sp.parent_id = ?
        ORDER BY s.first_name
    ");
    $students_stmt->execute([$parent_id]);
    foreach ($students_stmt->fetchAll() as $child) {
        $children_map[$child['student_id']] = $child;
    }
}

// Students linked via the student user parent_id field
$direct_parent_stmt = $pdo->prepare("
    SELECT s.student_id, s.first_name, s.last_name, s.admission_no, c.class_name, s.class_id, s.gender
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.class_id
    JOIN users su ON su.user_id = s.user_id
    WHERE su.parent_id = ?
    ORDER BY s.first_name
");
$direct_parent_stmt->execute([$parent_user_id]);
foreach ($direct_parent_stmt->fetchAll() as $child) {
    $children_map[$child['student_id']] = $child;
}

$children = array_values($children_map);

// Determine which student to view
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : ($children[0]['student_id'] ?? null);

$selected_student = null;
$grades = [];

if ($selected_student_id) {
    // Verify the student belongs to this parent
    $verify_stmt = $pdo->prepare("
        SELECT s.* FROM students s
        LEFT JOIN student_parents sp ON s.student_id = sp.student_id
        LEFT JOIN users su ON su.user_id = s.user_id
        WHERE s.student_id = ? AND (sp.parent_id = ? OR su.parent_id = ?)
    ");
    $verify_stmt->execute([$selected_student_id, $parent_id, $parent_user_id]);
    $selected_student = $verify_stmt->fetch();
    
    if ($selected_student) {
        // Fetch grades for this student
        $grades_stmt = $pdo->prepare("
            SELECT g.*, sub.subject_name
            FROM grades g
            JOIN subjects sub ON g.subject_id = sub.subject_id
            WHERE g.student_id = ?
            ORDER BY sub.subject_name
        ");
        $grades_stmt->execute([$selected_student_id]);
        $grades = $grades_stmt->fetchAll();
    }
}

// If the selected student is fetched, retrieve their class name too
if ($selected_student) {
    $class_stmt = $pdo->prepare("SELECT c.class_name FROM classes c WHERE c.class_id = ?");
    $class_stmt->execute([$selected_student['class_id']]);
    $selected_student['class_name'] = $class_stmt->fetchColumn() ?: 'N/A';
}

// Handle report generation
if (isset($_GET['generate_report']) && $selected_student_id) {
    $logo_data = '';
    if (file_exists(__DIR__ . '/../logo.png')) {
        $logo_data = base64_encode(file_get_contents(__DIR__ . '/../logo.png'));
    }

    // Generate HTML report for the selected student
    $report_html = "
    <html>
    <head>
        <title>Student Report - " . htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']) . "</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
            .logo { max-height: 90px; margin-bottom: 15px; }
            .school-name { font-size: 24px; font-weight: bold; color: #2c3e50; }
            .student-name { font-size: 20px; margin-top: 10px; }
            .report-title { font-size: 16px; margin-top: 5px; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .average-row { background-color: #e8f4f8; font-weight: bold; }
            .footer { margin-top: 40px; text-align: center; font-size: 12px; color: #666; }
            .student-info { margin-bottom: 20px; }
            .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
            .info-label { font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='header'>
            " . ($logo_data ? "<img class=\"logo\" src=\"data:image/png;base64,$logo_data\" alt=\"Bright Horizon Logo\" />" : "") . "
            <div class='school-name'>Bright Horizon Primary School</div>
            <div class='student-name'>" . htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']) . "</div>
            <div class='report-title'>Academic Report - Term 1</div>
        </div>
        
        <div class='student-info'>
            <div class='info-row'>
                <span><span class='info-label'>Admission Number:</span> " . htmlspecialchars($selected_student['admission_no']) . "</span>
                <span><span class='info-label'>Class:</span> " . htmlspecialchars($selected_student['class_name']) . "</span>
            </div>
            <div class='info-row'>
                <span><span class='info-label'>Gender:</span> " . htmlspecialchars($selected_student['gender']) . "</span>
                <span><span class='info-label'>Date of Birth:</span> " . ($selected_student['dob'] ? date('M d, Y', strtotime($selected_student['dob'])) : 'N/A') . "</span>
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Mark (%)</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>";

    $total_mark = 0;
    $mark_count = 0;
    
    foreach ($grades as $g) {
        $mark = (float)$g['mark'];
        $total_mark += $mark;
        $mark_count++;
        
        // Determine grade status
        if ($mark >= 80) { $status = 'Excellent'; }
        elseif ($mark >= 70) { $status = 'Good'; }
        elseif ($mark >= 50) { $status = 'Pass'; }
        else { $status = 'Fail'; }
        
        $report_html .= "
                <tr>
                    <td>" . htmlspecialchars($g['subject_name']) . "</td>
                    <td>" . number_format($mark, 1) . "%</td>
                    <td>$status</td>
                </tr>";
    }
    
    if ($mark_count > 0) {
        $average = $total_mark / $mark_count;
        $avg_status = $average >= 80 ? 'Excellent' : ($average >= 70 ? 'Good' : ($average >= 50 ? 'Pass' : 'Fail'));
        
        $report_html .= "
                <tr class='average-row'>
                    <td>Overall Average</td>
                    <td>" . number_format($average, 1) . "%</td>
                    <td>$avg_status</td>
                </tr>";
    }

    $report_html .= "
            </tbody>
        </table>
        <div class='footer'>
            <p>Report generated on " . date('F d, Y') . "</p>
        </div>
    </body>
    </html>";

    // Output the report
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="report_' . htmlspecialchars($selected_student['first_name'] . '_' . $selected_student['last_name']) . '_' . date('Y-m-d') . '.html"');
    echo $report_html;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Parent Portal - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content-wrapper { margin-left: 0; padding: 30px; }
        .student-selector { display: flex; gap: 12px; margin: 25px 0; flex-wrap: wrap; }
        .student-tab { padding: 12px 20px; border-radius: 8px; text-decoration: none; border: 2px solid transparent; transition: all 0.3s; font-weight: 600; cursor: pointer; }
        .student-tab.active { background: var(--primary-dark); color: white; border-color: var(--primary-dark); }
        .student-tab.inactive { background: white; color: var(--primary-dark); border-color: #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .student-tab:hover { box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .info-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .info-header { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 2px solid #f0f0f0; }
        .info-item { }
        .info-label { font-weight: 600; color: #666; font-size: 0.85rem; text-transform: uppercase; }
        .info-value { font-size: 1.1rem; color: var(--primary-dark); margin-top: 5px; }
        .grades-container { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow: hidden; }
        .grades-header { background: var(--primary-dark); color: white; padding: 20px; }
        .grades-header h2 { margin: 0; font-size: 1.3rem; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f9f9f9; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #ddd; }
        td { padding: 15px; border-bottom: 1px solid #f0f0f0; }
        tr:hover { background: #f9f9f9; }
        .grade-badge { display: inline-block; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.9rem; }
        .grade-excellent { background: #d4edda; color: #155724; }
        .grade-good { background: #cfe2ff; color: #084298; }
        .grade-pass { background: #fff3cd; color: #997404; }
        .grade-fail { background: #f8d7da; color: #842029; }
        .empty-state { text-align: center; padding: 40px; color: #999; }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert.info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="text-align: center; margin-bottom: 30px;">
             <i class="fas fa-user-parents fa-3x" style="color: var(--accent-color);"></i>
             <h2 style="margin-top: 10px;">Parent Portal</h2>
        </div>
        <ul>
            <li><a href="parent_dashboard.php" class="active"><i class="fas fa-bar-chart"></i> Academic Reports</a></li>
            <li><a href="parent_announcements.php"><i class="fas fa-bell"></i> Announcements</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <div style="padding: 30px;">
            <div style="margin-bottom: 30px;">
                <h1 style="margin: 0;"><i class="fas fa-chart-bar"></i> Academic Reports</h1>
                <p style="color: #666; margin: 5px 0 0 0;">View your child's grades and performance</p>
            </div>

            <?php if (empty($children)): ?>
                <div class="alert info">
                    <i class="fas fa-info-circle"></i> No students linked to your account yet.
                </div>
            <?php else: ?>
                <!-- Student Selector -->
                <div class="student-selector">
                    <?php foreach ($children as $child): ?>
                        <form method="GET" style="display: inline;">
                            <input type="hidden" name="student_id" value="<?php echo $child['student_id']; ?>">
                            <button type="submit" class="student-tab <?php echo ($child['student_id'] == $selected_student_id) ? 'active' : 'inactive'; ?>" style="border: none; background: none; padding: 0; cursor: pointer;">
                                <i class="fas fa-user-circle"></i> 
                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                <?php if ($child['class_name']): ?>
                                    <small style="display: block; font-size: 0.8rem; margin-top: 4px;">
                                        <?php echo htmlspecialchars($child['class_name']); ?>
                                    </small>
                                <?php endif; ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>

                <?php if ($selected_student): ?>
                    <!-- Student Information -->
                    <div class="info-card">
                        <div class="info-header">
                            <div class="info-item">
                                <div class="info-label">Student Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Admission Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($selected_student['admission_no']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Class</div>
                                <div class="info-value"><?php echo htmlspecialchars($selected_student['class_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Gender</div>
                                <div class="info-value"><?php echo htmlspecialchars($selected_student['gender']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Grades Table -->
                    <div class="grades-container">
                        <div class="grades-header" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                            <h2 style="margin: 0;"><i class="fas fa-book-reader"></i> Academic Grades - Term 1</h2>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <a href="?student_id=<?php echo $selected_student_id; ?>&generate_report=1" style="background: #e74c3c; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.9rem;"><i class="fas fa-download"></i> Download Report</a>
                                <a href="../generate_report.php?student_id=<?php echo $selected_student_id; ?>&term=Term+1" target="_blank" style="background: #3498db; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.9rem;"><i class="fas fa-print"></i> Printable Report</a>
                            </div>
                        </div>

                        <?php if (empty($grades)): ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                                <p>No grades recorded yet. Grades will appear here as they are entered by teachers.</p>
                            </div>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-book"></i> Subject</th>
                                        <th style="text-align: center;"><i class="fas fa-percent"></i> Mark</th>
                                        <th style="text-align: center;"><i class="fas fa-star"></i> Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_mark = 0;
                                    $mark_count = 0;
                                    
                                    foreach ($grades as $g): 
                                        $mark = (float)$g['mark'];
                                        $total_mark += $mark;
                                        $mark_count++;
                                        
                                        // Determine grade status
                                        if ($mark >= 80) { $status = 'Excellent'; $class = 'grade-excellent'; }
                                        elseif ($mark >= 70) { $status = 'Good'; $class = 'grade-good'; }
                                        elseif ($mark >= 50) { $status = 'Pass'; $class = 'grade-pass'; }
                                        else { $status = 'Fail'; $class = 'grade-fail'; }
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($g['subject_name']); ?></td>
                                            <td style="text-align: center; font-weight: 600; font-size: 1.1rem;"><?php echo number_format($mark, 1); ?>%</td>
                                            <td style="text-align: center;">
                                                <span class="grade-badge <?php echo $class; ?>"><?php echo $status; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($mark_count > 0): ?>
                                        <tr style="background: #f0f0f0; font-weight: 600;">
                                            <td>Average Performance</td>
                                            <td style="text-align: center; color: var(--primary-dark);">
                                                <?php echo number_format($total_mark / $mark_count, 1); ?>%
                                            </td>
                                            <td></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>