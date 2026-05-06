<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "bright_horizon");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

/* ================= FUNCTIONS ================= */

function getGrade($score) {
    if ($score >= 80) return "A";
    if ($score >= 70) return "B";
    if ($score >= 60) return "C";
    if ($score >= 50) return "D";
    return "F";
}

function getComment($avg) {
    if ($avg >= 80) return "Excellent";
    if ($avg >= 70) return "Very Good";
    if ($avg >= 60) return "Good";
    if ($avg >= 50) return "Fair";
    return "Needs Improvement";
}

/* ================= INPUT ================= */

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$term = isset($_GET['term']) ? $_GET['term'] : '';

?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Report</title>

    <style>
        body { font-family: Arial; margin: 30px; }

        .header {
            text-align: center;
        }

        .logo {
            width: 90px;
        }

        .report {
            border: 1px solid black;
            padding: 20px;
            margin-top: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid black;
        }

        th, td {
            padding: 10px;
            text-align: center;
        }

        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }

        button {
            margin-top: 10px;
            padding: 8px 15px;
        }

        .back {
            display: inline-block;
            margin-top: 15px;
        }

        /* PRINT STYLE */
        @media print {
            button, form, .back {
                display: none;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>

<body>

<?php if (!$student_id || !$term): ?>

<!-- ================= FORM ================= -->
<h2>Bright Horizon Primary School</h2>
<h3>Generate Student Report</h3>

<form method="GET">
    <select name="student_id" required>
        <option value="">Select Student</option>
        <?php
        $students = $conn->query("SELECT student_id, first_name, last_name FROM students");
        while($s = $students->fetch_assoc()):
        ?>
        <option value="<?= $s['student_id']; ?>">
            <?= $s['first_name']." ".$s['last_name']; ?>
        </option>
        <?php endwhile; ?>
    </select>

    <select name="term" required>
        <option value="">Select Term</option>
        <option>Term 1</option>
        <option>Term 2</option>
        <option>Term 3</option>
    </select>

    <button type="submit">Generate Report</button>
</form>

<?php else: ?>

<?php
    /* ================= STUDENT ================= */
    $student = $conn->query("
        SELECT st.*, c.class_name 
        FROM students st
        JOIN classes c ON st.class_id = c.class_id
        WHERE st.student_id = $student_id
    ")->fetch_assoc();

    $term_safe = $conn->real_escape_string($term);

    /* ================= RESULTS ================= */
    $results = [];
    $total = 0;
    $count = 0;

   $res = $conn->query("
    SELECT sub.subject_name, g.mark
    FROM grades g
    JOIN subjects sub ON g.subject_id = sub.subject_id
    WHERE g.student_id = $student_id AND g.term = '$term_safe'
    ");

    while($row = $res->fetch_assoc()){
        $row['grade'] = getGrade($row['mark']);
        $results[] = $row;
        $total += $row['mark'];
        $count++;
    }

    $average = $count ? $total / $count : 0;
    $comment = getComment($average);

    /* ================= POSITION ================= */
    $position = "-";
    $class_id = $student['class_id'];

    $all = $conn->query("
        SELECT st.student_id, AVG(g.mark) as avg_score
        FROM students st
        JOIN grades g ON st.student_id = g.student_id
        WHERE st.class_id = $class_id AND g.term = '$term_safe'
        GROUP BY st.student_id
        ORDER BY avg_score DESC
    ");

    $rank = 1;
    while($row = $all->fetch_assoc()){
        if ($row['student_id'] == $student_id) {
            $position = $rank;
        }
        $rank++;
    }
?>

<!-- ================= REPORT ================= -->

<div class="report">

    <div class="header">
        <img src="logo.png" class="logo">
        <h2>Bright Horizon Primary School</h2>
        <h3>Student Report Card</h3>
    </div>

    <p><strong>Name:</strong> <?= $student['first_name']." ".$student['last_name']; ?></p>
    <p><strong>Class:</strong> <?= $student['class_name']; ?></p>
    <p><strong>Term:</strong> <?= $term; ?></p>
    <p><strong>Position:</strong> <?= $position; ?></p>

    <table>
        <tr>
            <th>Subject</th>
            <th>Mark</th>
            <th>Grade</th>
        </tr>

        <?php foreach($results as $r): ?>
        <tr>
            <td><?= $r['subject_name']; ?></td>
            <td><?= $r['mark']; ?></td>
            <td><?= $r['grade']; ?></td>
        </tr>
        <?php endforeach; ?>

        <tr>
            <th colspan="2">Average</th>
            <th><?= number_format($average,2); ?></th>
        </tr>

        <tr>
            <th colspan="3">Comment: <?= $comment; ?></th>
        </tr>
    </table>

    <div class="footer">
        <div>
            ___________________<br>
            Class Teacher
        </div>

        <div>
            ___________________<br>
            Head Teacher
        </div>
    </div>

    <!-- ACTIONS -->
    <div>
        <button onclick="window.print()">🖨 Print</button>
    </div>

    <a href="generate_report.php" class="back">⬅ Back</a>

</div>

<?php endif; ?>

</body>
</html>