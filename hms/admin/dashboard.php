<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
include('../include/config.php');

// ============================================================
// ADMIN AUTH — fixed broken strlen() check
// ============================================================
$adminId = (int) ($_SESSION['id'] ?? 0);
if ($adminId <= 0) {
    header('Location: logout.php');
    exit;
}

$adminChk = $con->prepare("SELECT id, username FROM admin WHERE id = ?");
$adminChk->bind_param("i", $adminId);
$adminChk->execute();
$adminData = $adminChk->get_result()->fetch_assoc();
$adminChk->close();

if (!$adminData) {
    session_destroy();
    header('Location: logout.php');
    exit;
}
$adminName = $adminData['username'];

// ============================================================
// SUMMARY COUNTS — COUNT(*) queries, no SELECT * waste
// ============================================================

// Total registered users
$stmt = $con->prepare("SELECT COUNT(*) AS c FROM users");
$stmt->execute();
$numUsers = (int) $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// New users registered this month
$stmt = $con->prepare(
    "SELECT COUNT(*) AS c FROM users
     WHERE YEAR(regDate) = YEAR(CURDATE()) AND MONTH(regDate) = MONTH(CURDATE())"
);
$stmt->execute();
$newThisMonth = (int) $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Total feedback
$stmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus");
$stmt->execute();
$totalFeedback = (int) $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Unresponded feedback
$stmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus WHERE status = 'Not Responded'");
$stmt->execute();
$totalUnread = (int) $stmt->get_result()->fetch_assoc()['c'];
$stmt->close();

// Responded feedback (for ratio bar)
$respondedFeedback = $totalFeedback - $totalUnread;
$responseRate = $totalFeedback > 0
    ? round(($respondedFeedback / $totalFeedback) * 100)
    : 0;

// ============================================================
// RECENT UNRESPONDED FEEDBACK — latest 5 rows
// ============================================================
$recentFeedbackStmt = $con->prepare(
    "SELECT id, email, topic, date_sent
     FROM contactus
     WHERE status = 'Not Responded'
     ORDER BY date_sent DESC
     LIMIT 5"
);
$recentFeedbackStmt->execute();
$recentFeedback = $recentFeedbackStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentFeedbackStmt->close();

// ============================================================
// RECENT USER REGISTRATIONS — latest 5 rows
// ============================================================
$recentUsersStmt = $con->prepare(
    "SELECT fullName, email, regDate
     FROM users
     ORDER BY regDate DESC
     LIMIT 5"
);
$recentUsersStmt->execute();
$recentUsers = $recentUsersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$recentUsersStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
    body { font-family: 'Inter', sans-serif; }
    .sidebar {
        width: 280px;
        transition: all 0.3s ease;
        background: linear-gradient(180deg, #4b6cb7 0%, #182848 100%);
    }
    .main-content {
        margin-left: 280px;
        transition: all 0.3s ease;
    }
    .nav-link { transition: all 0.3s ease; }
    .nav-link:hover { background-color: rgba(255, 255, 255, 0.1); }
    .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
        border-left: 4px solid #fff;
    }
    @media (max-width: 768px) {
        .sidebar { margin-left: -280px; }
        .sidebar.active { margin-left: 0; }
        .main-content { margin-left: 0; }
        .main-content.active { margin-left: 280px; }
    }
    select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-color: white;
        padding-right: 2.5rem;
    }
    /* Summary card hover effect */
    .summary-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .summary-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.10);
    }
    </style>
