<?php
session_start();
include('include/config.php');

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
// UNRESPONDED FEEDBACK COUNT — for sidebar badge
// ============================================================
$unreadStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus WHERE status = 'Not Responded'");
$unreadStmt->execute();
$totalUnread = (int) $unreadStmt->get_result()->fetch_assoc()['c'];
$unreadStmt->close();

// ============================================================
// SUMMARY COUNTS (today, total success/fail, suspicious IPs)
// ============================================================
$todayLogins   = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM userlog WHERE DATE(loginTime) = CURDATE()"))['c'];
$todaySuccess  = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM userlog WHERE DATE(loginTime) = CURDATE() AND status = 1"))['c'];
$todayFailed   = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM userlog WHERE DATE(loginTime) = CURDATE() AND status = 0"))['c'];

// Suspicious IPs: IPs with 3+ failed attempts in the last 24 hours
$suspiciousResult = mysqli_query($con,
    "SELECT userip, COUNT(*) AS attempts
     FROM userlog
     WHERE status = 0 AND loginTime >= NOW() - INTERVAL 24 HOUR
     GROUP BY userip
     HAVING attempts >= 3"
);
$suspiciousIPs = [];
while ($r = mysqli_fetch_assoc($suspiciousResult)) {
    $suspiciousIPs[$r['userip']] = $r['attempts'];
}
$suspiciousCount = count($suspiciousIPs);

// ============================================================
// PAGINATION
// ============================================================
$recordsPerPage = 20;
$page   = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// ============================================================
// SEARCH
// ============================================================
$search = isset($_GET['search']) ? $_GET['search'] : '';
$searchCondition = '';
if (!empty($search)) {
    $search = mysqli_real_escape_string($con, $search);
    $searchCondition = "(username LIKE '%$search%' OR uid LIKE '%$search%' OR userip LIKE '%$search%')";
}

// ============================================================
// STATUS FILTER
// ============================================================
$statusFilter    = isset($_GET['status']) ? $_GET['status'] : 'all';
$statusCondition = '';
if ($statusFilter === 'success') {
    $statusCondition = "status = 1";
} elseif ($statusFilter === 'failed') {
    $statusCondition = "status = 0";
} elseif ($statusFilter === 'today') {
    $statusCondition = "DATE(loginTime) = CURDATE()";
} elseif ($statusFilter === 'suspicious') {
    if (!empty($suspiciousIPs)) {
        $ipList = implode("','", array_map('mysqli_real_escape_string', array_fill(0, count($suspiciousIPs), $con)));
        $ipList = "'" . implode("','", array_keys($suspiciousIPs)) . "'";
        $statusCondition = "userip IN ($ipList)";
    } else {
        $statusCondition = "1 = 0"; // no suspicious IPs, return nothing
    }
}

// ============================================================
// COMBINE CONDITIONS
// ============================================================
$whereClause = '';
if (!empty($searchCondition) && !empty($statusCondition)) {
    $whereClause = "WHERE $searchCondition AND $statusCondition";
} elseif (!empty($searchCondition)) {
    $whereClause = "WHERE $searchCondition";
} elseif (!empty($statusCondition)) {
    $whereClause = "WHERE $statusCondition";
}

// ============================================================
// FILTER TAB COUNTS
// ============================================================
$allCount        = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS count FROM userlog" . ($searchCondition ? " WHERE $searchCondition" : "")))['count'];
$successCount    = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS count FROM userlog WHERE status = 1" . ($searchCondition ? " AND $searchCondition" : "")))['count'];
$failedCount     = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS count FROM userlog WHERE status = 0" . ($searchCondition ? " AND $searchCondition" : "")))['count'];
$todayCount      = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS count FROM userlog WHERE DATE(loginTime) = CURDATE()" . ($searchCondition ? " AND $searchCondition" : "")))['count'];

