<?php
session_start();
include('../include/config.php');

// ============================================================
// ADMIN AUTHENTICATION
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
// CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// HELPERS
// ============================================================
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ============================================================
// SAFE SORT / ORDER — whitelist
// ============================================================
$allowedSorts = ['date_sent', 'topic', 'status', 'email'];
$sort  = (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts, true))
         ? $_GET['sort'] : 'date_sent';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'desc') ? 'DESC' : 'ASC';

// ============================================================
// PAGINATION
// ============================================================
$recordsPerPage = 5;
$page   = (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0)
          ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// ============================================================
// FILTER & SEARCH
// ============================================================
$allowedFilters = ['all', 'responded', 'unresponded'];
$filter = (isset($_GET['filter']) && in_array($_GET['filter'], $allowedFilters, true))
          ? $_GET['filter'] : 'all';
$search = trim($_GET['search'] ?? '');
$like   = '%' . $search . '%';

// ============================================================
// HANDLE REPLY SUBMISSION — POST with CSRF
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reply'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    $feedbackId   = (int) ($_POST['feedback_id'] ?? 0);
    $adminRemarks = trim($_POST['admin_remarks'] ?? '');

    if ($feedbackId <= 0) {
        setFlash('error', 'Invalid feedback ID.');
    } elseif (empty($adminRemarks)) {
        setFlash('error', 'Remarks cannot be empty.');
    } elseif (strlen($adminRemarks) > 2000) {
        setFlash('error', 'Remarks must be 2000 characters or fewer.');
    } else {
        $dupChk = $con->prepare("SELECT id FROM replies WHERE feedback_id = ?");
        $dupChk->bind_param("i", $feedbackId);
        $dupChk->execute();
        $dupChk->store_result();

        if ($dupChk->num_rows > 0) {
            setFlash('error', 'This feedback has already been responded to.');
        } else {
            $insStmt = $con->prepare(
                "INSERT INTO replies (feedback_id, admin_remarks, date_replied) VALUES (?, ?, NOW())"
            );
            $insStmt->bind_param("is", $feedbackId, $adminRemarks);
            $insStmt->execute();
            $insStmt->close();

            $updStmt = $con->prepare("UPDATE contactus SET status = 'Responded' WHERE id = ?");
            $updStmt->bind_param("i", $feedbackId);
            $updStmt->execute();
            $updStmt->close();

            setFlash('success', 'Reply submitted successfully!');
        }
        $dupChk->close();
    }

    header("Location: manage_feedback.php?filter=" . urlencode($filter) . "&search=" . urlencode($search));
    exit();
}

// ============================================================
// HANDLE EDIT REPLY — POST with CSRF
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_reply'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $feedbackId   = (int) ($_POST['feedback_id'] ?? 0);
    $adminRemarks = trim($_POST['admin_remarks'] ?? '');

    if ($feedbackId <= 0 || empty($adminRemarks)) {
        setFlash('error', 'Invalid data for editing reply.');
    } elseif (strlen($adminRemarks) > 2000) {
        setFlash('error', 'Remarks must be 2000 characters or fewer.');
    } else {
        $editStmt = $con->prepare(
            "UPDATE replies SET admin_remarks = ?, date_replied = NOW() WHERE feedback_id = ?"
        );
        $editStmt->bind_param("si", $adminRemarks, $feedbackId);
        $editStmt->execute();
        $editStmt->close();
        setFlash('success', 'Reply updated successfully!');
    }

    header("Location: manage_feedback.php?filter=" . urlencode($filter) . "&search=" . urlencode($search));
    exit();
}

// ============================================================
// HANDLE DELETE FEEDBACK — POST with CSRF
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $delId = (int) ($_POST['delete_id'] ?? 0);
    if ($delId > 0) {
        $delReply = $con->prepare("DELETE FROM replies WHERE feedback_id = ?");
        $delReply->bind_param("i", $delId);
        $delReply->execute();
        $delReply->close();

        $delStmt = $con->prepare("DELETE FROM contactus WHERE id = ?");
        $delStmt->bind_param("i", $delId);
        if ($delStmt->execute()) {
            setFlash('success', 'Feedback deleted successfully!');
        } else {
            setFlash('error', 'Failed to delete feedback.');
        }
        $delStmt->close();
    }

    header("Location: manage_feedback.php?filter=" . urlencode($filter) . "&search=" . urlencode($search));
    exit();
}

// ============================================================
// SUMMARY COUNTS — safe if/else, no closures, no spread operators
// ============================================================
$totalStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus");
$totalStmt->execute();
$totalFeedback = (int) $totalStmt->get_result()->fetch_assoc()['c'];
$totalStmt->close();

$respStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus WHERE status = 'Responded'");
$respStmt->execute();
$totalResponded = (int) $respStmt->get_result()->fetch_assoc()['c'];
$respStmt->close();

$totalUnresponded = $totalFeedback - $totalResponded;
$responseRate     = $totalFeedback > 0
                    ? round(($totalResponded / $totalFeedback) * 100)
                    : 0;

$todayStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus WHERE DATE(date_sent) = CURDATE()");
$todayStmt->execute();
$todayCount = (int) $todayStmt->get_result()->fetch_assoc()['c'];
$todayStmt->close();

