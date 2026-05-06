<?php
$host = 'localhost';
$db   = 'bright_horizon'; 
$user = 'root'; 
$pass = ''; 

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (\PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$message = "";

// 1. UPDATED TABLE NAME TO 'roles'
$role_stmt = $pdo->query("SELECT role_id, role_name FROM roles");
$roles = $role_stmt->fetchAll();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role_id  = $_POST['role_id'];

    if (!empty($username) && !empty($password) && !empty($role_id)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // 2. Ensure your users table column is 'role_id' to match the roles table
        $sql = "INSERT INTO users (username, password_hash, role_id) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        try {
            if ($stmt->execute([$username, $password_hash, $role_id])) {
                $message = "User added successfully!";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Please fill in all fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add User - Bright Horizon</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; display: flex; justify-content: center; padding-top: 50px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 350px; }
        h2 { text-align: center; color: #333; margin-top: 0; }
        label { font-weight: bold; display: block; margin-top: 10px; }
        input, select { width: 100%; padding: 10px; margin: 5px 0 15px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; cursor: pointer; border-radius: 4px; font-weight: bold; }
        button:hover { background: #0056b3; }
        .success { color: #155724; background: #d4edda; padding: 10px; border-radius: 4px; text-align: center; }
        .error { color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px; text-align: center; }
    </style>
</head>
<body>

<div class="card">
    <h2>Add New User</h2>
    
    <?php if ($message): ?>
        <p class="<?php echo (strpos($message, 'successfully') !== false) ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </p>
    <?php endif; ?>

    <form method="POST">
        <label>Username</label>
        <input type="text" name="username" placeholder="e.g. jdoe" required>

        <label>Password</label>
        <input type="password" name="password" placeholder="••••••••" required>

        <label>Assign Role</label>
        <select name="role_id" required>
            <option value="">-- Choose Role --</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?php echo $role['role_id']; ?>">
                    <?php echo htmlspecialchars($role['role_name']); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Create Account</button>
    </form>
</div>

</body>
</html>