// ============================================================
// CSV EXPORT — must run before HTML output
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expResult = mysqli_query($con,
        "SELECT uid, username, userip, loginTime, logout, status
         FROM userlog $whereClause
         ORDER BY loginTime DESC"
    );
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="user_logs_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['User ID', 'Username', 'IP Address', 'Login Time', 'Logout Time', 'Duration', 'Status']);
    while ($r = mysqli_fetch_assoc($expResult)) {
        $duration = '—';
        if (!empty($r['logout']) && !empty($r['loginTime'])) {
            $diff = strtotime($r['logout']) - strtotime($r['loginTime']);
            if ($diff > 0) {
                $h = floor($diff / 3600);
                $m = floor(($diff % 3600) / 60);
                $s = $diff % 60;
                $duration = $h > 0 ? "{$h}h {$m}m" : ($m > 0 ? "{$m}m {$s}s" : "{$s}s");
            }
        }
        fputcsv($out, [
            $r['uid'], $r['username'], $r['userip'],
            $r['loginTime'], $r['logout'] ?: 'Active',
            $duration,
            $r['status'] == 1 ? 'Success' : 'Failed'
        ]);
    }
    fclose($out);
    exit;
}

// ============================================================
// FETCH LOGS
// ============================================================
$sql       = mysqli_query($con, "SELECT * FROM userlog $whereClause ORDER BY loginTime DESC LIMIT $offset, $recordsPerPage");
$totalRows = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS total FROM userlog $whereClause"))['total'];
$totalPages = ceil($totalRows / $recordsPerPage);

