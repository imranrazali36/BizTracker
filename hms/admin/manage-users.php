<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
include('include/config.php');

// ============================================================
// CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// ADMIN AUTH — verify session belongs to admin table
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
// HELPERS — session flash messages
// ============================================================
function setFlash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function getFlash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ============================================================
// DELETE USER — POST handler with CSRF
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $delId = (int) ($_POST['delete_id'] ?? 0);
    if ($delId > 0) {
        $delStmt = $con->prepare("DELETE FROM users WHERE id = ?");
        $delStmt->bind_param("i", $delId);
        if ($delStmt->execute() && $delStmt->affected_rows > 0) {
            setFlash('success', 'User has been deleted successfully.');
        } else {
            setFlash('error', 'Error deleting user: user may have existing records in the database.');
        }
        $delStmt->close();
    }

    header('Location: manage-users.php');
    exit;
}

// ============================================================
// EDIT USER — POST handler with CSRF
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_submit'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $editId = (int) ($_POST['edit_id'] ?? 0);
    $fullName = trim($_POST['fullName'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $contactno = trim($_POST['contactno'] ?? '');

    if ($editId > 0 && $fullName !== '') {
        $editStmt = $con->prepare(
            "UPDATE users SET fullName=?, address=?, city=?, gender=?, dob=?, contactno=?, updationDate=NOW() WHERE id=?"
        );
        $editStmt->bind_param("ssssssi", $fullName, $address, $city, $gender, $dob, $contactno, $editId);
        if ($editStmt->execute() && $editStmt->affected_rows >= 0) {
            setFlash('success', 'User details updated successfully.');
        } else {
            setFlash('error', 'Error updating user. Please try again.');
        }
        $editStmt->close();
    }

    header('Location: manage-users.php' . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// ============================================================
// SAFE SORT / ORDER — whitelist
// ============================================================
$allowedSorts = ['regDate', 'fullName', 'email', 'updationDate'];
$sort = (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts, true))
    ? $_GET['sort'] : 'regDate';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
$nextOrder = $order === 'ASC' ? 'desc' : 'asc';

// ============================================================
// PAGINATION
// ============================================================
$recordsPerPage = 10;
$page = (isset($_GET['page']) && is_numeric($_GET['page']) && (int) $_GET['page'] > 0)
    ? (int) $_GET['page'] : 1;

// ============================================================
// SEARCH
// ============================================================
$search = trim($_GET['search'] ?? '');

// ============================================================
// SUMMARY COUNTS
// ============================================================
$totalUsersStmt = $con->prepare("SELECT COUNT(*) AS c FROM users");
$totalUsersStmt->execute();
$totalUsersAll = (int) $totalUsersStmt->get_result()->fetch_assoc()['c'];
$totalUsersStmt->close();

$newThisMonthStmt = $con->prepare(
    "SELECT COUNT(*) AS c FROM users
     WHERE YEAR(regDate) = YEAR(CURDATE()) AND MONTH(regDate) = MONTH(CURDATE())"
);
$newThisMonthStmt->execute();
$newThisMonth = (int) $newThisMonthStmt->get_result()->fetch_assoc()['c'];
$newThisMonthStmt->close();

// ============================================================
// COUNT QUERY
// ============================================================
if (!empty($search)) {
    $like = '%' . $search . '%';
    $cntStmt = $con->prepare(
        "SELECT COUNT(*) AS total FROM users
         WHERE fullName LIKE ? OR email LIKE ? OR contactno LIKE ?"
    );
    $cntStmt->bind_param("sss", $like, $like, $like);
} else {
    $cntStmt = $con->prepare("SELECT COUNT(*) AS total FROM users");
}
$cntStmt->execute();
$totalRows = (int) $cntStmt->get_result()->fetch_assoc()['total'];
$cntStmt->close();

$totalPages = max(1, (int) ceil($totalRows / $recordsPerPage));
if ($page > $totalPages)
    $page = $totalPages;
$offset = ($page - 1) * $recordsPerPage;

// ============================================================
// DATA QUERY
// ============================================================
if (!empty($search)) {
    $like = '%' . $search . '%';
    $dataStmt = $con->prepare(
        "SELECT * FROM users
         WHERE fullName LIKE ? OR email LIKE ? OR contactno LIKE ?
         ORDER BY $sort $order
         LIMIT ? OFFSET ?"
    );
    $dataStmt->bind_param("sssii", $like, $like, $like, $recordsPerPage, $offset);
} else {
    $dataStmt = $con->prepare(
        "SELECT * FROM users ORDER BY $sort $order LIMIT ? OFFSET ?"
    );
    $dataStmt->bind_param("ii", $recordsPerPage, $offset);
}
$dataStmt->execute();
$userRows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

$flash = getFlash();

// ============================================================
// CSV EXPORT — runs before HTML output
// ============================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (!empty($search)) {
        $like = '%' . $search . '%';
        $expStmt = $con->prepare(
            "SELECT fullName, address, city, gender, email, contactno, dob, regDate, updationDate
             FROM users
             WHERE fullName LIKE ? OR email LIKE ? OR contactno LIKE ?
             ORDER BY $sort $order"
        );
        $expStmt->bind_param("sss", $like, $like, $like);
    } else {
        $expStmt = $con->prepare(
            "SELECT fullName, address, city, gender, email, contactno, dob, regDate, updationDate
             FROM users ORDER BY $sort $order"
        );
    }
    $expStmt->execute();
    $expRows = $expStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $expStmt->close();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="users_export_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel
    fputcsv($out, ['Full Name', 'Address', 'City', 'Gender', 'Email', 'Contact', 'Date of Birth', 'Registered', 'Last Updated']);
    foreach ($expRows as $r) {
        fputcsv($out, [
            $r['fullName'],
            $r['address'],
            $r['city'],
            $r['gender'],
            $r['email'],
            $r['contactno'],
            $r['dob'],
            $r['regDate'],
            $r['updationDate']
        ]);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .sidebar {
            width: 280px;
            transition: all 0.3s ease;
            background: linear-gradient(180deg, #4b6cb7 0%, #182848 100%);
        }

        .main-content {
            margin-left: 280px;
            transition: all 0.3s ease;
        }

        .nav-link {
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #fff;
        }

        @media (max-width: 768px) {
            .sidebar {
                margin-left: -280px;
            }

            .sidebar.active {
                margin-left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .main-content.active {
                margin-left: 280px;
            }
        }

        select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: white;
            padding-right: 2.5rem;
        }

        /* Sort indicators */
        .sort-link {
            text-decoration: none;
            color: inherit;
            white-space: nowrap;
        }

        .sort-link .sort-icon {
            font-size: 11px;
            margin-left: 4px;
            opacity: 0.5;
        }

        .sort-link.active-sort .sort-icon {
            opacity: 1;
            color: #4b6cb7;
        }

        /* ── Three-dot action dropdown ── */
        .action-menu {
            position: relative;
            display: inline-block;
        }

        .action-trigger {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: white;
            color: #6b7280;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 1px;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            user-select: none;
        }

        .action-trigger:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
            color: #374151;
        }

        .action-trigger.open {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        /* Dropdown is FIXED so it always floats above the page and never
       causes the table wrapper to grow or scroll */
        .action-dropdown {
            display: none;
            position: fixed;
            /* ← key change: fixed, not absolute */
            min-width: 150px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.13);
            z-index: 9999;
            /* always on top */
            overflow: hidden;
            transform-origin: top right;
            transform: scale(0.95) translateY(-4px);
            opacity: 0;
            transition: transform 0.12s ease, opacity 0.12s ease;
        }

        .action-dropdown.open {
            display: block;
            transform: scale(1) translateY(0);
            opacity: 1;
        }

        .action-item {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            text-decoration: none;
            color: #374151;
            transition: background 0.1s;
        }

        .action-item:hover {
            background: #f9fafb;
        }

        .item-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
        }

        /* colour variants */
        .item-blue .item-icon {
            background: #eff6ff;
            color: #2563eb;
        }

        .item-indigo .item-icon {
            background: #eef2ff;
            color: #4338ca;
        }

        .item-red .item-icon {
            background: #fef2f2;
            color: #dc2626;
        }

        .item-blue:hover {
            color: #1d4ed8;
        }

        .item-indigo:hover {
            color: #3730a3;
        }

        .item-red:hover {
            color: #b91c1c;
            background: #fff5f5;
        }

        .dropdown-divider {
            height: 1px;
            background: #f3f4f6;
            margin: 2px 0;
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
                    <h3 class="font-medium truncate"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?>
                    </h3>
                    <p class="text-sm text-white/70">Administrator</p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="mt-4 px-3">
            <div class="mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Main Menu</p>
            </div>
            <a href="dashboard.php"
                class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage-users.php"
                class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-users w-5 text-center"></i>
                <span>Users</span>
            </a>
            <a href="user-logs.php"
                class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-file-alt w-5 text-center"></i>
                <span>User Session Logs</span>
            </a>
            <!-- Feedback link with unresponded badge — consistent with dashboard.php -->
            <a href="manage_feedback.php"
                class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-envelope w-5 text-center"></i>
                <span>Feedback</span>
                <?php if ($totalUnread > 0): ?>
                    <span class="ml-auto bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded-full">
                        <?php echo $totalUnread; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="about-us.php"
                class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-info-circle w-5 text-center"></i>
                <span>About Us</span>
            </a>
            <div class="mt-4 mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Account Settings</p>
            </div>
            <a href="change-password.php"
                class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-lock w-5 text-center"></i>
                <span>Change Password</span>
            </a>
            <a href="logout.php" onclick="return confirmLogout()"
                class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>Log Out</span>
            </a>
        </nav>
    </div>

    <!-- ============================================================
         MAIN CONTENT
         ============================================================ -->
    <div class="main-content min-h-screen">

        <!-- Header -->
        <header class="bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white">
            <div class="h-1 bg-white/10"></div>
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <button id="sidebarToggle" class="md:hidden text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-semibold">Manage Users</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">Admin</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Manage Users</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-4 py-8">

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3">
                    <div class="p-3 bg-blue-100 rounded-full text-blue-600">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">Total Users</p>
                        <p class="text-xl font-bold text-[#182848]"><?php echo number_format($totalUsersAll); ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 flex items-center space-x-3">
                    <div class="p-3 bg-green-100 rounded-full text-green-600">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase tracking-wide">New This Month</p>
                        <p class="text-xl font-bold text-[#182848]"><?php echo number_format($newThisMonth); ?></p>
                    </div>
                </div>
            </div>

            <!-- Main Table Card -->
            <div class="bg-white rounded-lg shadow-lg">
                <div class="p-6">

                    <!-- Card header + export button -->
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-semibold text-gray-700">
                            Manage <span class="text-[#4b6cb7]">Users</span>
                        </h2>
                        <a href="manage-users.php?export=csv&search=<?php echo urlencode($search); ?>"
                            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm flex items-center gap-2">
                            <i class="fas fa-download"></i> Export CSV
                        </a>
                    </div>

                    <!-- Flash message -->
                    <?php if ($flash): ?>
                        <div
                            class="mb-4 p-4 rounded-lg <?php echo $flash['type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Search -->
                    <form method="GET" class="mb-6">
                        <div class="flex gap-2">
                            <div class="relative flex-1">
                                <input type="text" name="search"
                                    class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    placeholder="Search by name, email or phone..."
                                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                            </div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Search
                            </button>
                            <?php if (!empty($search)): ?>
                                <a href="manage-users.php"
                                    class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                                    Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <?php if ($totalRows === 0): ?>
                            <div class="text-center py-8">
                                <p class="text-gray-500">
                                    <?php echo empty($search) ? "No users found." : "No users match your search criteria."; ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <table class="w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">No
                                        </th>

                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">
                                            <a href="?<?php echo http_build_query(['search' => $search, 'sort' => 'fullName', 'order' => $sort === 'fullName' ? $nextOrder : 'asc']); ?>"
                                                class="sort-link <?php echo $sort === 'fullName' ? 'active-sort' : ''; ?>">
                                                Full Name
                                                <span
                                                    class="sort-icon"><?php echo $sort === 'fullName' ? ($order === 'ASC' ? '▲' : '▼') : '⇅'; ?></span>
                                            </a>
                                        </th>

                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">
                                            Address</th>
                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">City
                                        </th>
                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">Gender
                                        </th>

                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">
                                            <a href="?<?php echo http_build_query(['search' => $search, 'sort' => 'email', 'order' => $sort === 'email' ? $nextOrder : 'asc']); ?>"
                                                class="sort-link <?php echo $sort === 'email' ? 'active-sort' : ''; ?>">
                                                Email
                                                <span
                                                    class="sort-icon"><?php echo $sort === 'email' ? ($order === 'ASC' ? '▲' : '▼') : '⇅'; ?></span>
                                            </a>
                                        </th>

                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">
                                            Contact</th>
                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">Birth
                                            Date</th>

                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">
                                            <a href="?<?php echo http_build_query(['search' => $search, 'sort' => 'regDate', 'order' => $sort === 'regDate' ? $nextOrder : 'desc']); ?>"
                                                class="sort-link <?php echo $sort === 'regDate' ? 'active-sort' : ''; ?>">
                                                Created
                                                <span
                                                    class="sort-icon"><?php echo $sort === 'regDate' ? ($order === 'ASC' ? '▲' : '▼') : '⇅'; ?></span>
                                            </a>
                                        </th>

                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center">
                                            <a href="?<?php echo http_build_query(['search' => $search, 'sort' => 'updationDate', 'order' => $sort === 'updationDate' ? $nextOrder : 'desc']); ?>"
                                                class="sort-link <?php echo $sort === 'updationDate' ? 'active-sort' : ''; ?>">
                                                Updated
                                                <span
                                                    class="sort-icon"><?php echo $sort === 'updationDate' ? ($order === 'ASC' ? '▲' : '▼') : '⇅'; ?></span>
                                            </a>
                                        </th>

                                        <!-- Compact action column -->
                                        <th class="px-3 py-3 text-xs font-medium text-gray-500 uppercase text-center w-12">
                                            Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($userRows as $i => $row): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-4 text-sm text-gray-500 text-center">
                                                <?php echo $offset + $i + 1; ?></td>
                                            <td class="px-3 py-4 text-sm text-gray-900">
                                                <?php echo htmlspecialchars($row['fullName'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['contactno'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['dob'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['regDate'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="px-3 py-4 text-sm text-gray-500">
                                                <?php echo htmlspecialchars($row['updationDate'], ENT_QUOTES, 'UTF-8'); ?></td>

                                            <!-- ── Action cell: three-dot dropdown ── -->
                                            <td class="px-3 py-4 text-center">
                                                <div class="action-menu flex justify-center">

                                                    <!-- Trigger button -->
                                                    <button type="button" class="action-trigger" onclick="toggleMenu(this)"
                                                        aria-label="Actions">
                                                        &vellip;
                                                    </button>

                                                    <!-- Dropdown -->
                                                    <div class="action-dropdown">

                                                        <!-- View -->
                                                        <button type="button" class="action-item item-blue"
                                                            data-name="<?php echo htmlspecialchars($row['fullName'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-email="<?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-contact="<?php echo htmlspecialchars($row['contactno'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-address="<?php echo htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-city="<?php echo htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-gender="<?php echo htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-dob="<?php echo htmlspecialchars($row['dob'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-created="<?php echo htmlspecialchars($row['regDate'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            onclick="openUserModal(this); closeAllMenus();">
                                                            <span class="item-icon"><i class="fas fa-eye"></i></span>
                                                            View
                                                        </button>

                                                        <div class="dropdown-divider"></div>

                                                        <!-- Edit — now opens modal instead of redirecting -->
                                                        <button type="button" class="action-item item-indigo"
                                                            data-id="<?php echo (int) $row['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($row['fullName'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-address="<?php echo htmlspecialchars($row['address'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-city="<?php echo htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-gender="<?php echo htmlspecialchars($row['gender'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-dob="<?php echo htmlspecialchars($row['dob'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-contact="<?php echo htmlspecialchars($row['contactno'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-email="<?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-regdate="<?php echo htmlspecialchars($row['regDate'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-upddate="<?php echo htmlspecialchars($row['updationDate'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                                            onclick="openEditModal(this); closeAllMenus();">
                                                            <span class="item-icon"><i class="fas fa-pen"></i></span>
                                                            Edit
                                                        </button>

                                                        <div class="dropdown-divider"></div>

                                                        <!-- Delete -->
                                                        <form method="POST" action="manage-users.php"
                                                            onsubmit="return confirm('Delete this user? This cannot be undone.');">
                                                            <input type="hidden" name="csrf_token"
                                                                value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="delete_id"
                                                                value="<?php echo (int) $row['id']; ?>">
                                                            <button type="submit" name="delete" class="action-item item-red">
                                                                <span class="item-icon"><i class="fas fa-trash"></i></span>
                                                                Delete
                                                            </button>
                                                        </form>

                                                    </div>
                                                </div>
                                            </td>
                                            <!-- ── end action cell ── -->

                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="flex justify-center mt-6 space-x-2 flex-wrap gap-y-2">
                                    <?php
                                    $pgBase = array_filter([
                                        'search' => $search ?: null,
                                        'sort' => $sort !== 'regDate' ? $sort : null,
                                        'order' => $order !== 'DESC' ? strtolower($order) : null,
                                    ]);
                                    ?>
                                    <?php if ($page > 1): ?>
                                        <a href="?<?php echo http_build_query(array_merge($pgBase, ['page' => 1])); ?>"
                                            class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">First page</a>
                                        <a href="?<?php echo http_build_query(array_merge($pgBase, ['page' => $page - 1])); ?>"
                                            class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Previous</a>
                                    <?php endif; ?>

                                    <?php
                                    $maxVisible = 10;
                                    $half = (int) floor($maxVisible / 2);
                                    $startPage = max(1, min($page - $half, $totalPages - $maxVisible + 1));
                                    $endPage = min($totalPages, $startPage + $maxVisible - 1);
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
                            <?php endif; ?>

                            <!-- Pagination info line -->
                            <p class="text-center text-sm text-gray-500 mt-3">
                                Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $recordsPerPage, $totalRows); ?>
                                of <?php echo number_format($totalRows); ?> user<?php echo $totalRows !== 1 ? 's' : ''; ?>
                            </p>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div><!-- end .main-content -->

    <!-- ============================================================
         USER VIEW MODAL — redesigned, larger, more polished
         ============================================================ -->
    <div id="userModal"
        class="fixed hidden inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl relative overflow-hidden">

            <!-- Coloured banner header -->
            <div class="bg-gradient-to-r from-[#4b6cb7] to-[#182848] px-8 pt-8 pb-14 relative">
                <button onclick="closeUserModal()"
                    class="absolute top-4 right-4 w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center text-lg font-bold transition-colors">
                    &times;
                </button>
                <p class="text-xs font-semibold uppercase tracking-widest text-white/60 mb-1">User Profile</p>
                <h3 class="text-2xl font-bold text-white" id="uModalName">—</h3>
                <p class="text-sm text-white/70 mt-1" id="uModalEmail">—</p>
            </div>

            <!-- Avatar overlapping the banner -->
            <div class="flex justify-center -mt-10 mb-2">
                <div
                    class="w-20 h-20 rounded-full bg-white ring-4 ring-white shadow-lg flex items-center justify-center">
                    <div
                        class="w-full h-full rounded-full bg-gradient-to-br from-[#4b6cb7] to-[#182848] flex items-center justify-center">
                        <i class="fas fa-user text-3xl text-white"></i>
                    </div>
                </div>
            </div>

            <!-- Info grid -->
            <div class="px-8 pb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                    <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-phone text-blue-600 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Contact</p>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5 truncate" id="uContact">—</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-purple-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-venus-mars text-purple-600 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Gender</p>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5" id="uGender">—</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-pink-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-birthday-cake text-pink-600 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Date of Birth</p>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5" id="uDob">—</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 bg-gray-50 rounded-xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-city text-green-600 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">City</p>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5 truncate" id="uCity">—</p>
                        </div>
                    </div>

                    <div class="sm:col-span-2 flex items-start gap-3 bg-gray-50 rounded-xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-orange-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-map-marker-alt text-orange-600 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Address</p>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5" id="uAddress">—</p>
                        </div>
                    </div>

                    <div class="sm:col-span-2 flex items-start gap-3 bg-gray-50 rounded-xl p-4">
                        <div class="w-9 h-9 rounded-lg bg-indigo-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-calendar-check text-indigo-600 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Registered</p>
                            <p class="text-sm font-semibold text-gray-800 mt-0.5" id="uCreated">—</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================================================
         EDIT USER MODAL — populated via JS, submits via POST
         ============================================================ -->
    <div id="editModal"
        class="fixed hidden inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl relative max-h-[92vh] overflow-y-auto">

            <!-- Modal header (matches edit-users.php form header style) -->
            <div class="p-6 bg-gradient-to-r from-[#4b6cb7]/10 to-[#182848]/10 rounded-t-xl">
                <div class="flex items-center space-x-4">
                    <div
                        class="w-14 h-14 rounded-full bg-gradient-to-r from-[#4b6cb7] to-[#182848] flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-user text-xl text-white"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-xl font-bold text-gray-800" id="editModalTitle">Edit User</h3>
                        <div class="mt-1 text-sm text-gray-600 flex flex-wrap gap-x-4">
                            <span class="inline-flex items-center">
                                <i class="far fa-calendar-alt mr-1"></i>
                                Registered: <span id="eRegDate" class="ml-1"></span>
                            </span>
                            <span class="inline-flex items-center" id="eUpdWrap">
                                <i class="far fa-clock mr-1"></i>
                                Last Updated: <span id="eUpdDate" class="ml-1"></span>
                            </span>
                        </div>
                    </div>
                    <button onclick="closeEditModal()"
                        class="text-gray-400 hover:text-gray-600 text-2xl font-bold leading-none flex-shrink-0">
                        &times;
                    </button>
                </div>
            </div>

            <!-- Edit form -->
            <form method="POST"
                action="manage-users.php<?php echo !empty($_SERVER['QUERY_STRING']) ? '?' . htmlspecialchars($_SERVER['QUERY_STRING'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                class="p-6">
                <input type="hidden" name="csrf_token"
                    value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="edit_id" id="eId">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Personal Information -->
                    <div class="space-y-5">
                        <h4 class="text-base font-semibold text-gray-800 border-b pb-2">Personal Information</h4>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="eFullName">
                                Full Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="fullName" id="eFullName" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="eGender">
                                Gender <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <select name="gender" id="eGender" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                                <div
                                    class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="eDob">
                                Date of Birth <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="dob" id="eDob" required max="<?php echo date('Y-m-d'); ?>"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent">
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="space-y-5">
                        <h4 class="text-base font-semibold text-gray-800 border-b pb-2">Contact Information</h4>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="eAddress">
                                Address <span class="text-red-500">*</span>
                            </label>
                            <textarea name="address" id="eAddress" rows="3" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="eCity">
                                City <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="city" id="eCity" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1" for="eContact">
                                Contact Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="contactno" id="eContact" required pattern="[0-9]{10,15}"
                                title="Please enter a valid phone number (10-15 digits)"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent">
                            <p class="mt-1 text-xs text-gray-500">10-15 digits only</p>
                        </div>
                    </div>

                    <!-- Account Information (read-only email) -->
                    <div class="md:col-span-2 space-y-4">
                        <h4 class="text-base font-semibold text-gray-800 border-b pb-2">Account Information</h4>
                        <div class="max-w-sm">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <div class="relative">
                                <input type="email" id="eEmail" readonly
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed text-gray-500">
                                <span class="absolute right-3 top-2.5 text-gray-400">
                                    <i class="fas fa-lock text-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="md:col-span-2 pt-4 border-t flex items-center justify-end space-x-3">
                        <button type="button" onclick="resetEditForm()"
                            class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors">
                            <i class="fas fa-undo mr-2"></i>Reset Changes
                        </button>
                        <button type="submit" name="edit_submit"
                            class="px-6 py-2.5 bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[#4b6cb7] focus:ring-offset-2 transition-all">
                            <i class="fas fa-save mr-2"></i>Update User
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script>
        /* ---------- Sidebar toggle ---------- */
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');
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

    <script>
        /* ---------- Three-dot action dropdown ---------- */

        function closeAllMenus() {
            document.querySelectorAll('.action-trigger.open').forEach(function (btn) {
                btn.classList.remove('open');
            });
            document.querySelectorAll('.action-dropdown.open').forEach(function (dd) {
                dd.classList.remove('open');
            });
        }

        function toggleMenu(triggerBtn) {
            const dropdown = triggerBtn.nextElementSibling;
            const wasOpen = dropdown.classList.contains('open');

            closeAllMenus();

            if (!wasOpen) {
                triggerBtn.classList.add('open');

                // Position the fixed dropdown relative to the trigger button
                const rect = triggerBtn.getBoundingClientRect();
                const ddWidth = 150;

                // Place below the button
                dropdown.style.top = (rect.bottom + 4) + 'px';

                // Align right edge with button, but clamp to viewport
                let left = rect.right - ddWidth;
                if (left < 8) left = 8;
                if (left + ddWidth > window.innerWidth - 8) left = window.innerWidth - ddWidth - 8;
                dropdown.style.left = left + 'px';

                dropdown.classList.add('open');
            }
        }

        // Close when clicking outside any menu
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.action-menu')) {
                closeAllMenus();
            }
        });

        // Close on Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAllMenus();
                closeUserModal();
                closeEditModal();
            }
        });
    </script>

    <script>
        /* ---------- User view modal (XSS-safe via data attributes) ---------- */

        function openUserModal(btn) {
            document.getElementById('uModalName').textContent = btn.dataset.name;
            document.getElementById('uModalEmail').textContent = btn.dataset.email;
            document.getElementById('uContact').textContent = btn.dataset.contact;
            document.getElementById('uAddress').textContent = btn.dataset.address;
            document.getElementById('uCity').textContent = btn.dataset.city;
            document.getElementById('uGender').textContent = btn.dataset.gender;
            document.getElementById('uDob').textContent = btn.dataset.dob;
            document.getElementById('uCreated').textContent = btn.dataset.created;
            document.getElementById('userModal').classList.remove('hidden');
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.add('hidden');
        }

        // Close modal when clicking the dark backdrop
        document.getElementById('userModal').addEventListener('click', function (e) {
            if (e.target === this) closeUserModal();
        });
    </script>

    <script>
        /* ---------- Edit user modal ---------- */

        // Store original values so Reset Changes works
        var _editOriginal = {};

        function openEditModal(btn) {
            var d = btn.dataset;

            // Store originals for reset
            _editOriginal = {
                fullName: d.name,
                address: d.address,
                city: d.city,
                gender: d.gender,
                dob: d.dob,
                contactno: d.contact,
            };

            // Populate hidden id
            document.getElementById('eId').value = d.id;

            // Header meta
            document.getElementById('editModalTitle').textContent = d.name + "'s Profile";
            document.getElementById('eRegDate').textContent = d.regdate;

            var updWrap = document.getElementById('eUpdWrap');
            if (d.upddate && d.upddate.trim() !== '') {
                document.getElementById('eUpdDate').textContent = d.upddate;
                updWrap.style.display = '';
            } else {
                updWrap.style.display = 'none';
            }

            // Form fields
            document.getElementById('eFullName').value = d.name;
            document.getElementById('eAddress').value = d.address;
            document.getElementById('eCity').value = d.city;
            document.getElementById('eDob').value = d.dob;
            document.getElementById('eContact').value = d.contact;
            document.getElementById('eEmail').value = d.email;

            // Gender select
            var sel = document.getElementById('eGender');
            for (var i = 0; i < sel.options.length; i++) {
                sel.options[i].selected = (sel.options[i].value === d.gender);
            }

            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function resetEditForm() {
            document.getElementById('eFullName').value = _editOriginal.fullName || '';
            document.getElementById('eAddress').value = _editOriginal.address || '';
            document.getElementById('eCity').value = _editOriginal.city || '';
            document.getElementById('eDob').value = _editOriginal.dob || '';
            document.getElementById('eContact').value = _editOriginal.contactno || '';

            var sel = document.getElementById('eGender');
            for (var i = 0; i < sel.options.length; i++) {
                sel.options[i].selected = (sel.options[i].value === _editOriginal.gender);
            }
        }

        // Close when clicking the dark backdrop
        document.getElementById('editModal').addEventListener('click', function (e) {
            if (e.target === this) closeEditModal();
        });

        // Digits-only enforcement for contact field inside edit modal
        document.getElementById('eContact').addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // DOB future-date guard inside edit modal
        document.getElementById('eDob').addEventListener('change', function () {
            if (new Date(this.value) > new Date()) {
                alert('Date of birth cannot be in the future');
                this.value = _editOriginal.dob || '';
            }
        });
    </script>

</body>

</html>