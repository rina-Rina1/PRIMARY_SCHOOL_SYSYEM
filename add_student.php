<?php
if (basename($_SERVER['PHP_SELF']) != "dashboard.php") {
 include 'sidebar.php';
}
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php'; // Include your database connection

// --- UPDATED REGISTRATION LOGIC ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_student_complete'])) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $s_username = trim($_POST['s_username']);
    $password = $_POST['password']; // Will be hashed below
    $class_id = $_POST['class_id'];
    $parent_id = $_POST['parent_id'];
    $enroll_date = $_POST['enrollment_date'];

    try {
        $pdo->beginTransaction(); // Start the transaction
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // A. Handle Parent Registration
        // If "new" is selected, we create a new user for the parent first
        if ($parent_id == "new") {
            $p_username = trim($_POST['p_username']);
            $p_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role_id) VALUES (?, ?, 3)");
            $p_stmt->execute([$p_username, $hashed_password]);
            $parent_id = $pdo->lastInsertId(); // Save this ID to link to the student
        }

        // B. Create the Student's User Login
        // Note: role_id 3 is used for both students and parents in this setup
        $u_stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role_id, parent_id) VALUES (?, ?, 3, ?)");
        $u_stmt->execute([$s_username, $hashed_password, $parent_id]);
        $user_id = $pdo->lastInsertId();

        // C. Insert into Students Profile Table
        // This links the student to their Class
        $admission_no = trim($_POST['admission_no']);
        $sql = "INSERT INTO students (first_name, last_name, class_id, admission_no) VALUES (?, ?, ?, ?)";
        $stmt_student = $pdo->prepare($sql);
        $stmt_student->execute([$first_name, $last_name, $class_id, $admission_no]);
        $student_id = $pdo->lastInsertId();

        // D. Link the student to parent(s) in student_parents table
        $relationship = isset($_POST['relationship']) ? $_POST['relationship'] : 'Guardian';
        $link_stmt = $pdo->prepare("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES (?, ?, ?)");
        $link_stmt->execute([$student_id, $parent_id, $relationship]);

        $pdo->commit(); // Save everything to the database
        echo "<script>alert('Student, Parent, and Class/Teacher successfully linked!');</script>";
    } catch (Exception $e) {
        $pdo->rollBack(); // Undo everything if any error occurs
        die("Registration Failed: " . $e->getMessage());
    }
}

// --- FETCH DATA FOR DROPDOWNS ---
// Fetch Classes and the Teachers assigned to them
$classes = $pdo->query("SELECT c.class_id, c.class_name, u.username as teacher_name 
                        FROM classes c 
                        LEFT JOIN teacher_class_assignments tca ON c.class_id = tca.class_id AND tca.role_in_class = 'lead'
                        LEFT JOIN teachers t ON tca.teacher_id = t.teacher_id
                        LEFT JOIN users u ON t.user_id = u.user_id")->fetchAll();

// Fetch existing parents so we can link new students to them if needed
$parents = $pdo->query("SELECT parent_id, first_name, last_name FROM parents")->fetchAll();
?>