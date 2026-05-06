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
$user_id = $_SESSION['user_id'];

$message = "";
$error = "";

// Handle announcement creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'create') {
    $title = trim($_POST['title']);
    $message_text = trim($_POST['message']);
    $audience = $_POST['audience'];

    $errors = [];
    if (empty($title)) $errors[] = "Title is required";
    if (empty($message_text)) $errors[] = "Message is required";
    if (empty($audience)) $errors[] = "Audience is required";

    if (!empty($errors)) {
        $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> " . implode(", ", $errors) . "</div>";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO announcements (title, message, audience, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$title, $message_text, $audience, $user_id]);
            $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Announcement posted successfully!</div>";
        } catch (Exception $e) {
            $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// Handle announcement deletion
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    try {
        $del_stmt = $pdo->prepare("DELETE FROM announcements WHERE announcement_id = ? AND created_by = ?");
        $del_stmt->execute([$delete_id, $user_id]);
        $message = "<div class='alert success'><i class='fas fa-check-circle'></i> Announcement deleted successfully!</div>";
    } catch (Exception $e) {
        $error = "<div class='alert error'><i class='fas fa-exclamation-circle'></i> Error deleting announcement.</div>";
    }
}

// Fetch teacher's announcements
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE created_by = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$announcements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Announcements - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content-wrapper { margin-left: 260px; padding: 30px; }
        .form-container { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; }
        .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-family: inherit; }
        .form-group textarea { resize: vertical; min-height: 150px; }
        .btn-primary { background: var(--primary-dark); color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-primary:hover { background: var(--accent-color); color: var(--primary-dark); }
        .announcements-list { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .announcement-item { padding: 20px; border-bottom: 1px solid #f0f0f0; transition: background 0.2s; }
        .announcement-item:hover { background: #f9f9f9; }
        .announcement-title { font-size: 1.2rem; font-weight: 600; color: var(--primary-dark); margin-bottom: 8px; }
        .announcement-meta { font-size: 0.85rem; color: #999; margin-bottom: 12px; }
        .announcement-message { color: #333; line-height: 1.6; margin-bottom: 12px; }
        .announcement-audience { display: inline-block; padding: 4px 10px; background: #e3f2fd; color: #0d47a1; border-radius: 4px; font-size: 0.85rem; font-weight: 600; }
        .announcement-actions { text-align: right; }
        .btn-small { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 0.85rem; }
        .btn-delete { background: #e74c3c; color: white; }
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
            <li><a href="teacher_announcements.php" class="active"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="teacher_marks.php"><i class="fas fa-pen-nib"></i> Enter Marks</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="content-wrapper">
        <div style="margin-bottom: 30px;">
            <h1 style="margin: 0;"><i class="fas fa-bullhorn"></i> Announcements</h1>
            <p style="color: #666; margin: 5px 0 0 0;">Share updates and announcements with parents and students</p>
        </div>

        <?php if ($message): echo $message; endif; ?>
        <?php if ($error): echo $error; endif; ?>

        <!-- Create Announcement Form -->
        <div class="form-container">
            <h2 style="margin-top: 0; color: var(--primary-dark);"><i class="fas fa-pen"></i> Post New Announcement</h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required placeholder="e.g., Upcoming Class Test">
                </div>

                <div class="form-group">
                    <label for="message">Message *</label>
                    <textarea id="message" name="message" required placeholder="Enter your announcement message here..."></textarea>
                </div>

                <div class="form-group">
                    <label for="audience">Audience *</label>
                    <select id="audience" name="audience" required>
                        <option value="">-- Select Audience --</option>
                        <option value="parents">Parents</option>
                        <option value="students">Students</option>
                        <option value="all">All (Parents & Students)</option>
                    </select>
                </div>

                <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Post Announcement</button>
            </form>
        </div>

        <!-- Announcements List -->
        <div class="announcements-list">
            <div style="padding: 20px; border-bottom: 2px solid #f0f0f0;">
                <h2 style="margin: 0; color: var(--primary-dark);"><i class="fas fa-list"></i> Your Announcements</h2>
            </div>

            <?php if (empty($announcements)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                    <p>No announcements posted yet. Create your first announcement above!</p>
                </div>
            <?php else: ?>
                <?php foreach ($announcements as $ann): ?>
                    <div class="announcement-item">
                        <div class="announcement-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                        <div class="announcement-meta">
                            <i class="fas fa-clock"></i> <?php echo date('M d, Y \a\t H:i', strtotime($ann['created_at'])); ?>
                            <span style="margin-left: 15px;">
                                <span class="announcement-audience"><?php echo ucfirst($ann['audience']); ?></span>
                            </span>
                        </div>
                        <div class="announcement-message"><?php echo htmlspecialchars($ann['message']); ?></div>
                        <div class="announcement-actions">
                            <a href="?delete=<?php echo $ann['announcement_id']; ?>" onclick="return confirm('Delete this announcement?')" class="btn-small btn-delete"><i class="fas fa-trash"></i> Delete</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