// Helper: format session duration from two datetime strings
function sessionDuration($loginTime, $logout) {
    if (empty($logout) || empty($loginTime)) return null;
    $diff = strtotime($logout) - strtotime($loginTime);
    if ($diff <= 0) return null;
    $h = floor($diff / 3600);
    $m = floor(($diff % 3600) / 60);
    $s = $diff % 60;
    if ($h > 0)  return "{$h}h {$m}m";
    if ($m > 0)  return "{$m}m {$s}s";
    return "{$s}s";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | User Logs</title>
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
        appearance: none; -webkit-appearance: none; -moz-appearance: none;
        background-color: white; padding-right: 2.5rem;
    }
    .filter-tabs {
        display: flex;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 1rem;
        flex-wrap: wrap;
        gap: 2px;
    }
    .filter-tab {
        padding: 0.5rem 1rem;
        cursor: pointer;
        border-bottom: 2px solid transparent;
        margin-right: 0.25rem;
        text-decoration: none;
        color: inherit;
        white-space: nowrap;
    }
    .filter-tab.active {
        border-bottom-color: #4b6cb7;
        color: #4b6cb7;
        font-weight: 600;
    }
    .filter-tab:hover { background-color: #f7fafc; }
    .filter-tab.danger.active {
        border-bottom-color: #dc2626;
        color: #dc2626;
    }
    .count-badge {
        background-color: #e2e8f0;
        border-radius: 9999px;
        padding: 0.15rem 0.5rem;
        font-size: 0.75rem;
        margin-left: 0.25rem;
    }
    .count-badge.danger {
        background-color: #fee2e2;
        color: #b91c1c;
    }
    /* Suspicious IP row highlight */
    tr.suspicious-row { background-color: #fff7ed !important; }
    tr.suspicious-row:hover { background-color: #ffedd5 !important; }
    </style>
</head>
<body class="bg-gray-50">

    <!-- Sidebar -->
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
            <a href="dashboard.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage-users.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-users w-5 text-center"></i>
                <span>Users</span>
            </a>
            <a href="user-logs.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
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
                        <h1 class="text-2xl font-semibold">User Session Logs</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">Admin</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">User Logs</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-4 py-8">

            <!-- ================================================
                 SUMMARY CARDS
                 ================================================ -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">

                <!-- Today's Activity -->
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3">
                    <div class="p-3 bg-blue-100 rounded-full text-blue-600 flex-shrink-0">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Today's Logins</p>
                        <p class="text-xl font-bold text-[#182848]"><?php echo number_format($todayLogins); ?></p>
                        <p class="text-xs text-gray-400"><?php echo number_format($todaySuccess); ?> success, <?php echo number_format($todayFailed); ?> failed</p>
                    </div>
                </div>

                <!-- Total Successful -->
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3">
                    <div class="p-3 bg-green-100 rounded-full text-green-600 flex-shrink-0">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Total Success</p>
                        <p class="text-xl font-bold text-[#182848]"><?php echo number_format($successCount); ?></p>
                    </div>
                </div>

                <!-- Total Failed -->
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3">
                    <div class="p-3 bg-red-100 rounded-full text-red-500 flex-shrink-0">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Total Failed</p>
                        <p class="text-xl font-bold text-[#182848]"><?php echo number_format($failedCount); ?></p>
                    </div>
                </div>

                <!-- Suspicious IPs -->
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3 <?php echo $suspiciousCount > 0 ? 'border-l-4 border-orange-400' : ''; ?>">
                    <div class="p-3 <?php echo $suspiciousCount > 0 ? 'bg-orange-100 text-orange-500' : 'bg-gray-100 text-gray-400'; ?> rounded-full flex-shrink-0">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Suspicious IPs</p>
                        <p class="text-xl font-bold <?php echo $suspiciousCount > 0 ? 'text-orange-600' : 'text-[#182848]'; ?>">
                            <?php echo number_format($suspiciousCount); ?>
                        </p>
                        <p class="text-xs text-gray-400">3+ failures in 24h</p>
                    </div>
                </div>

            </div>

            <!-- ================================================
                 MAIN TABLE CARD
                 ================================================ -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-semibold text-gray-700">User <span class="text-[#4b6cb7]">Session Logs</span></h2>
                    <!-- CSV Export -->
                    <a href="user-logs.php?export=csv&status=<?php echo urlencode($statusFilter); ?>&search=<?php echo urlencode($search); ?>"
                       class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm flex items-center gap-2">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <a href="user-logs.php?status=all<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                       All <span class="count-badge"><?php echo number_format($allCount); ?></span>
                    </a>
                    <a href="user-logs.php?status=success<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="filter-tab <?php echo $statusFilter === 'success' ? 'active' : ''; ?>">
                       Success <span class="count-badge"><?php echo number_format($successCount); ?></span>
                    </a>
                    <a href="user-logs.php?status=failed<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="filter-tab <?php echo $statusFilter === 'failed' ? 'active' : ''; ?>">
                       Failed <span class="count-badge"><?php echo number_format($failedCount); ?></span>
                    </a>
                    <a href="user-logs.php?status=today<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="filter-tab <?php echo $statusFilter === 'today' ? 'active' : ''; ?>">
                       Today <span class="count-badge"><?php echo number_format($todayCount); ?></span>
                    </a>
                    <?php if ($suspiciousCount > 0): ?>
                    <a href="user-logs.php?status=suspicious<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="filter-tab danger <?php echo $statusFilter === 'suspicious' ? 'active' : ''; ?>">
                        <i class="fas fa-exclamation-triangle text-xs mr-1"></i>
                        Suspicious <span class="count-badge danger"><?php echo $suspiciousCount; ?></span>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Search Bar -->
                <form method="GET" class="mb-6">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="flex gap-2">
                        <div class="relative flex-1">
                            <input type="text" name="search"
                                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Search by username, ID or IP..."
                                   value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                        </div>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="user-logs.php?status=<?php echo htmlspecialchars($statusFilter, ENT_QUOTES, 'UTF-8'); ?>"
                               class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>

                <!-- Suspicious IP Warning Banner -->
                <?php if ($suspiciousCount > 0 && $statusFilter !== 'suspicious'): ?>
                <div class="mb-4 p-3 bg-orange-50 border border-orange-200 rounded-lg flex items-center justify-between">
                    <div class="flex items-center gap-2 text-orange-700 text-sm">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>
                            <strong><?php echo $suspiciousCount; ?> IP address<?php echo $suspiciousCount !== 1 ? 'es have' : ' has'; ?></strong>
                            3+ failed login attempts in the last 24 hours.
                        </span>
                    </div>
                    <a href="user-logs.php?status=suspicious"
                       class="text-xs font-semibold text-orange-700 hover:underline whitespace-nowrap ml-4">
                        View suspicious activity <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <?php if ($totalRows == 0): ?>
                        <div class="text-center py-8">
                            <p class="text-gray-500">
                                <?php
                                if ($statusFilter === 'success')    echo "No successful login attempts found.";
                                elseif ($statusFilter === 'failed') echo "No failed login attempts found.";
                                elseif ($statusFilter === 'today')  echo "No login activity today.";
                                elseif ($statusFilter === 'suspicious') echo "No suspicious activity found.";
                                else                                echo "No user logs found.";
                                ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Login Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Logout Time</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php
                                $cnt = $offset + 1;
                                while ($row = mysqli_fetch_array($sql)):
                                    $isSuspicious = isset($suspiciousIPs[$row['userip']]);
                                    $duration     = sessionDuration($row['loginTime'], $row['logout']);
                                ?>
                                <tr class="hover:bg-gray-50 <?php echo $isSuspicious ? 'suspicious-row' : ''; ?>">
                                    <td class="px-4 py-3 text-sm text-gray-500"><?php echo $cnt; ?>.</td>
                                    <td class="px-4 py-3 text-sm text-gray-500"><?php echo htmlspecialchars($row['uid'],       ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900"><?php echo htmlspecialchars($row['username'],  ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <?php echo htmlspecialchars($row['userip'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php if ($isSuspicious): ?>
                                            <span class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 bg-orange-100 text-orange-700 text-xs rounded font-medium"
                                                  title="<?php echo $suspiciousIPs[$row['userip']]; ?> failed attempts in 24h">
                                                <i class="fas fa-exclamation-triangle text-xs"></i>
                                                <?php echo $suspiciousIPs[$row['userip']]; ?>x
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500"><?php echo htmlspecialchars($row['loginTime'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <?php if (!empty($row['logout'])): ?>
                                            <?php echo htmlspecialchars($row['logout'], ENT_QUOTES, 'UTF-8'); ?>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-50 text-blue-600 text-xs rounded">
                                                <span class="w-1.5 h-1.5 bg-blue-500 rounded-full inline-block animate-pulse"></span>
                                                Active
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        <?php if ($duration): ?>
                                            <span class="text-gray-600"><?php echo $duration; ?></span>
                                        <?php elseif (empty($row['logout'])): ?>
                                            <span class="text-gray-400 italic text-xs">ongoing</span>
                                        <?php else: ?>
                                            <span class="text-gray-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <?php if ($row['status'] == 1): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Success</span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php
                                $cnt++;
                                endwhile;
                                ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="flex justify-center mt-6 space-x-2 flex-wrap gap-y-2">
                                <?php if ($page > 1): ?>
                                    <a href="user-logs.php?page=1&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>"
                                       class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">First page</a>
                                    <a href="user-logs.php?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>"
                                       class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Previous</a>
                                <?php endif; ?>

                                <?php
                                $maxVisiblePages = 10;
                                $half = floor($maxVisiblePages / 2);
                                if ($totalPages <= $maxVisiblePages) {
                                    $startPage = 1; $endPage = $totalPages;
                                } elseif ($page <= $half) {
                                    $startPage = 1; $endPage = $maxVisiblePages;
                                } elseif ($page >= ($totalPages - $half)) {
                                    $startPage = $totalPages - $maxVisiblePages + 1; $endPage = $totalPages;
                                } else {
                                    $startPage = $page - $half; $endPage = $page + $half;
                                }
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="user-logs.php?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>"
                                       class="px-4 py-2 rounded <?= ($i == $page ? 'bg-blue-500 text-white' : 'bg-gray-200 hover:bg-gray-300') ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="user-logs.php?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>"
                                       class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Next</a>
                                    <a href="user-logs.php?page=<?php echo $totalPages; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>"
                                       class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Last page</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Pagination info line -->
                        <p class="text-center text-sm text-gray-500 mt-3">
                            Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $recordsPerPage, $totalRows); ?>
                            of <?php echo number_format($totalRows); ?> log<?php echo $totalRows != 1 ? 's' : ''; ?>
                        </p>

                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar       = document.querySelector('.sidebar');
            const mainContent   = document.querySelector('.main-content');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
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