// Sidebar badge — unresponded user feedback only
$totalUnread = $totalUnresponded;

// ============================================================
// FILTER TAB COUNTS — safe if/else, no closures, no spread
// ============================================================
if (!empty($search)) {
    $acStmt = $con->prepare(
        "SELECT COUNT(*) AS c FROM contactus c
         WHERE (c.topic LIKE ? OR c.email LIKE ? OR c.date_sent LIKE ?)"
    );
    $acStmt->bind_param("sss", $like, $like, $like);
} else {
    $acStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus c");
}
$acStmt->execute();
$allCount = (int) $acStmt->get_result()->fetch_assoc()['c'];
$acStmt->close();

if (!empty($search)) {
    $rcStmt = $con->prepare(
        "SELECT COUNT(*) AS c FROM contactus c
         WHERE c.status = 'Responded'
         AND (c.topic LIKE ? OR c.email LIKE ? OR c.date_sent LIKE ?)"
    );
    $rcStmt->bind_param("sss", $like, $like, $like);
} else {
    $rcStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus c WHERE c.status = 'Responded'");
}
$rcStmt->execute();
$respondedCount = (int) $rcStmt->get_result()->fetch_assoc()['c'];
$rcStmt->close();

if (!empty($search)) {
    $ucStmt = $con->prepare(
        "SELECT COUNT(*) AS c FROM contactus c
         WHERE c.status = 'Not Responded'
         AND (c.topic LIKE ? OR c.email LIKE ? OR c.date_sent LIKE ?)"
    );
    $ucStmt->bind_param("sss", $like, $like, $like);
} else {
    $ucStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus c WHERE c.status = 'Not Responded'");
}
$ucStmt->execute();
$unrespondedCount = (int) $ucStmt->get_result()->fetch_assoc()['c'];
$ucStmt->close();

// ============================================================
// COUNT QUERY — for pagination
// ============================================================
if (!empty($search) && $filter === 'responded') {
    $cntStmt = $con->prepare(
        "SELECT COUNT(*) AS total FROM contactus c
         WHERE c.status = 'Responded'
         AND (c.topic LIKE ? OR c.email LIKE ? OR c.date_sent LIKE ?)"
    );
    $cntStmt->bind_param("sss", $like, $like, $like);
} elseif (!empty($search) && $filter === 'unresponded') {
    $cntStmt = $con->prepare(
        "SELECT COUNT(*) AS total FROM contactus c
         WHERE c.status = 'Not Responded'
         AND (c.topic LIKE ? OR c.email LIKE ? OR c.date_sent LIKE ?)"
    );
    $cntStmt->bind_param("sss", $like, $like, $like);
} elseif (!empty($search)) {
    $cntStmt = $con->prepare(
        "SELECT COUNT(*) AS total FROM contactus c
         WHERE (c.topic LIKE ? OR c.email LIKE ? OR c.date_sent LIKE ?)"
    );
    $cntStmt->bind_param("sss", $like, $like, $like);
} elseif ($filter === 'responded') {
    $cntStmt = $con->prepare("SELECT COUNT(*) AS total FROM contactus c WHERE c.status = 'Responded'");
} elseif ($filter === 'unresponded') {
    $cntStmt = $con->prepare("SELECT COUNT(*) AS total FROM contactus c WHERE c.status = 'Not Responded'");
} else {
    $cntStmt = $con->prepare("SELECT COUNT(*) AS total FROM contactus c");
}
$cntStmt->execute();
$totalRows = (int) $cntStmt->get_result()->fetch_assoc()['total'];
$cntStmt->close();