</head>
<body class="bg-gray-50">

    <!-- ============================================================
         SIDEBAR
         ============================================================ -->
    <div class="sidebar fixed h-full text-white">
        <div class="p-5 bg-[#182848]">
            <h2 class="text-xl font-bold flex items-center space-x-2">
                <img src="assets/images/biztracker.png" alt="BizTracker Logo" class="w-7 h-7 object-contain">
                <span>BizTracker</span>
            </h2>
        </div>

        <!-- Admin Profile -->
        <div class="p-4 border-b border-white/10">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                    <i class="fas fa-user-shield text-white"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="font-medium truncate"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-sm text-white/70">Administrator</p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="mt-4 px-3">
            <div class="mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Main Menu</p>
            </div>

            <a href="dashboard.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage-users.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-users w-5 text-center"></i>
                <span>Users</span>
            </a>
            <a href="user-logs.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-file-alt w-5 text-center"></i>
                <span>User Session Logs</span>
            </a>
            <a href="manage_feedback.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-envelope w-5 text-center"></i>
                <span>Feedback</span>
                <?php if ($totalUnread > 0): ?>
                    <span class="ml-auto bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded-full">
                        <?php echo $totalUnread; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="about-us.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-info-circle w-5 text-center"></i>
                <span>About Us</span>
            </a>

            <div class="mt-4 mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Account Settings</p>
            </div>
            <a href="change-password.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-lock w-5 text-center"></i>
                <span>Change Password</span>
            </a>
            <a href="logout.php" onclick="return confirmLogout()" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>Log Out</span>
            </a>
        </nav>
    </div>

    <!-- ============================================================
         MAIN CONTENT
         ============================================================ -->
    <div class="main-content min-h-screen bg-gray-100">

        <!-- Header -->
        <header class="bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white">
            <div class="h-1 bg-white/10"></div>
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <button id="sidebarToggle" class="md:hidden text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-semibold">Admin Dashboard</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">Admin</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Dashboard</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-4 py-8 max-w-6xl">

            <!-- ================================================
                 SUMMARY CARDS — 4 cards
                 ================================================ -->
            <h2 class="text-xl font-semibold text-gray-700 mb-4">
                Overview <span class="text-[#4b6cb7]">Summary</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

                <!-- Total Users -->
                <a href="manage-users.php"
                   class="summary-card bg-white rounded-lg shadow p-5 flex flex-col items-center text-center">
                    <div class="w-14 h-14 bg-blue-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-500 font-medium uppercase tracking-wide mb-1">Total Users</p>
                    <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($numUsers); ?></p>
                    <p class="text-xs text-gray-400">All registered accounts</p>
                    <span class="mt-3 text-blue-600 text-xs font-semibold hover:underline">
                        Manage Users <i class="fas fa-arrow-right ml-1"></i>
                    </span>
                </a>

                <!-- New This Month -->
                <a href="manage-users.php"
                   class="summary-card bg-white rounded-lg shadow p-5 flex flex-col items-center text-center">
                    <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-user-plus text-green-600 text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-500 font-medium uppercase tracking-wide mb-1">New This Month</p>
                    <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($newThisMonth); ?></p>
                    <p class="text-xs text-gray-400"><?php echo date('F Y'); ?> registrations</p>
                    <span class="mt-3 text-green-600 text-xs font-semibold hover:underline">
                        View Users <i class="fas fa-arrow-right ml-1"></i>
                    </span>
                </a>

                <!-- Total Feedback -->
                <a href="manage_feedback.php"
                   class="summary-card bg-white rounded-lg shadow p-5 flex flex-col items-center text-center">
                    <div class="w-14 h-14 bg-purple-100 rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-comments text-purple-600 text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-500 font-medium uppercase tracking-wide mb-1">Total Feedback</p>
                    <p class="text-3xl font-bold text-gray-800 mb-1"><?php echo number_format($totalFeedback); ?></p>
                    <p class="text-xs text-gray-400">All messages received</p>
                    <span class="mt-3 text-purple-600 text-xs font-semibold hover:underline">
                        View Feedback <i class="fas fa-arrow-right ml-1"></i>
                    </span>
                </a>

                <!-- Unresponded Feedback -->
                <a href="manage_feedback.php?filter=unresponded"
                   class="summary-card bg-white rounded-lg shadow p-5 flex flex-col items-center text-center">
                    <div class="w-14 h-14 <?php echo $totalUnread > 0 ? 'bg-yellow-100' : 'bg-gray-100'; ?> rounded-full flex items-center justify-center mb-3">
                        <i class="fas fa-envelope-open-text <?php echo $totalUnread > 0 ? 'text-yellow-500' : 'text-gray-400'; ?> text-2xl"></i>
                    </div>
                    <p class="text-sm text-gray-500 font-medium uppercase tracking-wide mb-1">Pending Reply</p>
                    <p class="text-3xl font-bold <?php echo $totalUnread > 0 ? 'text-yellow-600' : 'text-gray-800'; ?> mb-1">
                        <?php echo number_format($totalUnread); ?>
                    </p>
                    <p class="text-xs text-gray-400">Awaiting your response</p>
                    <span class="mt-3 <?php echo $totalUnread > 0 ? 'text-yellow-600' : 'text-gray-400'; ?> text-xs font-semibold hover:underline">
                        <?php echo $totalUnread > 0 ? 'Respond Now' : 'All caught up'; ?>
                        <?php if ($totalUnread > 0): ?><i class="fas fa-arrow-right ml-1"></i><?php endif; ?>
                    </span>
                </a>

            </div>

            <!-- ================================================
                 RESPONSE RATE BAR
                 ================================================ -->
            <?php if ($totalFeedback > 0): ?>
            <div class="bg-white rounded-lg shadow p-5 mb-8">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-sm font-semibold text-gray-700">Feedback Response Rate</p>
                    <span class="text-sm font-bold <?php echo $responseRate >= 80 ? 'text-green-600' : ($responseRate >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                        <?php echo $responseRate; ?>%
                        (<?php echo number_format($respondedFeedback); ?> of <?php echo number_format($totalFeedback); ?> responded)
                    </span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                    <div class="h-3 rounded-full transition-all duration-500
                        <?php echo $responseRate >= 80 ? 'bg-green-500' : ($responseRate >= 50 ? 'bg-yellow-400' : 'bg-red-500'); ?>"
                         style="width: <?php echo $responseRate; ?>%">
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ================================================
                 BOTTOM TWO-COLUMN LAYOUT
                 ================================================ -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- Recent Unresponded Feedback -->
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-700">
                            Pending <span class="text-[#4b6cb7]">Feedback</span>
                        </h3>
                        <a href="manage_feedback.php?filter=unresponded"
                           class="text-xs text-blue-600 hover:underline font-medium">
                            View all <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <?php if (empty($recentFeedback)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-check-circle text-green-400 text-3xl mb-2"></i>
                            <p class="text-gray-500 text-sm">All feedback has been responded to.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-100">
                            <?php foreach ($recentFeedback as $fb): ?>
                            <div class="py-3 flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3 min-w-0">
                                    <div class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <i class="fas fa-envelope text-yellow-500 text-xs"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-gray-800 truncate">
                                            <?php echo htmlspecialchars($fb['topic'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <p class="text-xs text-gray-400 truncate">
                                            <?php echo htmlspecialchars($fb['email'], ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <p class="text-xs text-gray-400 mt-0.5">
                                            <?php echo date('d M Y, H:i', strtotime($fb['date_sent'])); ?>
                                        </p>
                                    </div>
                                </div>
                                <a href="manage_feedback.php?filter=unresponded"
                                   class="flex-shrink-0 px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                    Reply
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent User Registrations -->
                <div class="bg-white rounded-lg shadow p-5">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-700">
                            Recent <span class="text-[#4b6cb7]">Registrations</span>
                        </h3>
                        <a href="manage-users.php"
                           class="text-xs text-blue-600 hover:underline font-medium">
                            View all <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <?php if (empty($recentUsers)): ?>
                        <div class="text-center py-8">
                            <p class="text-gray-500 text-sm">No users registered yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-100">
                            <?php foreach ($recentUsers as $user): ?>
                            <div class="py-3 flex items-center gap-3">
                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-user text-blue-500 text-xs"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-800 truncate">
                                        <?php echo htmlspecialchars($user['fullName'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                    <p class="text-xs text-gray-400 truncate">
                                        <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>
                                </div>
                                <p class="text-xs text-gray-400 flex-shrink-0">
                                    <?php echo date('d M Y', strtotime($user['regDate'])); ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </main>
    </div><!-- end .main-content -->

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script>
        /* ---------- Sidebar toggle ---------- */
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar       = document.querySelector('.sidebar');
            const mainContent   = document.querySelector('.main-content');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('active');
                });
            }
        });

        function confirmLogout() {
            return confirm('Are you sure you want to log out?');
        }
    </script>

</body>
</html>