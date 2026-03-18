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

$userStmt = $con->prepare("SELECT fullName FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
$userName = $userData['fullName'] ?? 'User';

// ============================================================
// HELPERS
// ============================================================
$allowedTypes = ['Sales', 'Expense'];

// Session-based flash message helper
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
// ADD CATEGORY — POST handler
// ============================================================
$errorMsg     = '';
$oldName      = '';
$oldType      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    // 1. CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    // 2. Sanitise inputs
    $categoryName = trim($_POST['category_name'] ?? '');
    $categoryType = trim($_POST['category_type'] ?? '');
    $oldName      = $categoryName;
    $oldType      = $categoryType;

    // 3. Validate
    if (empty($categoryName)) {
        $errorMsg = "Category name is required.";
    } elseif (strlen($categoryName) > 100) {
        $errorMsg = "Category name must be 100 characters or fewer.";
    } elseif (!preg_match('/^[\w\s\-&(),\.\/]+$/u', $categoryName)) {
        $errorMsg = "Category name contains invalid characters.";
    } elseif (!in_array($categoryType, $allowedTypes, true)) {
        $errorMsg = "Please select a valid category type.";
    } else {
        // Normalise casing
        $categoryName = ucwords(strtolower($categoryName));

        // 4. Case-insensitive duplicate check — prepared statement
        $chkStmt = $con->prepare(
            "SELECT id FROM categories
             WHERE user_id = ?
             AND LOWER(category_name) = LOWER(?)
             AND category_type = ?"
        );
        $chkStmt->bind_param("iss", $userId, $categoryName, $categoryType);
        $chkStmt->execute();
        $chkStmt->store_result();

        if ($chkStmt->num_rows > 0) {
            $errorMsg = "This category already exists.";
        } else {
            $dateCreated = date('Y-m-d H:i:s');
            $insStmt = $con->prepare(
                "INSERT INTO categories (user_id, category_name, category_type, date_created)
                 VALUES (?, ?, ?, ?)"
            );
            $insStmt->bind_param("isss", $userId, $categoryName, $categoryType, $dateCreated);
            if ($insStmt->execute()) {
                setFlash('success', 'Category added successfully!');
                header("Location: category.php");
                exit();
            } else {
                $errorMsg = "Failed to add category. Please try again.";
            }
            $insStmt->close();
        }
        $chkStmt->close();
    }
}

// ============================================================
// DELETE CATEGORY — POST handler (moved from GET)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {

    // CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $delId = (int) ($_POST['delete_id'] ?? 0);

    if ($delId > 0) {
        // Check ownership first
        $ownStmt = $con->prepare(
            "SELECT id FROM categories WHERE id = ? AND user_id = ?"
        );
        $ownStmt->bind_param("ii", $delId, $userId);
        $ownStmt->execute();
        $ownStmt->store_result();

        if ($ownStmt->num_rows === 0) {
            setFlash('error', 'Category not found.');
            header("Location: category.php");
            exit();
        }
        $ownStmt->close();

        // Check for related records
        $usageStmt = $con->prepare(
            "SELECT id FROM expenses WHERE category_id = ?
             UNION
             SELECT id FROM budgets  WHERE category_id = ?"
        );
        $usageStmt->bind_param("ii", $delId, $delId);
        $usageStmt->execute();
        $usageResult = $usageStmt->get_result();

        if ($usageResult->num_rows > 0) {
            setFlash('error', 'Category cannot be deleted as it has related income or expense records.');
        } else {
            $delStmt = $con->prepare(
                "DELETE FROM categories WHERE id = ? AND user_id = ?"
            );
            $delStmt->bind_param("ii", $delId, $userId);
            if ($delStmt->execute()) {
                setFlash('success', 'Category deleted successfully!');
            } else {
                setFlash('error', 'Failed to delete category. Please try again.');
            }
            $delStmt->close();
        }
        $usageStmt->close();
    }

    header("Location: category.php");
    exit();
}

