<?php
session_start();
include('include/config.php');
include('include/checklogin.php');
check_login();

// ============================================================
// CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// USER DATA — prepared statement, userId cast to int
// ============================================================
$userId = (int) $_SESSION['id'];
if ($userId <= 0) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$userStmt = $con->prepare("SELECT fullName, email FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userData  = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
$userName  = $userData['fullName'] ?? 'User';
$userEmail = $userData['email']    ?? '';

// ============================================================
// HELPERS — session flash messages (Post-Redirect-Get)
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
$allowedSorts = ['date_sent', 'topic', 'status'];
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

// ============================================================
// SUBMIT FEEDBACK — POST handler
// ============================================================
$error   = '';
$oldPost = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    $topic   = trim($_POST['topic']   ?? '');
    $comment = trim($_POST['comment'] ?? '');
    $oldPost = ['topic' => $topic, 'comment' => $comment];

    if (empty($topic)) {
        $error = "Topic is required.";
    } elseif (strlen($topic) > 200) {
        $error = "Topic must be 200 characters or fewer.";
    } elseif (empty($comment)) {
        $error = "Comment is required.";
    } elseif (strlen($comment) > 2000) {
        $error = "Comment must be 2000 characters or fewer.";
    } else {
        $status    = 'Not Responded';
        $dateSent  = date('Y-m-d H:i:s');

        $insStmt = $con->prepare(
            "INSERT INTO contactus (user_id, email, topic, comment, status, date_sent)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insStmt->bind_param("isssss", $userId, $userEmail, $topic, $comment, $status, $dateSent);
        if ($insStmt->execute()) {
            setFlash('success', 'Feedback sent successfully!');
            header("Location: feedback.php");
            exit();
        } else {
            $error = "Failed to submit feedback. Please try again.";
        }
        $insStmt->close();
    }
}

// ============================================================
// DELETE FEEDBACK — POST handler (moved from GET)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $delId = (int) ($_POST['delete_id'] ?? 0);
    if ($delId > 0) {
        $delStmt = $con->prepare(
            "DELETE FROM contactus WHERE id = ? AND user_id = ?"
        );
        $delStmt->bind_param("ii", $delId, $userId);
        if ($delStmt->execute() && $delStmt->affected_rows > 0) {
            setFlash('success', 'Feedback deleted successfully!');
        } else {
            setFlash('error', 'Failed to delete feedback or record not found.');
        }
        $delStmt->close();
    }

    header("Location: feedback.php?filter=" . urlencode($filter) . "&search=" . urlencode($search));
    exit();
}

// ============================================================
// BUILD WHERE PARTS (shared by all queries)
// ============================================================
$whereParts  = ["c.user_id = ?"];
$bindTypes   = "i";
$bindValues  = [$userId];

if (!empty($search)) {
    $whereParts[] = "(c.topic LIKE ? OR c.comment LIKE ? OR c.date_sent LIKE ?)";
    $like = '%' . $search . '%';
    $bindTypes   .= "sss";
    $bindValues[] = $like;
    $bindValues[] = $like;
    $bindValues[] = $like;
}
if ($filter === 'responded') {
    $whereParts[] = "c.status = 'Responded'";
} elseif ($filter === 'unresponded') {
    $whereParts[] = "c.status = 'Not Responded'";
}

$whereSQL = "WHERE " . implode(" AND ", $whereParts);

// ============================================================
// COUNT QUERY (for pagination)
// ============================================================
$cntStmt = $con->prepare(
    "SELECT COUNT(*) AS total FROM contactus c $whereSQL"
);
$cntStmt->bind_param($bindTypes, ...$bindValues);
$cntStmt->execute();
$totalRecords = (int) $cntStmt->get_result()->fetch_assoc()['total'];
$cntStmt->close();
$totalPages = max(1, (int) ceil($totalRecords / $recordsPerPage));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $recordsPerPage; }

// ============================================================
// FILTER TAB COUNTS
// ============================================================
// All count (no status filter)
$allBase   = ["c.user_id = ?"];
$allTypes  = "i";
$allVals   = [$userId];
if (!empty($search)) {
    $allBase[]  = "(c.topic LIKE ? OR c.comment LIKE ? OR c.date_sent LIKE ?)";
    $allTypes  .= "sss";
    $allVals[]  = $like ?? '%' . $search . '%';
    $allVals[]  = $like ?? '%' . $search . '%';
    $allVals[]  = $like ?? '%' . $search . '%';
}
$allWhereSQL = "WHERE " . implode(" AND ", $allBase);

$acStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus c $allWhereSQL");
$acStmt->bind_param($allTypes, ...$allVals);
$acStmt->execute();
$allCount = (int) $acStmt->get_result()->fetch_assoc()['c'];
$acStmt->close();

$rcStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus c $allWhereSQL AND c.status = 'Responded'");
$rcStmt->bind_param($allTypes, ...$allVals);
$rcStmt->execute();
$respondedCount = (int) $rcStmt->get_result()->fetch_assoc()['c'];
$rcStmt->close();

$ucStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus c $allWhereSQL AND c.status = 'Not Responded'");
$ucStmt->bind_param($allTypes, ...$allVals);
$ucStmt->execute();
$unrespondedCount = (int) $ucStmt->get_result()->fetch_assoc()['c'];
$ucStmt->close();

// ============================================================
// DATA QUERY — LEFT JOIN replies to avoid N+1
// ============================================================
$dataTypes  = $bindTypes . "ii";
$dataVals   = array_merge($bindValues, [$recordsPerPage, $offset]);
$dataStmt   = $con->prepare(
    "SELECT c.*, r.admin_remarks, r.date_replied
     FROM contactus c
     LEFT JOIN replies r ON r.feedback_id = c.id
     $whereSQL
     ORDER BY c.$sort $order
     LIMIT ? OFFSET ?"
);
$dataStmt->bind_param($dataTypes, ...$dataVals);
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
    <title>Feedback Management | BizTracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        background: rgb(235, 235, 235);
    }
    .nav-link { transition: all 0.3s ease; }
    .nav-link:hover { background-color: rgba(255,255,255,0.1); }
    .nav-link.active { background-color: rgba(255,255,255,0.1); border-left: 4px solid #fff; }
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
    .btn {
        display: inline-block; padding: 8px 16px; border-radius: 4px;
        text-align: center; text-decoration: none; font-size: 14px;
        transition: transform 0.2s ease-in-out, background-color 0.3s ease; color: white;
    }
    .btn-edit { background-color: #4CAF50; }
    .btn-edit:hover { background-color: #45a049; transform: scale(1.1); }
    .btn-delete { background-color: #f44336; }
    .btn-delete:hover { background-color: #e53935; transform: scale(1.1); }
    .btn:active { transform: scale(1); }
    .action-buttons { display: flex; justify-content: center; gap: 0.5rem; }
    .filter-tabs { display: flex; border-bottom: 1px solid #e2e8f0; margin-bottom: 1rem; }
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

    /* ── Three-dot action dropdown (matches expense/budget/category) ── */
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
        /* position:fixed escapes the overflow-x:auto table container */
        display: none; position: fixed;
        min-width: 140px; background: white; border: 1px solid #e5e7eb;
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
    .item-blue  .item-icon { background: #eff6ff; color: #2563eb; }
    .item-red   .item-icon { background: #fef2f2; color: #dc2626; }
    .item-blue:hover { color: #1d4ed8; }
    .item-red:hover  { color: #b91c1c; background: #fff5f5; }
    .dropdown-divider { height: 1px; background: #f3f4f6; margin: 2px 0; }
    </style>
</head>
<body style="background:rgb(235,235,235);">

    <!-- Sidebar -->
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
                    <i class="fas fa-user text-white"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="font-medium truncate"><?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-sm text-white/70">User</p>
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
            <a href="expense.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-comments-dollar w-5 text-center"></i><span>Expense Management</span>
            </a>
            <a href="budget.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-dollar-sign w-5 text-center"></i><span>Income Management</span>
            </a>
            <a href="category.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-list w-5 text-center"></i><span>Category Management</span>
            </a>
            <a href="prediction.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-gear w-5 text-center"></i><span>Prediction Management</span>
            </a>
            <a href="feedback.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-solid fa-envelope w-5 text-center"></i><span>Feedback Management</span>
            </a>
            <div class="mt-4 mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Account Settings</p>
            </div>
            <a href="edit-profile.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-user-edit w-5 text-center"></i><span>My Profile</span>
            </a>
            <a href="change-password.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-lock w-5 text-center"></i><span>Change Password</span>
            </a>
            <a href="logout.php" onclick="return confirmLogout()" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-sign-out-alt w-5 text-center"></i><span>Log Out</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
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
                        <h1 class="text-2xl font-semibold">Feedback</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">User</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Manage Feedback</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Page Body -->
        <div style="background:rgb(235,235,235); padding-bottom:40px; padding-left:1.5rem; padding-right:1.5rem;">

            <!-- ================================================
                 CREATE FEEDBACK CARD
                 ================================================ -->
            <div class="mt-10 bg-white p-6 rounded shadow">
                <h2 class="text-2xl font-semibold text-gray-700 mb-6">Create <span class="text-[#4b6cb7]">Feedback</span></h2>
                <span class="block text-gray-600 mb-4 text-sm">Send feedback about our bookkeeping system.</span>

                <?php if ($flash): ?>
                    <div class="mb-4 p-3 rounded <?php echo $flash['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="grid grid-cols-1 gap-4">
                    <!-- CSRF token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="block mb-1 text-sm font-medium text-gray-700">
                        <label>Topic: <span class="text-red-500">*</span></label>
                        <!-- Topic suggestions via datalist -->
                        <input type="text" name="topic" id="topicInput" maxlength="200"
                               list="topicSuggestions"
                               class="w-full px-3 py-2 border rounded mt-1"
                               value="<?php echo htmlspecialchars($oldPost['topic'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                               required>
                        <datalist id="topicSuggestions">
                            <option value="Bug Report">
                            <option value="Feature Request">
                            <option value="General Enquiry">
                            <option value="Billing Issue">
                            <option value="Prediction Feedback">
                            <option value="UI/UX Suggestion">
                        </datalist>
                        <div id="topicCounter" class="char-counter">0 / 200</div>
                    </div>

                    <div class="block mb-1 text-sm font-medium text-gray-700">
                        <label>Comment: <span class="text-red-500">*</span></label>
                        <textarea name="comment" id="commentInput" maxlength="2000"
                                  class="w-full px-3 py-2 border rounded mt-1" rows="4"
                                  required><?php echo htmlspecialchars($oldPost['comment'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <div id="commentCounter" class="char-counter">0 / 2000</div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit" name="submit"
                                class="btn btn-edit bg-blue-600 text-white rounded hover:bg-blue-700 px-4 py-2">
                            Send Feedback
                        </button>
                    </div>
                </form>
            </div>

            <!-- ================================================
                 FEEDBACK ENTRIES CARD
                 ================================================ -->
            <div class="mt-10 bg-white p-6 rounded shadow">
                <h2 class="text-2xl font-semibold text-gray-700 mb-6">Feedback <span class="text-[#4b6cb7]">Entries</span></h2>

                <!-- Filter Tabs -->
                <div class="filter-tabs">
                    <a href="feedback.php?filter=all<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        All <span class="count-badge"><?php echo $allCount; ?></span>
                    </a>
                    <a href="feedback.php?filter=responded<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="filter-tab <?php echo $filter === 'responded' ? 'active' : ''; ?>">
                        Responded <span class="count-badge"><?php echo $respondedCount; ?></span>
                    </a>
                    <a href="feedback.php?filter=unresponded<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>"
                       class="filter-tab <?php echo $filter === 'unresponded' ? 'active' : ''; ?>">
                        Unresponded <span class="count-badge"><?php echo $unrespondedCount; ?></span>
                    </a>
                </div>

                <!-- Search Input -->
                <form method="GET" class="mb-4">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="flex gap-2">
                        <input type="text" name="search" placeholder="Search by topic, comment or date..."
                               value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                               class="flex-1 px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="feedback.php?filter=<?php echo htmlspecialchars($filter, ENT_QUOTES, 'UTF-8'); ?>"
                               class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <?php if ($totalRecords === 0): ?>
                        <div class="text-center py-8">
                            <p class="text-gray-500">
                                <?php
                                if ($filter === 'responded')       echo "No responded feedback found.";
                                elseif ($filter === 'unresponded') echo "No unresponded feedback found.";
                                else                               echo "No feedback submitted yet.";
                                ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <table class="min-w-full border text-sm" id="feedbackTable">
                            <thead class="bg-gray-200 text-gray-700">
                                <tr>
                                    <th class="px-4 py-2 border">No</th>
                                    <th class="px-4 py-2 border">Date Sent</th>
                                    <th class="px-4 py-2 border">Topic</th>
                                    <th class="px-4 py-2 border">Status</th>
                                    <th class="px-4 py-2 border">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($feedbackRows as $i => $row):
                                    $status       = $row['status'];
                                    $adminRemarks = $row['admin_remarks'] ?? '';
                                    $replyDate    = $row['date_replied']  ?? '';
                                ?>
                                <tr>
                                    <td class="px-4 py-2 border"><?php echo $offset + $i + 1; ?></td>
                                    <td class="px-4 py-2 border"><?php echo htmlspecialchars($row['date_sent'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-4 py-2 border"><?php echo htmlspecialchars($row['topic'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td class="px-4 py-2 border">
                                        <?php if ($status === 'Responded'): ?>
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Responded</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 border text-center">
                                        <div class="action-menu flex justify-center">
                                            <button type="button" class="action-trigger" onclick="toggleFbMenu(this)" aria-label="Actions">&vellip;</button>
                                            <div class="action-dropdown">
                                                <!-- View — opens existing feedback modal -->
                                                <button type="button" class="action-item item-blue"
                                                        data-topic="<?php echo htmlspecialchars($row['topic'],    ENT_QUOTES,'UTF-8'); ?>"
                                                        data-comment="<?php echo htmlspecialchars($row['comment'], ENT_QUOTES,'UTF-8'); ?>"
                                                        data-date="<?php echo htmlspecialchars($row['date_sent'],  ENT_QUOTES,'UTF-8'); ?>"
                                                        data-remarks="<?php echo htmlspecialchars($adminRemarks,   ENT_QUOTES,'UTF-8'); ?>"
                                                        data-replydate="<?php echo htmlspecialchars($replyDate,    ENT_QUOTES,'UTF-8'); ?>"
                                                        onclick="openFeedbackModal(this); closeFbMenus();">
                                                    <span class="item-icon"><i class="fas fa-eye"></i></span>
                                                    View
                                                </button>
                                                <div class="dropdown-divider"></div>
                                                <!-- Delete — POST + CSRF -->
                                                <form method="POST" action="feedback.php"
                                                      onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                                    <input type="hidden" name="csrf_token"
                                                           value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES,'UTF-8'); ?>">
                                                    <input type="hidden" name="delete_id" value="<?php echo (int)$row['id']; ?>">
                                                    <button type="submit" name="delete" class="action-item item-red">
                                                        <span class="item-icon"><i class="fas fa-trash"></i></span>
                                                        Delete
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
                                'filter' => $filter !== 'all' ? $filter : null,
                                'search' => $search ?: null,
                                'sort'   => $sort !== 'date_sent' ? $sort : null,
                                'order'  => $order !== 'ASC' ? strtolower($order) : null,
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
                            $half       = (int) floor($maxVisible / 2);
                            $startPage  = max(1, min($page - $half, $totalPages - $maxVisible + 1));
                            $endPage    = min($totalPages, $startPage + $maxVisible - 1);
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
                            Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $recordsPerPage, $totalRecords); ?>
                            of <?php echo number_format($totalRecords); ?> feedback<?php echo $totalRecords !== 1 ? 's' : ''; ?>
                        </p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- end bg-gray-100 -->
    </div><!-- end main-content -->

    <!-- ================================================
         FEEDBACK VIEW MODAL — single shared modal
         ================================================ -->
    <div id="viewModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 pointer-events-none transition-opacity duration-300">
        <div id="viewContent" class="bg-white p-6 rounded shadow-lg max-w-md w-full transform scale-95 transition-transform duration-300">
            <h3 class="text-xl font-semibold mb-4">Feedback Details</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Topic</label>
                    <p id="viewTopic" class="text-gray-800"></p>
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Comment</label>
                    <p id="viewComment" class="text-gray-800 whitespace-pre-line"></p>
                </div>
                <div>
                    <label class="block text-gray-700 font-medium mb-1">Date Sent</label>
                    <p id="viewDate" class="text-gray-800"></p>
                </div>
                <div id="replySection" class="hidden pt-4 mt-4 border-t border-gray-200">
                    <h4 class="text-lg font-semibold text-green-700 mb-2">Admin Response</h4>
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Remarks</label>
                        <p id="viewRemarks" class="text-gray-800 whitespace-pre-line"></p>
                    </div>
                    <div class="mt-2">
                        <label class="block text-gray-700 font-medium mb-1">Date Replied</label>
                        <p id="viewReplyDate" class="text-gray-800"></p>
                    </div>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button type="button" onclick="toggleModal()"
                        class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition">Close</button>
            </div>
        </div>
    </div>

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

    <script>
        /* ---------- Feedback view modal — reads from data attributes (XSS-safe) ---------- */
        const modal   = document.getElementById('viewModal');
        const content = document.getElementById('viewContent');

        function toggleModal() {
            const isVisible = modal.classList.contains('opacity-100');
            if (isVisible) {
                content.classList.remove('scale-100');
                content.classList.add('scale-95');
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0', 'pointer-events-none');
            } else {
                modal.classList.remove('pointer-events-none');
                setTimeout(() => {
                    modal.classList.remove('opacity-0');
                    modal.classList.add('opacity-100');
                    content.classList.remove('scale-95');
                    content.classList.add('scale-100');
                }, 10);
            }
        }

        function openFeedbackModal(btn) {
            // textContent assignment is XSS-safe — no HTML parsing
            document.getElementById('viewTopic').textContent   = btn.dataset.topic;
            document.getElementById('viewComment').textContent = btn.dataset.comment;
            document.getElementById('viewDate').textContent    = btn.dataset.date;

            const remarks   = btn.dataset.remarks || '';
            const replyDate = btn.dataset.replydate || '';
            const replySection = document.getElementById('replySection');

            if (remarks.trim() !== '') {
                replySection.classList.remove('hidden');
                document.getElementById('viewRemarks').textContent   = remarks;
                document.getElementById('viewReplyDate').textContent = replyDate;
            } else {
                replySection.classList.add('hidden');
            }
            toggleModal();
        }

        /* Close modal when clicking backdrop */
        window.addEventListener('click', function (e) {
            if (e.target === modal) toggleModal();
        });
    </script>

    <script>
        /* ---------- Character counters ---------- */
        function attachCounter(inputId, counterId, maxLen) {
            const input   = document.getElementById(inputId);
            const counter = document.getElementById(counterId);
            if (!input || !counter) return;

            function update() {
                const len = input.value.length;
                counter.textContent = len + ' / ' + maxLen;
                counter.className = 'char-counter';
                if (len >= maxLen)          counter.classList.add('over');
                else if (len >= maxLen * 0.85) counter.classList.add('warn');
            }
            input.addEventListener('input', update);
            update(); // initialise on page load (repopulates)
        }

        attachCounter('topicInput',   'topicCounter',   200);
        attachCounter('commentInput', 'commentCounter', 2000);
    </script>

    <script>
        /* ---------- Three-dot dropdown ---------- */
        function closeFbMenus() {
            document.querySelectorAll('.action-trigger.open').forEach(b => b.classList.remove('open'));
            document.querySelectorAll('.action-dropdown.open').forEach(d => {
                d.classList.remove('open');
                d.style.top = ''; d.style.bottom = '';
                d.style.left = ''; d.style.right = '';
            });
        }
        function toggleFbMenu(btn) {
            const dd      = btn.nextElementSibling;
            const wasOpen = dd.classList.contains('open');
            closeFbMenus();
            if (!wasOpen) {
                btn.classList.add('open');
                const rect       = btn.getBoundingClientRect();
                const ddW        = 140;
                const ddH        = 80;
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceRight = window.innerWidth  - rect.right;

                if (spaceBelow < ddH + 8) {
                    dd.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
                    dd.style.top    = 'auto';
                } else {
                    dd.style.top    = (rect.bottom + 4) + 'px';
                    dd.style.bottom = 'auto';
                }
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
        ['scroll', 'resize'].forEach(function(evt) {
            window.addEventListener(evt, closeFbMenus, true);
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.action-menu')) closeFbMenus();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeFbMenus();
        });
    </script>

</body>
</html>