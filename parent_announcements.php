<?php
session_start();
require_once "../db.php"; 
include "../auth.php";
restrictTo(3); // Parent only
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Announcements - Bright Horizon</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .content-wrapper { margin-left: 0; padding: 30px; }
        .announcements-container { background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .announcements-header { background: var(--primary-dark); color: white; padding: 20px; border-radius: 12px 12px 0 0; }
        .announcements-header h1 { margin: 0; font-size: 1.5rem; }
        .announcement-item {
            padding: 25px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }
        .announcement-item:hover { background: #f9f9f9; }
        .announcement-item:last-child { border-bottom: none; }
        .announcement-title { font-size: 1.2rem; font-weight: 600; color: var(--primary-dark); margin-bottom: 8px; }
        .announcement-meta { font-size: 0.85rem; color: #999; margin-bottom: 12px; }
        .announcement-message { color: #333; line-height: 1.6; margin-bottom: 12px; }
        .announcement-from { background: #f0f0f0; padding: 8px 12px; border-radius: 4px; font-size: 0.85rem; display: inline-block; }
        .empty-state { text-align: center; padding: 50px 30px; color: #999; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div style="text-align: center; margin-bottom: 30px;">
             <i class="fas fa-user-parents fa-3x" style="color: var(--accent-color);"></i>
             <h2 style="margin-top: 10px;">Parent Portal</h2>
        </div>
        <ul>
            <li><a href="parent_dashboard.php"><i class="fas fa-bar-chart"></i> Academic Reports</a></li>
            <li><a href="parent_announcements.php" class="active"><i class="fas fa-bell"></i> Announcements</a></li>
        </ul>
        <a href="../logout.php" class="btn-logout" style="margin-top:auto; background:var(--accent-color); color:var(--primary-dark); text-align:center; padding:12px; border-radius:8px; text-decoration:none; font-weight:bold;">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <div style="padding: 30px;">
            <div style="margin-bottom: 30px;">
                <h1 style="margin: 0;"><i class="fas fa-bullhorn"></i> Announcements</h1>
                <p style="color: #666; margin: 5px 0 0 0;">Latest updates from teachers and admin</p>
            </div>

            <div class="announcements-container">
                <div class="announcements-header">
                    <h1 style="margin: 0;"><i class="fas fa-list"></i> Recent Announcements</h1>
                </div>

                <?php
                $announcements = $pdo->query("
                    SELECT a.*, u.username as created_by_username, t.first_name as teacher_first, t.last_name as teacher_last
                    FROM announcements a
                    LEFT JOIN users u ON a.created_by = u.user_id
                    LEFT JOIN teachers t ON u.user_id = t.user_id
                    WHERE a.audience IN ('parents', 'all')
                    ORDER BY a.created_at DESC
                    LIMIT 20
                ")->fetchAll();

                if (empty($announcements)):
                ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox fa-3x" style="opacity: 0.3; margin-bottom: 15px;"></i>
                        <p>No announcements yet. Check back soon!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="announcement-item">
                            <div class="announcement-title"><?php echo htmlspecialchars($ann['title']); ?></div>
                            <div class="announcement-meta">
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y \a\t H:i', strtotime($ann['created_at'])); ?>
                            </div>
                            <div class="announcement-message"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></div>
                            <?php if (!empty($ann['teacher_first'])): ?>
                                <div class="announcement-from">
                                    <i class="fas fa-user"></i> From: <?php echo htmlspecialchars($ann['teacher_first'] . ' ' . $ann['teacher_last']); ?>
                                </div>
                            <?php elseif (!empty($ann['created_by_username'])): ?>
                                <div class="announcement-from">
                                    <i class="fas fa-user"></i> From: <?php echo htmlspecialchars($ann['created_by_username']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
