<?php
$host = 'localhost';
$db   = 'bright_horizon';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Shows errors instead of silent failure
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Returns data as an associative array
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Improves security
];

try {
    // We create the $pdo variable here so login.php can use it
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Create and seed default classes if needed
    try {
        // First, try to create the table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS classes (
            class_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            class_name varchar(50) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        // Check if table is empty
        $classCount = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
        if ($classCount == 0) {
            $defaultClasses = [
                'Baby Class',
                'Standard 1',
                'Standard 2',
                'Standard 3',
                'Standard 4',
                'Standard 5',
                'Standard 6',
                'Standard 7',
                'Standard 8'
            ];
            
            // Use INSERT IGNORE to avoid duplicate errors
            $insertClass = $pdo->prepare("INSERT IGNORE INTO classes (class_name) VALUES (?)");
            foreach ($defaultClasses as $className) {
                try {
                    $insertClass->execute([$className]);
                } catch (\Exception $e) {
                    // Log but continue
                    error_log("Error inserting class: " . $e->getMessage());
                }
            }
        }
    } catch (\PDOException $e) {
        // Log the error but don't fail the whole app
        error_log("Classes table initialization error: " . $e->getMessage());
    }
} catch (\PDOException $e) {
    // If the connection fails, this explains why (e.g., wrong database name)
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}