<?php
// Database configuration
$host    = 'localhost';
$db      = 'bright_horizon';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // 1. Establish the connection
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 2. Initialize the classes table structure
    $createTableSql = "CREATE TABLE IF NOT EXISTS classes (
        class_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(50) DEFAULT NULL,
        UNIQUE KEY unique_class (class_name) 
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($createTableSql);

    // 3. Seed default data if table is empty
    $classCount = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    
    if ($classCount == 0) {
        $defaultClasses = [
            'Baby Class', 'Standard 1', 'Standard 2', 'Standard 3', 
            'Standard 4', 'Standard 5', 'Standard 6', 'Standard 7', 'Standard 8'
        ];
        
        $stmt = $pdo->prepare("INSERT IGNORE INTO classes (class_name) VALUES (?)");
        
        // Start a transaction for faster/safer bulk insertion
        $pdo->beginTransaction();
        foreach ($defaultClasses as $className) {
            $stmt->execute([$className]);
        }
        $pdo->commit();
    }

} catch (\PDOException $e) {
    // Critical connection error
    error_log("Database Connection Error: " . $e->getMessage());
    die("A database error occurred. Please try again later.");
}