// ============================================================
// UPDATE CATEGORY — POST handler (called from inline edit modal)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    $editId       = (int) ($_POST['category_id']   ?? 0);
    $categoryName = trim($_POST['edit_name']        ?? '');
    $categoryType = trim($_POST['edit_type']        ?? '');

    $editErrors = [];

    // Verify ownership
    $ownStmt = $con->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
    $ownStmt->bind_param("ii", $editId, $userId);
    $ownStmt->execute();
    $ownStmt->store_result();
    if ($ownStmt->num_rows === 0) {
        $editErrors[] = "Category not found or access denied.";
    }
    $ownStmt->close();

    if (empty($categoryName)) {
        $editErrors[] = "Category name is required.";
    } elseif (strlen($categoryName) > 100) {
        $editErrors[] = "Category name must be 100 characters or fewer.";
    } elseif (!preg_match('/^[\w\s\-&(),\.\/]+$/u', $categoryName)) {
        $editErrors[] = "Category name contains invalid characters.";
    }

    if (!in_array($categoryType, $allowedTypes, true)) {
        $editErrors[] = "Please select a valid category type.";
    }

    if (empty($editErrors)) {
        $categoryName = ucwords(strtolower($categoryName));

        // Duplicate check — exclude current record
        $dupStmt = $con->prepare(
            "SELECT id FROM categories
             WHERE user_id = ? AND LOWER(category_name) = LOWER(?) AND category_type = ? AND id != ?"
        );
        $dupStmt->bind_param("issi", $userId, $categoryName, $categoryType, $editId);
        $dupStmt->execute();
        $dupStmt->store_result();

        if ($dupStmt->num_rows > 0) {
            $editErrors[] = "A category with this name and type already exists.";
        } else {
            $updStmt = $con->prepare(
                "UPDATE categories SET category_name = ?, category_type = ? WHERE id = ? AND user_id = ?"
            );
            $updStmt->bind_param("ssii", $categoryName, $categoryType, $editId, $userId);
            if ($updStmt->execute()) {
                setFlash('success', 'Category updated successfully!');
            } else {
                setFlash('error', 'Failed to update category. Please try again.');
            }
            $updStmt->close();
        }
        $dupStmt->close();
    } else {
        setFlash('error', implode(' ', $editErrors));
    }

    header("Location: category.php");
    exit();
}

// ============================================================
// SORT PARAMETERS — safe whitelist
// ============================================================
$allowedSorts = ['date_created', 'category_name', 'category_type'];
$sort  = (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts, true))
         ? $_GET['sort'] : 'date_created';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
$nextOrder = $order === 'ASC' ? 'desc' : 'asc';

// ============================================================
// FETCH ALL CATEGORIES (with usage count) — single query
// ============================================================
$fetchStmt = $con->prepare(
    "SELECT
        c.id,
        c.category_name,
        c.category_type,
        c.date_created,
        (SELECT COUNT(*) FROM budgets  b WHERE b.category_id = c.id AND b.user_id  = ?) +
        (SELECT COUNT(*) FROM expenses e WHERE e.category_id = c.id AND e.user_id  = ?) AS usage_count
     FROM categories c
     WHERE c.user_id = ?
     ORDER BY $sort $order"
);
$fetchStmt->bind_param("iii", $userId, $userId, $userId);
$fetchStmt->execute();
$allCategories = $fetchStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$fetchStmt->close();