$totalPages = max(1, (int) ceil($totalRows / $recordsPerPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $recordsPerPage; }

// ============================================================
// DATA QUERY — LEFT JOIN replies, safe if/else branches
// ============================================================
$baseSQL = "SELECT c.*, r.admin_remarks, r.date_replied
            FROM contactus c
            LEFT JOIN replies r ON r.feedback_id = c.id";

if (!empty($search) && $filter === 'responded') {
    $dataStmt = $con->prepare(
        "$baseSQL WHERE c.status = 'Responded'
         AND (c.topic LIKE ? OR c.email LIKE ? OR c.date_sent LIKE ?)
         ORDER BY c.$sort $order LIMIT ? OFFSET ?"
    );
    $dataStmt->bind_param("sssii", $like, $like, $like, $recordsPerPage, $offset);
} elseif (!empty($search) && $filter === 'unresponded') {
    $dataStmt = $con->prepare(
        "$baseSQL WHERE c.status = 'Not Responded'
         AND (c.topic LIKE ? OR c.email LIKE ? OR c.date_sent LIKE ?)
         ORDER BY c.$sort $order LIMIT ? OFFSET ?"
    );
    $dataStmt->bind_param("sssii", $like, $like, $like, $recordsPerPage, $offset);
} elseif (!empty($search)) {
    $dataStmt = $con->prepare(
        "$baseSQL WHERE (c.topic LIKE ? OR c.email LIKE ? OR c.date_sent LIKE ?)
         ORDER BY c.$sort $order LIMIT ? OFFSET ?"
    );
    $dataStmt->bind_param("sssii", $like, $like, $like, $recordsPerPage, $offset);
} elseif ($filter === 'responded') {
    $dataStmt = $con->prepare(
        "$baseSQL WHERE c.status = 'Responded'
         ORDER BY c.$sort $order LIMIT ? OFFSET ?"
    );
    $dataStmt->bind_param("ii", $recordsPerPage, $offset);
} elseif ($filter === 'unresponded') {
    $dataStmt = $con->prepare(
        "$baseSQL WHERE c.status = 'Not Responded'
         ORDER BY c.$sort $order LIMIT ? OFFSET ?"
    );
    $dataStmt->bind_param("ii", $recordsPerPage, $offset);
} else {
    $dataStmt = $con->prepare(
        "$baseSQL ORDER BY c.$sort $order LIMIT ? OFFSET ?"
    );
    $dataStmt->bind_param("ii", $recordsPerPage, $offset);
}
$dataStmt->execute();
$feedbackRows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Feedback</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
    body { font-family: 'Inter', sans-serif; }
    .sidebar {
        width: 280px; transition: all 0.3s ease;
        background: linear-gradient(180deg, #4b6cb7 0%, #182848 100%);
    }
    .main-content { margin-left: 280px; transition: all 0.3s ease; }
    .nav-link { transition: all 0.3s ease; }
    .nav-link:hover { background-color: rgba(255,255,255,0.1); }
    .nav-link.active { background-color: rgba(255,255,255,0.1); border-left: 4px solid #fff; }
    @media (max-width: 768px) {
        .sidebar { margin-left: -280px; } .sidebar.active { margin-left: 0; }
        .main-content { margin-left: 0; } .main-content.active { margin-left: 280px; }
    }
    select { appearance: none; -webkit-appearance: none; -moz-appearance: none; background-color: white; padding-right: 2.5rem; }

    /* Filter tabs */
    .filter-tabs { display: flex; border-bottom: 1px solid #e2e8f0; margin-bottom: 1rem; flex-wrap: wrap; }
    .filter-tab {
        padding: 0.5rem 1rem; cursor: pointer;
        border-bottom: 2px solid transparent; margin-right: 0.5rem;
        text-decoration: none; color: inherit;
    }
    .filter-tab.active { border-bottom-color: #4b6cb7; color: #4b6cb7; font-weight: 600; }
    .filter-tab:hover { background-color: #f7fafc; }
    .count-badge {
        background-color: #e2e8f0; border-radius: 9999px;
        padding: 0.15rem 0.5rem; font-size: 0.75rem; margin-left: 0.25rem;
    }
    .char-counter { font-size: 12px; color: #6b7280; text-align: right; margin-top: 2px; }
    .char-counter.warn { color: #d97706; }
    .char-counter.over { color: #dc2626; }

    /* Three-dot action dropdown */
    .action-menu { position: relative; display: inline-block; }
    .action-trigger {
        width: 32px; height: 32px; border-radius: 6px;
        border: 1px solid #e5e7eb; background: white; color: #6b7280;
        cursor: pointer; display: flex; align-items: center; justify-content: center;
        font-size: 18px; font-weight: 700; letter-spacing: 1px;
        transition: background .15s, border-color .15s, color .15s; user-select: none;
    }
    .action-trigger:hover { background: #f3f4f6; border-color: #d1d5db; color: #374151; }
    .action-trigger.open  { background: #eff6ff; border-color: #93c5fd; color: #1d4ed8; }
    .action-dropdown {
        display: none; position: fixed;
        min-width: 150px; background: white; border: 1px solid #e5e7eb;
        border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,.15);
        z-index: 9999; overflow: hidden;
        transform: scale(.95); opacity: 0;
        transition: transform .12s ease, opacity .12s ease;
    }
    .action-dropdown.open { display: block; transform: scale(1); opacity: 1; }
    .action-item {
        display: flex; align-items: center; gap: 9px; padding: 9px 14px;
        font-size: 13px; font-weight: 500; cursor: pointer; border: none;
        background: transparent; width: 100%; text-align: left;
        text-decoration: none; color: #374151; transition: background .1s;
    }
    .action-item:hover { background: #f9fafb; }
    .item-icon {
        width: 28px; height: 28px; border-radius: 6px;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; flex-shrink: 0;
    }
    .item-blue   .item-icon { background: #eff6ff; color: #2563eb; }
    .item-yellow .item-icon { background: #fefce8; color: #a16207; }
    .item-red    .item-icon { background: #fef2f2; color: #dc2626; }
    .item-blue:hover   { color: #1d4ed8; }
    .item-yellow:hover { color: #854d0e; }
    .item-red:hover    { color: #b91c1c; background: #fff5f5; }
    .dropdown-divider { height: 1px; background: #f3f4f6; margin: 2px 0; }

    /* Quick date filter tabs */
    .quick-date-tab {
        padding: 3px 12px; border-radius: 9999px; font-size: 12px; font-weight: 500;
        cursor: pointer; border: 1px solid #d1d5db; background: white;
        transition: all 0.2s; text-decoration: none; color: #374151; display: inline-block;
    }
    .quick-date-tab:hover { background: #f3f4f6; }
    .quick-date-tab.active { background: #4b6cb7; color: white; border-color: #4b6cb7; }

    /* Sort column links */
    .sort-col-link { font-size: 11px; margin-left: 3px; text-decoration: none; color: #9ca3af; }
    .sort-col-link:hover { color: #4b6cb7; }
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
        <nav class="mt-4 px-3">
            <div class="mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Main Menu</p>
            </div>
            <a href="dashboard.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-tachometer-alt w-5 text-center"></i><span>Dashboard</span>
            </a>
            <a href="manage-users.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-users w-5 text-center"></i><span>Users</span>
            </a>
            <a href="user-logs.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-file-alt w-5 text-center"></i><span>User Session Logs</span>
            </a>
            <a href="manage_feedback.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-envelope w-5 text-center"></i>
                <span>Feedback</span>
                <?php if ($totalUnread > 0): ?>
                    <span class="ml-auto bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded-full">
                        <?php echo $totalUnread; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="about-us.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-info-circle w-5 text-center"></i><span>About Us</span>
            </a>
            <div class="mt-4 mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Account Settings</p>
            </div>
            <a href="change-password.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-lock w-5 text-center"></i><span>Change Password</span>
            </a>
            <a href="logout.php" onclick="return confirmLogout()" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-sign-out-alt w-5 text-center"></i><span>Log Out</span>
            </a>
        </nav>
    </div>

    <!-- ============================================================
         MAIN CONTENT
         ============================================================ -->
    <div class="main-content min-h-screen bg-gray-100">

        <header class="bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white">
            <div class="h-1 bg-white/10"></div>
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <button id="sidebarToggle" class="md:hidden text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-semibold">Feedback Management</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">Admin</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Manage Feedback</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-4 py-8">

            <!-- Flash message -->
            <?php if ($flash): ?>
                <div class="mb-6 p-3 rounded flex items-center gap-3
                    <?php echo $flash['type'] === 'success' ? 'bg-green-100 text-green-800 border border-green-200' : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                    <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> flex-shrink-0"></i>
                    <span><?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3">
                    <div class="p-3 bg-blue-100 rounded-full text-blue-600 flex-shrink-0">
                        <i class="fas fa-comments"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Total Feedback</p>
                        <p class="text-xl font-bold text-[#182848]"><?php echo number_format($totalFeedback); ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3">
                    <div class="p-3 bg-green-100 rounded-full text-green-600 flex-shrink-0">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Responded</p>
                        <p class="text-xl font-bold text-[#182848]"><?php echo number_format($totalResponded); ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3 <?php echo $totalUnresponded > 0 ? 'border-l-4 border-yellow-400' : ''; ?>">
                    <div class="p-3 <?php echo $totalUnresponded > 0 ? 'bg-yellow-100 text-yellow-500' : 'bg-gray-100 text-gray-400'; ?> rounded-full flex-shrink-0">
                        <i class="fas fa-envelope-open-text"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Pending Reply</p>
                        <p class="text-xl font-bold <?php echo $totalUnresponded > 0 ? 'text-yellow-600' : 'text-[#182848]'; ?>">
                            <?php echo number_format($totalUnresponded); ?>
                        </p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3">
                    <div class="p-3 bg-purple-100 rounded-full text-purple-600 flex-shrink-0">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">New Today</p>
                        <p class="text-xl font-bold text-[#182848]"><?php echo number_format($todayCount); ?></p>
                        <p class="text-xs text-gray-400"><?php echo date('d M Y'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Response Rate Bar -->
            <?php if ($totalFeedback > 0): ?>
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <div class="flex justify-between items-center mb-2">
                    <p class="text-sm font-semibold text-gray-700">Response Rate</p>
                    <span class="text-sm font-bold <?php echo $responseRate >= 80 ? 'text-green-600' : ($responseRate >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                        <?php echo $responseRate; ?>%
                        (<?php echo number_format($totalResponded); ?> of <?php echo number_format($totalFeedback); ?> responded)
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

            <!-- Main Table Card -->
            <div class="bg-white p-6 rounded shadow-lg">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                    <h2 class="text-2xl font-semibold text-gray-700">
                        Manage User <span class="text-[#4b6cb7]">Feedback</span>
                    </h2>
                    <!-- Sort selector -->
                    <form method="GET" class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>
                        <label class="text-gray-500 font-medium whitespace-nowrap">Sort by</label>
                        <select name="sort" onchange="this.form.submit()"
                                class="border border-gray-300 rounded px-3 py-1.5 text-sm bg-white focus:ring-2 focus:ring-blue-400">
                            <option value="date_sent"  <?php echo $sort === 'date_sent'  ? 'selected' : ''; ?>>Date Sent</option>
                            <option value="topic"      <?php echo $sort === 'topic'      ? 'selected' : ''; ?>>Topic</option>
                            <option value="status"     <?php echo $sort === 'status'     ? 'selected' : ''; ?>>Status</option>
                            <option value="email"      <?php echo $sort === 'email'      ? 'selected' : ''; ?>>Email</option>
                        </select>
                        <select name="order" onchange="this.form.submit()"
                                class="border border-gray-300 rounded px-3 py-1.5 text-sm bg-white focus:ring-2 focus:ring-blue-400">
                            <option value="asc"  <?php echo $order === 'ASC'  ? 'selected' : ''; ?>>↑ Ascending</option>
                            <option value="desc" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>↓ Descending</option>
                        </select>
                    </form>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <a href="manage_feedback.php?filter=all<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>&sort=<?php echo $sort; ?>&order=<?php echo strtolower($order); ?>"
                       class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All <span class="count-badge"><?php echo $allCount; ?></span>
                    </a>
                    <a href="manage_feedback.php?filter=responded<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>&sort=<?php echo $sort; ?>&order=<?php echo strtolower($order); ?>"
                       class="filter-tab <?php echo $filter === 'responded' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle text-green-500 mr-1"></i>Responded <span class="count-badge"><?php echo $respondedCount; ?></span>
                    </a>
                    <a href="manage_feedback.php?filter=unresponded<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>&sort=<?php echo $sort; ?>&order=<?php echo strtolower($order); ?>"
                       class="filter-tab <?php echo $filter === 'unresponded' ? 'active' : ''; ?>">
                        <i class="fas fa-clock text-yellow-500 mr-1"></i>Pending <span class="count-badge"><?php echo $unrespondedCount; ?></span>
                    </a>
                </div>

                <!-- Search + quick date filters -->
                <form method="GET" class="mb-4" id="searchForm">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="sort"   value="<?php echo htmlspecialchars($sort,   ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="order"  value="<?php echo strtolower($order); ?>">
                    <div class="flex gap-2 mb-3">
                        <div class="relative flex-1">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm pointer-events-none"></i>
                            <input type="text" name="search" placeholder="Search by topic, email or date..."
                                   value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                                   class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400">
                        </div>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <?php if (!empty($search)): ?>
                            <a href="manage_feedback.php?filter=<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>&sort=<?php echo $sort; ?>&order=<?php echo strtolower($order); ?>"
                               class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 text-sm">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Quick date filters -->
                    <?php
                    $qBase = array_filter(['filter' => $filter !== 'all' ? $filter : null, 'search' => $search ?: null, 'sort' => $sort !== 'date_sent' ? $sort : null, 'order' => $order !== 'ASC' ? strtolower($order) : null]);
                    $today     = date('Y-m-d');
                    $thisMonS  = date('Y-m-01');
                    $thisMonE  = date('Y-m-t');
                    $lastMonS  = date('Y-m-01', strtotime('first day of last month'));
                    $lastMonE  = date('Y-m-t',  strtotime('last day of last month'));
                    $thisYearS = date('Y-01-01');
                    $thisYearE = date('Y-12-31');
                    $df = trim($_GET['date_from'] ?? '');
                    $dt = trim($_GET['date_to']   ?? '');
                    ?>
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="text-xs text-gray-500 font-medium">Quick date:</span>
                        <a href="?<?php echo http_build_query(array_merge($qBase, [])); ?>"
                           class="quick-date-tab <?php echo empty($df) ? 'active' : ''; ?>">All time</a>
                        <a href="?<?php echo http_build_query(array_merge($qBase, ['date_from' => $today, 'date_to' => $today])); ?>"
                           class="quick-date-tab <?php echo ($df === $today && $dt === $today) ? 'active' : ''; ?>">Today</a>
                        <a href="?<?php echo http_build_query(array_merge($qBase, ['date_from' => $thisMonS, 'date_to' => $thisMonE])); ?>"
                           class="quick-date-tab <?php echo ($df === $thisMonS && $dt === $thisMonE) ? 'active' : ''; ?>">This month</a>
                        <a href="?<?php echo http_build_query(array_merge($qBase, ['date_from' => $lastMonS, 'date_to' => $lastMonE])); ?>"
                           class="quick-date-tab <?php echo ($df === $lastMonS && $dt === $lastMonE) ? 'active' : ''; ?>">Last month</a>
                        <a href="?<?php echo http_build_query(array_merge($qBase, ['date_from' => $thisYearS, 'date_to' => $thisYearE])); ?>"
                           class="quick-date-tab <?php echo ($df === $thisYearS && $dt === $thisYearE) ? 'active' : ''; ?>">This year</a>
                        <?php if (!empty($df)): ?>
                            <span class="text-xs text-blue-600 bg-blue-50 border border-blue-200 px-2 py-1 rounded-full">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <?php echo date('d M Y', strtotime($df)); ?> — <?php echo date('d M Y', strtotime($dt)); ?>
                                <a href="?<?php echo http_build_query($qBase); ?>" class="ml-1 text-blue-400 hover:text-red-500">×</a>
                            </span>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <?php if ($totalRows === 0): ?>
                        <div class="text-center py-10">
                            <i class="fas fa-inbox text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">
                                <?php
                                if ($filter === 'responded')       echo "No responded feedback found.";
                                elseif ($filter === 'unresponded') echo "No pending feedback found.";
                                else                               echo "No feedback submitted yet.";
                                ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php
                        // Build sort links
                        $sortBase = array_filter(['filter' => $filter !== 'all' ? $filter : null, 'search' => $search ?: null, 'date_from' => $df ?: null, 'date_to' => $dt ?: null]);
                        function sortLink(array $base, string $col, string $cur, string $curOrder): string {
                            $newOrder = ($cur === $col && $curOrder === 'ASC') ? 'desc' : 'asc';
                            $params   = array_merge($base, ['sort' => $col, 'order' => $newOrder]);
                            $icon     = $cur === $col ? ($curOrder === 'ASC' ? '▲' : '▼') : '⇅';
                            $active   = $cur === $col ? 'style="color:#4b6cb7"' : '';
                            return '<a href="?' . http_build_query(array_filter($params)) . '" class="sort-col-link" ' . $active . '>' . $icon . '</a>';
                        }
                        ?>
                        <table class="min-w-full border text-sm">
                            <thead class="bg-gray-100 text-gray-700">
                                <tr>
                                    <th class="px-4 py-3 border text-center w-12">#</th>
                                    <th class="px-4 py-3 border">
                                        <span class="flex items-center gap-1">Email <?php echo sortLink($sortBase, 'email', $sort, $order); ?></span>
                                    </th>
                                    <th class="px-4 py-3 border">
                                        <span class="flex items-center gap-1">Topic <?php echo sortLink($sortBase, 'topic', $sort, $order); ?></span>
                                    </th>
                                    <th class="px-4 py-3 border text-center">
                                        <span class="flex items-center justify-center gap-1">Status <?php echo sortLink($sortBase, 'status', $sort, $order); ?></span>
                                    </th>
                                    <th class="px-4 py-3 border">
                                        <span class="flex items-center gap-1">Date <?php echo sortLink($sortBase, 'date_sent', $sort, $order); ?></span>
                                    </th>
                                    <th class="px-4 py-3 border text-center w-12">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbackRows as $i => $row):
                                    $status       = $row['status'];
                                    $adminRemarks = $row['admin_remarks'] ?? '';
                                    $replyDate    = $row['date_replied']  ?? '';
                                    $hasReply     = $status === 'Responded';
                                    $rowBg        = $hasReply ? 'hover:bg-green-50' : 'hover:bg-yellow-50';
                                ?>
                                <tr class="border-t transition-colors <?php echo $rowBg; ?>">
                                    <td class="px-4 py-3 border text-center font-semibold text-gray-500">
                                        <?php echo $offset + $i + 1; ?>
                                    </td>
                                    <td class="px-4 py-3 border">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs font-bold flex-shrink-0">
                                                <?php echo strtoupper(substr($row['email'], 0, 1)); ?>
                                            </div>
                                            <span class="truncate max-w-[160px]" title="<?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 border">
                                        <span class="block truncate max-w-[200px] font-medium text-gray-800"
                                              title="<?php echo htmlspecialchars($row['topic'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($row['topic'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                        <?php if (!empty($row['comment'])): ?>
                                            <span class="block text-xs text-gray-400 truncate max-w-[200px] mt-0.5"
                                                  title="<?php echo htmlspecialchars($row['comment'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars(mb_substr($row['comment'], 0, 60) . (mb_strlen($row['comment']) > 60 ? '…' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 border text-center">
                                        <?php if ($hasReply): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">
                                                <i class="fas fa-check-circle"></i> Responded
                                            </span>
                                            <?php if ($replyDate): ?>
                                                <div class="text-xs text-gray-400 mt-1"><?php echo date('d M Y', strtotime($replyDate)); ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs font-medium">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 border text-gray-600 whitespace-nowrap">
                                        <?php echo date('d M Y', strtotime($row['date_sent'])); ?>
                                        <div class="text-xs text-gray-400"><?php echo date('H:i', strtotime($row['date_sent'])); ?></div>
                                    </td>
                                    <td class="px-4 py-3 border text-center">
                                        <div class="action-menu flex justify-center">
                                            <button type="button" class="action-trigger" onclick="toggleMenu(this)" aria-label="Actions">&vellip;</button>
                                            <div class="action-dropdown">
                                                <!-- View / Reply -->
                                                <button type="button" class="action-item item-blue"
                                                        data-id="<?php echo (int)$row['id']; ?>"
                                                        data-email="<?php echo htmlspecialchars($row['email'],    ENT_QUOTES,'UTF-8'); ?>"
                                                        data-topic="<?php echo htmlspecialchars($row['topic'],    ENT_QUOTES,'UTF-8'); ?>"
                                                        data-comment="<?php echo htmlspecialchars($row['comment'],ENT_QUOTES,'UTF-8'); ?>"
                                                        data-remarks="<?php echo htmlspecialchars($adminRemarks,  ENT_QUOTES,'UTF-8'); ?>"
                                                        data-replied="<?php echo htmlspecialchars($replyDate,     ENT_QUOTES,'UTF-8'); ?>"
                                                        onclick="openAdminModal(this);closeAllMenus();">
                                                    <span class="item-icon"><i class="fas fa-eye"></i></span>
                                                    <?php echo $hasReply ? 'View' : 'Reply'; ?>
                                                </button>
                                                <?php if ($hasReply): ?>
                                                <div class="dropdown-divider"></div>
                                                <!-- Edit Reply -->
                                                <button type="button" class="action-item item-yellow"
                                                        data-id="<?php echo (int)$row['id']; ?>"
                                                        data-email="<?php echo htmlspecialchars($row['email'],    ENT_QUOTES,'UTF-8'); ?>"
                                                        data-topic="<?php echo htmlspecialchars($row['topic'],    ENT_QUOTES,'UTF-8'); ?>"
                                                        data-comment="<?php echo htmlspecialchars($row['comment'],ENT_QUOTES,'UTF-8'); ?>"
                                                        data-remarks="<?php echo htmlspecialchars($adminRemarks,  ENT_QUOTES,'UTF-8'); ?>"
                                                        data-replied="<?php echo htmlspecialchars($replyDate,     ENT_QUOTES,'UTF-8'); ?>"
                                                        onclick="openAdminModal(this);toggleEditReply(true);closeAllMenus();">
                                                    <span class="item-icon"><i class="fas fa-pen"></i></span>
                                                    Edit Reply
                                                </button>
                                                <?php endif; ?>
                                                <div class="dropdown-divider"></div>
                                                <!-- Delete -->
                                                <form method="POST" action="manage_feedback.php"
                                                      onsubmit="return confirm('Delete this feedback and its reply permanently?');">
                                                    <input type="hidden" name="csrf_token"
                                                           value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                                                    <input type="hidden" name="delete_id" value="<?php echo (int)$row['id']; ?>">
                                                    <button type="submit" name="delete_feedback" class="action-item item-red">
                                                        <span class="item-icon"><i class="fas fa-trash"></i></span>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="flex justify-center mt-6 space-x-2 flex-wrap gap-y-2">
                            <?php
                            $pgBase = array_filter([
                                'filter'    => $filter !== 'all' ? $filter : null,
                                'search'    => $search ?: null,
                                'sort'      => $sort !== 'date_sent' ? $sort : null,
                                'order'     => $order !== 'ASC' ? strtolower($order) : null,
                                'date_from' => $df ?: null,
                                'date_to'   => $dt ?: null,
                            ]);
                            ?>
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($pgBase, ['page' => 1])); ?>"
                                   class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">First page</a>
                                <a href="?<?php echo http_build_query(array_merge($pgBase, ['page' => $page - 1])); ?>"
                                   class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Previous</a>
                            <?php endif; ?>
                            <?php
                            $startPage = max(1, min($page - 5, $totalPages - 9));
                            $endPage   = min($totalPages, $startPage + 9);
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="?<?php echo http_build_query(array_merge($pgBase, ['page' => $i])); ?>"
                                   class="px-4 py-2 rounded <?php echo $i === $page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?php echo http_build_query(array_merge($pgBase, ['page' => $page + 1])); ?>"
                                   class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Next</a>
                                <a href="?<?php echo http_build_query(array_merge($pgBase, ['page' => $totalPages])); ?>"
                                   class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Last page</a>
                            <?php endif; ?>
                        </div>
                        <p class="text-center text-sm text-gray-500 mt-3">
                            Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $recordsPerPage, $totalRows); ?>
                            of <?php echo number_format($totalRows); ?> feedback<?php echo $totalRows !== 1 ? 's' : ''; ?>
                        </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div><!-- end .main-content -->

    <!-- ================================================
         FEEDBACK MODAL
         ================================================ -->
    <div id="adminModal" class="fixed hidden inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div id="adminModalBox" class="bg-white p-6 rounded shadow-md max-w-xl w-full relative">
            <button onclick="closeAdminModal()"
                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl font-bold">&times;</button>
            <h3 class="text-xl font-bold mb-4">Feedback Details</h3>
            <p class="mb-1"><strong>Email:</strong> <span id="modalEmail"></span></p>
            <p class="mb-1"><strong>Topic:</strong> <span id="modalTopic"></span></p>
            <p class="mb-3"><strong>Comment:</strong> <span id="modalComment" class="whitespace-pre-line"></span></p>
            <hr class="my-4">
            <div id="existingReplySection" class="hidden">
                <h4 class="text-lg font-semibold mb-1 text-green-700">Admin Reply</h4>
                <p class="mb-1"><strong>Remarks:</strong> <span id="modalRemarks" class="whitespace-pre-line"></span></p>
                <p class="text-sm text-gray-500 mb-4">Replied on: <span id="modalReplied"></span></p>
                <form method="POST" action="manage_feedback.php" id="editReplyForm">
                    <input type="hidden" name="csrf_token"
                           value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                    <input type="hidden" name="feedback_id" id="editFeedbackId">
                    <div id="editReplySection" class="hidden">
                        <label class="block font-semibold mb-1">Edit Remarks:</label>
                        <textarea name="admin_remarks" id="editRemarksInput" maxlength="2000"
                                  class="w-full border px-3 py-2 rounded mb-1" rows="4"></textarea>
                        <div id="editCounter" class="char-counter mb-3">0 / 2000</div>
                        <div class="flex justify-end space-x-2">
                            <button type="button" onclick="toggleEditReply(false)"
                                    class="bg-gray-400 hover:bg-gray-500 text-white px-4 py-2 rounded">Cancel</button>
                            <button type="submit" name="edit_reply"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update Reply</button>
                        </div>
                    </div>
                    <div id="editReplyBtn">
                        <button type="button" onclick="toggleEditReply(true)"
                                class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded text-sm">
                            <i class="fas fa-edit mr-1"></i>Edit Reply
                        </button>
                    </div>
                </form>
            </div>
            <div id="newReplySection" class="hidden">
                <form method="POST" action="manage_feedback.php">
                    <input type="hidden" name="csrf_token"
                           value="<?php echo htmlspecialchars($_SESSION['csrf_token'],ENT_QUOTES,'UTF-8'); ?>">
                    <input type="hidden" name="feedback_id" id="newFeedbackId">
                    <label class="block font-semibold mb-1">Admin Remarks:</label>
                    <textarea name="admin_remarks" id="newRemarksInput" maxlength="2000"
                              class="w-full border px-3 py-2 rounded mb-1" rows="4" required></textarea>
                    <div id="newCounter" class="char-counter mb-3">0 / 2000</div>
                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeAdminModal()"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Close</button>
                        <button type="submit" name="submit_reply"
                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Submit Reply</button>
                    </div>
                </form>
            </div>
            <div id="closeOnlyBtn" class="hidden flex justify-end mt-4">
                <button type="button" onclick="closeAdminModal()"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Close</button>
            </div>
        </div>
    </div>

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const t = document.getElementById('sidebarToggle');
            if (t) {
                t.addEventListener('click', function () {
                    document.querySelector('.sidebar').classList.toggle('active');
                    document.querySelector('.main-content').classList.toggle('active');
                });
            }
        });
        function confirmLogout() { return confirm('Are you sure you want to log out?'); }
    </script>

    <script>
        /* Three-dot dropdown */
        function closeAllMenus() {
            document.querySelectorAll('.action-trigger.open').forEach(b => b.classList.remove('open'));
            document.querySelectorAll('.action-dropdown.open').forEach(d => {
                d.classList.remove('open');
                d.style.top = ''; d.style.bottom = '';
                d.style.left = ''; d.style.right = '';
            });
        }
        function toggleMenu(btn) {
            const dd      = btn.nextElementSibling;
            const wasOpen = dd.classList.contains('open');
            closeAllMenus();
            if (!wasOpen) {
                btn.classList.add('open');
                const rect       = btn.getBoundingClientRect();
                const ddW        = 150;
                const ddH        = 100; // approximate height for 2-3 items
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceRight = window.innerWidth  - rect.right;

                // Vertical: open downward unless not enough space below
                if (spaceBelow < ddH + 8) {
                    dd.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
                    dd.style.top    = 'auto';
                } else {
                    dd.style.top    = (rect.bottom + 4) + 'px';
                    dd.style.bottom = 'auto';
                }
                // Horizontal: align right edge of dropdown to right edge of button
                if (spaceRight < ddW) {
                    dd.style.left  = Math.max(8, rect.left - ddW + rect.width) + 'px';
                    dd.style.right = 'auto';
                } else {
                    dd.style.left  = (rect.right - ddW) + 'px';
                    dd.style.right = 'auto';
                }
                dd.classList.add('open');
            }
        }
        // Close on scroll or resize so dropdown doesn't drift from its button
        ['scroll', 'resize'].forEach(function(evt) {
            window.addEventListener(evt, closeAllMenus, true);
        });
        document.addEventListener('click', e => { if (!e.target.closest('.action-menu')) closeAllMenus(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeAllMenus(); closeAdminModal(); } });
    </script>

    <script>
        /* Feedback modal */
        function openAdminModal(btn) {
            const hasReply = (btn.dataset.remarks || '').trim() !== '';
            document.getElementById('modalEmail').textContent   = btn.dataset.email;
            document.getElementById('modalTopic').textContent   = btn.dataset.topic;
            document.getElementById('modalComment').textContent = btn.dataset.comment;
            document.getElementById('editFeedbackId').value     = btn.dataset.id;
            document.getElementById('newFeedbackId').value      = btn.dataset.id;
            ['existingReplySection','newReplySection','closeOnlyBtn','editReplySection']
                .forEach(id => document.getElementById(id).classList.add('hidden'));
            document.getElementById('editReplyBtn').classList.remove('hidden');
            if (hasReply) {
                document.getElementById('modalRemarks').textContent = btn.dataset.remarks;
                document.getElementById('modalReplied').textContent = btn.dataset.replied;
                document.getElementById('editRemarksInput').value   = btn.dataset.remarks;
                document.getElementById('existingReplySection').classList.remove('hidden');
            } else {
                document.getElementById('newReplySection').classList.remove('hidden');
            }
            document.getElementById('adminModal').classList.remove('hidden');
        }
        function closeAdminModal() {
            document.getElementById('adminModal').classList.add('hidden');
            document.getElementById('editReplySection').classList.add('hidden');
            document.getElementById('editReplyBtn').classList.remove('hidden');
        }
        function toggleEditReply(show) {
            document.getElementById('editReplySection').classList.toggle('hidden', !show);
            document.getElementById('editReplyBtn').classList.toggle('hidden', show);
            document.getElementById('closeOnlyBtn').classList.toggle('hidden', show);
            if (show) document.getElementById('editRemarksInput').focus();
        }
        document.getElementById('adminModal').addEventListener('click', e => {
            if (e.target === document.getElementById('adminModal')) closeAdminModal();
        });
    </script>

    <script>
        /* Character counters */
        function attachCounter(inputId, counterId, maxLen) {
            const input = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            if (!input || !counter) return;
            function update() {
                const len = input.value.length;
                counter.textContent = len + ' / ' + maxLen;
                counter.className = 'char-counter';
                if (len >= maxLen)             counter.classList.add('over');
                else if (len >= maxLen * 0.85) counter.classList.add('warn');
            }
            input.addEventListener('input', update);
            update();
        }
        attachCounter('newRemarksInput',  'newCounter',  2000);
        attachCounter('editRemarksInput', 'editCounter', 2000);
    </script>

</body>
</html>