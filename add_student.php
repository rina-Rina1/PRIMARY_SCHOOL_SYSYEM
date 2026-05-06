<?php
require_once 'db.php';

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
        // This links the student to their Class (and automatically to that class's Teacher)
        $sql = "INSERT INTO students (user_id, first_name, last_name, class_id, enrollment_date) VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$user_id, $first_name, $last_name, $class_id, $enroll_date]);

        $pdo->commit(); // Save everything to the database
        echo "<script>alert('Student, Parent, and Class/Teacher successfully linked!');</script>";
    } catch (Exception $e) {
        $pdo->rollBack(); // Undo everything if any error occurs
        die("Registration Failed: " . $e->getMessage());
    }
}

// --- FETCH DATA FOR DROPDOWNS ---
// Fetch Classes and the Teachers assigned to them
$classes = $pdo->query("SELECT c.class_id, c.class_name FROM classes c ORDER BY c.class_id ASC")->fetchAll();

// Fetch existing parents so we can link new students to them if needed
$parents = $pdo->query("SELECT user_id, username FROM users WHERE role_id = 3 AND parent_id IS NULL")->fetchAll();
?>