// Flash message for this render
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management | BizTracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
    body {
        font-family: 'Inter', sans-serif;
        background: rgb(235, 235, 235);
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
    .btn {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 4px;
        text-align: center;
        text-decoration: none;
        font-size: 14px;
        transition: transform 0.2s ease-in-out, background-color 0.3s ease;
        color: white;
    }
    .btn-edit {
        background-color: #4CAF50;
    }
    .btn-edit:hover {
        background-color: #45a049;
        transform: scale(1.1);
    }
    .btn-delete {
        background-color: #f44336;
    }
    .btn-delete:hover {
        background-color: #e53935;
        transform: scale(1.1);
    }
    .btn:active {
        transform: scale(1);
    }
    /* Sort arrow indicators */
    .sort-link { cursor: pointer; white-space: nowrap; }
    .sort-link .sort-icon { font-size: 11px; margin-left: 4px; opacity: 0.6; }
    .sort-link.active-sort .sort-icon { opacity: 1; color: #4b6cb7; }
    /* Usage badge */
    .usage-badge {
        display: inline-block;
        font-size: 11px;
        padding: 1px 7px;
        border-radius: 9999px;
        font-weight: 500;
    }
    /* Filter tab buttons */
    .filter-tab {
        padding: 5px 14px;
        border-radius: 9999px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid #d1d5db;
        background: white;
        transition: all 0.2s;
    }
    .filter-tab.active-tab {
        background: #4b6cb7;
        color: white;
        border-color: #4b6cb7;
    }

    /* ── Three-dot action dropdown (matches expense.php / budget.php) ── */
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
        <!-- Logo Section -->
        <div class="p-5 bg-[#182848]">
            <h2 class="text-xl font-bold flex items-center space-x-2">
                <img src="assets/images/biztracker.png" alt="BizTracker Logo" class="w-7 h-7 object-contain">
                <span>BizTracker</span>
            </h2>
        </div>

        <!-- User Profile Section -->
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

        <!-- Navigation Menu -->
        <nav class="mt-4 px-3">
            <div class="mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Main Menu</p>
            </div>

            <a href="dashboard.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a href="expense.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-comments-dollar w-5 text-center"></i>
                <span>Expense Management</span>
            </a>
            <a href="budget.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-dollar-sign w-5 text-center"></i>
                <span>Income Management</span>
            </a>
            <a href="category.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-solid fa-list w-5 text-center"></i>
                <span>Category Management</span>
            </a>
            <a href="prediction.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-gear w-5 text-center"></i>
                <span>Prediction Management</span>
            </a>
            <a href="feedback.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-envelope w-5 text-center"></i>
                <span>Feedback Management</span>
            </a>

            <div class="mt-4 mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Account Settings</p>
            </div>
            <a href="edit-profile.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-user-edit w-5 text-center"></i>
                <span>My Profile</span>
            </a>
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
                        <h1 class="text-2xl font-semibold">Category</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">User</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Manage Category List</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Page Body -->
        <div style="background:rgb(235,235,235); padding-bottom:40px; padding-left:1.5rem; padding-right:1.5rem;">

            <!-- ================================================
                 ADD CATEGORY CARD
                 ================================================ -->
            <div class="my-10 bg-white p-6 rounded shadow-md">
                <h2 class="text-2xl font-semibold text-gray-700 mb-6">Add <span class="text-[#4b6cb7]">New Category</span></h2>
                <span class="block text-gray-600 mb-4 text-sm">
                    Add your category list to sort income and expense category easier.
                </span>

                <?php
                // Session flash messages (replaces URL-based messages)
                if ($flash): ?>
                    <div class="mb-4 p-3 rounded <?php echo $flash['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMsg)): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                        <?php echo htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <!-- Add Category Form -->
                <form method="POST" class="mb-8">
                    <!-- CSRF token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700 font-medium mb-2" for="category_name">Category Name</label>
                            <input type="text" name="category_name" id="category_name" required
                                   maxlength="100"
                                   value="<?php echo htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8'); ?>"
                                   placeholder="e.g. Online Sales"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-gray-700 font-medium mb-2" for="category_type">Category Type</label>
                            <select name="category_type" id="category_type" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                                <option value="">-- Select Type --</option>
                                <option value="Sales"   <?php echo $oldType === 'Sales'   ? 'selected' : ''; ?>>Sales</option>
                                <option value="Expense" <?php echo $oldType === 'Expense' ? 'selected' : ''; ?>>Expense</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit" name="submit"
                                class="btn btn-edit px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Add Category
                        </button>
                    </div>
                </form>
            </div>

            <!-- ================================================
                 CATEGORY TABLE CARD
                 ================================================ -->
            <div class="my-10 bg-white p-6 rounded shadow-md">
                <h2 class="text-2xl font-semibold text-gray-700 mb-6">My <span class="text-[#4b6cb7]">Categories</span></h2>

                <!-- Filter tabs: All / Sales / Expense -->
                <div class="flex gap-2 mb-4 flex-wrap">
                    <button class="filter-tab active-tab" onclick="setFilter('all', this)">All</button>
                    <button class="filter-tab" onclick="setFilter('sales', this)">
                        <i class="fas fa-chart-line mr-1 text-blue-500"></i>Sales
                    </button>
                    <button class="filter-tab" onclick="setFilter('expense', this)">
                        <i class="fas fa-coins mr-1 text-red-500"></i>Expense
                    </button>
                </div>

                <!-- Search Input -->
                <div class="mb-4">
                    <input type="text" id="searchInput" placeholder="Search categories..."
                           class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400">
                </div>

                <div class="overflow-x-auto">
                    <table id="categoryTable" class="min-w-full border border-gray-300 text-sm text-left">
                        <thead class="bg-blue-100">
                            <tr>
                                <th class="px-4 py-2 border">No</th>
                                <th class="px-4 py-2 border">
                                    <a href="?sort=category_name&order=<?php echo $sort === 'category_name' ? $nextOrder : 'asc'; ?>"
                                       class="sort-link <?php echo $sort === 'category_name' ? 'active-sort' : ''; ?>">
                                        Category Name
                                        <span class="sort-icon">
                                            <?php if ($sort === 'category_name') echo $order === 'ASC' ? '▲' : '▼'; else echo '⇅'; ?>
                                        </span>
                                    </a>
                                </th>
                                <th class="px-4 py-2 border">
                                    <a href="?sort=category_type&order=<?php echo $sort === 'category_type' ? $nextOrder : 'asc'; ?>"
                                       class="sort-link <?php echo $sort === 'category_type' ? 'active-sort' : ''; ?>">
                                        Type
                                        <span class="sort-icon">
                                            <?php if ($sort === 'category_type') echo $order === 'ASC' ? '▲' : '▼'; else echo '⇅'; ?>
                                        </span>
                                    </a>
                                </th>
                                <th class="px-4 py-2 border">
                                    <a href="?sort=date_created&order=<?php echo $sort === 'date_created' ? $nextOrder : 'desc'; ?>"
                                       class="sort-link <?php echo $sort === 'date_created' ? 'active-sort' : ''; ?>">
                                        Date Created
                                        <span class="sort-icon">
                                            <?php if ($sort === 'date_created') echo $order === 'ASC' ? '▲' : '▼'; else echo '⇅'; ?>
                                        </span>
                                    </a>
                                </th>
                                <th class="px-4 py-2 border">Usage</th>
                                <th class="px-4 py-2 border">Action</th>
                            </tr>
                        </thead>
                        <tbody id="categoryTableBody">
                            <?php
                            $count = 1;
                            foreach ($allCategories as $row):
                                $isSales   = $row['category_type'] === 'Sales';
                                $typeColor = $isSales ? 'bg-blue-50' : 'bg-red-50';
                                $typeBadgeClass = $isSales
                                    ? 'usage-badge bg-blue-100 text-blue-800'
                                    : 'usage-badge bg-red-100 text-red-800';
                                $usageCount = (int) $row['usage_count'];
                                $canDelete  = $usageCount === 0;
                            ?>
                            <tr class="categoryRow <?php echo $typeColor; ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($row['category_name']), ENT_QUOTES, 'UTF-8'); ?>"
                                data-type="<?php echo htmlspecialchars(strtolower($row['category_type']), ENT_QUOTES, 'UTF-8'); ?>">

                                <td class="px-4 py-2 border rowNum"><?php echo $count++; ?></td>

                                <td class="px-4 py-2 border categoryName">
                                    <?php echo htmlspecialchars($row['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td class="px-4 py-2 border categoryType">
                                    <span class="<?php echo $typeBadgeClass; ?>">
                                        <?php echo htmlspecialchars($row['category_type'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>

                                <td class="px-4 py-2 border">
                                    <?php echo date('d M Y, H:i', strtotime($row['date_created'])); ?>
                                </td>

                                <td class="px-4 py-2 border text-center">
                                    <?php if ($usageCount > 0): ?>
                                        <span class="usage-badge bg-gray-100 text-gray-700"
                                              title="Used in <?php echo $usageCount; ?> record(s)">
                                            <?php echo $usageCount; ?> record<?php echo $usageCount !== 1 ? 's' : ''; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">Unused</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-2 border text-center">
                                    <div class="action-menu flex justify-center">
                                        <button type="button" class="action-trigger" onclick="toggleCatMenu(this)" aria-label="Actions">&vellip;</button>
                                        <div class="action-dropdown">
                                            <!-- Edit — opens inline modal -->
                                            <button type="button" class="action-item item-blue"
                                                    data-id="<?php echo (int)$row['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['category_name'], ENT_QUOTES,'UTF-8'); ?>"
                                                    data-type="<?php echo htmlspecialchars($row['category_type'], ENT_QUOTES,'UTF-8'); ?>"
                                                    onclick="openCatEditModal(this); closeCatMenus();">
                                                <span class="item-icon"><i class="fas fa-pen"></i></span>
                                                Edit
                                            </button>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($canDelete): ?>
                                                <!-- Delete — POST + CSRF -->
                                                <form method="POST" action="category.php"
                                                      onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                    <input type="hidden" name="csrf_token"
                                                           value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES,'UTF-8'); ?>">
                                                    <input type="hidden" name="delete_id" value="<?php echo (int)$row['id']; ?>">
                                                    <button type="submit" name="delete" class="action-item item-red">
                                                        <span class="item-icon"><i class="fas fa-trash"></i></span>
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <!-- Cannot delete — has linked records -->
                                                <button type="button" class="action-item item-red opacity-50"
                                                        onclick="closeCatMenus(); alert('This category has <?php echo $usageCount; ?> linked record(s).\n\nReassign or remove those records from the Income or Expense pages first, then return here to delete.')">
                                                    <span class="item-icon"><i class="fas fa-trash"></i></span>
                                                    Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($allCategories)): ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-gray-400">
                                        No categories found. Add your first category above.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Buttons -->
                <div class="flex justify-between items-center mt-6">
                    <span id="pageInfo" class="text-sm text-gray-500"></span>
                    <div class="flex gap-2">
                        <button id="prevBtn" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Previous</button>
                        <button id="nextBtn" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Next</button>
                    </div>
                </div>
            </div>

        </div><!-- end bg-gray-100 -->
    </div><!-- end main-content -->

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
        /* ---------- Search, filter tabs, pagination, row renumbering ---------- */
        const searchInput = document.getElementById('searchInput');
        const rows        = Array.from(document.querySelectorAll('.categoryRow'));
        const rowsPerPage = 10;
        let currentPage   = 1;
        let activeFilter  = 'all';

        function setFilter(filter, btn) {
            activeFilter = filter;
            currentPage  = 1;
            document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active-tab'));
            btn.classList.add('active-tab');
            renderTable();
        }

        function renderTable() {
            const searchTerm  = searchInput.value.toLowerCase().trim();
            const visibleRows = [];

            rows.forEach(row => {
                row.style.display = 'none';
                const name = row.dataset.name;
                const type = row.dataset.type;

                const matchesSearch = !searchTerm || name.includes(searchTerm) || type.includes(searchTerm);
                const matchesFilter = activeFilter === 'all' || type === activeFilter;

                if (matchesSearch && matchesFilter) {
                    visibleRows.push(row);
                }
            });

            const start = (currentPage - 1) * rowsPerPage;
            const end   = start + rowsPerPage;

            // Show paginated slice and renumber rows correctly
            visibleRows.slice(start, end).forEach((row, idx) => {
                row.style.display = '';
                row.querySelector('.rowNum').textContent = start + idx + 1;
            });

            // Update pagination controls
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            const pageInfo = document.getElementById('pageInfo');

            prevBtn.disabled = currentPage === 1;
            nextBtn.disabled = end >= visibleRows.length;

            const from = visibleRows.length === 0 ? 0 : start + 1;
            const to   = Math.min(end, visibleRows.length);
            pageInfo.textContent = visibleRows.length > 0
                ? `Showing ${from}–${to} of ${visibleRows.length} categor${visibleRows.length !== 1 ? 'ies' : 'y'}`
                : 'No categories match your search.';
        }

        searchInput.addEventListener('input', () => {
            currentPage = 1;
            renderTable();
        });

        document.getElementById('prevBtn').addEventListener('click', () => {
            if (currentPage > 1) { currentPage--; renderTable(); }
        });

        document.getElementById('nextBtn').addEventListener('click', () => {
            currentPage++;
            renderTable();
        });

        document.addEventListener('DOMContentLoaded', renderTable);
    </script>

    <!-- ================================================
         EDIT CATEGORY MODAL
         ================================================ -->
    <div id="editCatModal" class="fixed hidden inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded shadow-md max-w-md w-full relative">
            <button onclick="closeCatEditModal()"
                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl font-bold">&times;</button>

            <h3 class="text-xl font-bold mb-5">Edit <span class="text-[#4b6cb7]">Category</span></h3>

            <form method="POST" action="category.php" id="editCatForm">
                <input type="hidden" name="csrf_token"
                       value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="category_id" id="editCatId">

                <!-- Category Name -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Category Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="edit_name" id="editCatName" maxlength="100" required
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400">
                </div>

                <!-- Category Type -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Category Type <span class="text-red-500">*</span>
                    </label>
                    <select name="edit_type" id="editCatType" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400 bg-white">
                        <option value="Sales">Sales</option>
                        <option value="Expense">Expense</option>
                    </select>
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button type="button" onclick="closeCatEditModal()"
                            class="px-5 py-2 bg-gray-400 hover:bg-gray-500 text-white rounded">Cancel</button>
                    <button type="submit" name="update_category"
                            class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-medium">
                        <i class="fas fa-save mr-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================================
         SCRIPTS — three-dot dropdown + edit modal
         ============================================================ -->
    <script>
        /* ---------- Three-dot dropdown ---------- */
        function closeCatMenus() {
            document.querySelectorAll('.action-trigger.open').forEach(b => b.classList.remove('open'));
            document.querySelectorAll('.action-dropdown.open').forEach(d => {
                d.classList.remove('open');
                d.style.top = ''; d.style.bottom = '';
                d.style.left = ''; d.style.right = '';
            });
        }
        function toggleCatMenu(btn) {
            const dd      = btn.nextElementSibling;
            const wasOpen = dd.classList.contains('open');
            closeCatMenus();
            if (!wasOpen) {
                btn.classList.add('open');
                const rect       = btn.getBoundingClientRect();
                const ddW        = 150;
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
            window.addEventListener(evt, closeCatMenus, true);
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.action-menu')) closeCatMenus();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeCatMenus(); closeCatEditModal(); }
        });
    </script>

    <script>
        /* ---------- Edit category modal ---------- */
        function openCatEditModal(btn) {
            document.getElementById('editCatId').value   = btn.dataset.id;
            document.getElementById('editCatName').value = btn.dataset.name;

            const sel = document.getElementById('editCatType');
            for (let i = 0; i < sel.options.length; i++) {
                sel.options[i].selected = (sel.options[i].value === btn.dataset.type);
            }

            document.getElementById('editCatModal').classList.remove('hidden');
        }
        function closeCatEditModal() {
            document.getElementById('editCatModal').classList.add('hidden');
            document.getElementById('editCatForm').reset();
        }
        document.getElementById('editCatModal').addEventListener('click', function (e) {
            if (e.target === this) closeCatEditModal();
        });
    </script>

</body>
</html>