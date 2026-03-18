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
// HELPERS — session flash messages
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
$allowedSorts = ['date_created', 'category_name', 'amount', 'remarks'];
$sort  = (isset($_GET['sort']) && in_array($_GET['sort'], $allowedSorts, true))
         ? $_GET['sort'] : 'date_created';
$order = (isset($_GET['order']) && strtolower($_GET['order']) === 'asc') ? 'ASC' : 'DESC';
$nextOrder = $order === 'ASC' ? 'desc' : 'asc';

// ============================================================
// PAGINATION
// ============================================================
$recordsPerPage = 20;
$page   = (isset($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] > 0)
          ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// ============================================================
// SEARCH & DATE-RANGE FILTERS
// ============================================================
$search    = trim($_GET['search'] ?? '');
$filterCat = (int) ($_GET['filter_cat'] ?? 0);
$dateFrom  = trim($_GET['date_from'] ?? '');
$dateTo    = trim($_GET['date_to']   ?? '');

// Quick-date helper
$today     = date('Y-m-d');
$quickDate = trim($_GET['quick_date'] ?? '');
if ($quickDate === 'this_month') {
    $dateFrom = date('Y-m-01');
    $dateTo   = date('Y-m-t');
} elseif ($quickDate === 'last_month') {
    $dateFrom = date('Y-m-01', strtotime('first day of last month'));
    $dateTo   = date('Y-m-t',  strtotime('last day of last month'));
} elseif ($quickDate === 'this_year') {
    $dateFrom = date('Y-01-01');
    $dateTo   = date('Y-12-31');
}

// ============================================================
// ADD INCOME — POST handler
// ============================================================
$errors  = [];
$oldPost = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    // 1. CSRF check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $amount     = trim($_POST['amount']   ?? '');
    $remarks    = trim($_POST['remarks']  ?? '');
    $oldPost    = ['category_id' => $categoryId, 'amount' => $amount, 'remarks' => $remarks];

    // 2. Validate category — must belong to this user and be Sales type
    if ($categoryId <= 0) {
        $errors[] = "Category is required.";
    } else {
        $catChk = $con->prepare(
            "SELECT id, category_name FROM categories
             WHERE id = ? AND user_id = ? AND category_type = 'Sales'"
        );
        $catChk->bind_param("ii", $categoryId, $userId);
        $catChk->execute();
        $catRow = $catChk->get_result()->fetch_assoc();
        $catChk->close();
        if (!$catRow) {
            $errors[] = "Invalid category selected.";
        }
    }

    // 3. Validate amount
    if ($amount === '' || $amount === null) {
        $errors[] = "Amount is required.";
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = "Please enter a valid positive amount.";
    }

    // 4. Validate remarks length
    if (strlen($remarks) > 255) {
        $errors[] = "Remarks must be 255 characters or fewer.";
    }

    // 5. Handle receipt upload
    $receiptName = '';
    if (!empty($_FILES['receipt']['name'])) {
        $targetDir   = "uploads/";
        $fileExt     = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxFileSize  = 2 * 1024 * 1024; // 2 MB

        // Validate real MIME type — not just extension
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($_FILES['receipt']['tmp_name']);

        if (!in_array($fileExt, $allowedExts, true)) {
            $errors[] = "Only JPG, JPEG, PNG and PDF files are allowed.";
        } elseif (!in_array($realMime, $allowedMimes, true)) {
            $errors[] = "File content does not match its extension. Upload rejected.";
        } elseif ($_FILES['receipt']['size'] > $maxFileSize) {
            $errors[] = "File size too large. Maximum 2MB allowed.";
        } elseif ($_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload failed. Please try again.";
        } else {
            // Ensure upload directory exists
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $receiptName    = uniqid('rcpt_', true) . '.' . $fileExt;
            $targetFilePath = $targetDir . $receiptName;
            if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $targetFilePath)) {
                $errors[] = "Error saving uploaded file.";
                $receiptName = '';
            }
        }
    }

    // 6. Insert if no errors — prepared statement
    if (empty($errors)) {
        $amountVal   = (float) $amount;
        $dateCreated = date('Y-m-d H:i:s');

        $insStmt = $con->prepare(
            "INSERT INTO budgets (user_id, category_id, amount, remarks, receipt, date_created)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $insStmt->bind_param("iidsss", $userId, $categoryId, $amountVal, $remarks, $receiptName, $dateCreated);
        if ($insStmt->execute()) {
            setFlash('success', 'Income added successfully!');
            header("Location: budget.php");
            exit();
        } else {
            $errors[] = "Failed to save income record. Please try again.";
        }
        $insStmt->close();
    }
}

// ============================================================
// DELETE INCOME — POST handler (moved from GET)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $delId = (int) ($_POST['delete_id'] ?? 0);
    if ($delId > 0) {
        // Fetch receipt filename before deleting (to clean up the file)
        $recStmt = $con->prepare(
            "SELECT receipt FROM budgets WHERE id = ? AND user_id = ?"
        );
        $recStmt->bind_param("ii", $delId, $userId);
        $recStmt->execute();
        $recRow = $recStmt->get_result()->fetch_assoc();
        $recStmt->close();

        if ($recRow) {
            $delStmt = $con->prepare(
                "DELETE FROM budgets WHERE id = ? AND user_id = ?"
            );
            $delStmt->bind_param("ii", $delId, $userId);
            if ($delStmt->execute()) {
                // Remove the uploaded file if it exists
                if (!empty($recRow['receipt'])) {
                    $filePath = 'uploads/' . basename($recRow['receipt']);
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                setFlash('success', 'Income deleted successfully!');
            } else {
                setFlash('error', 'Failed to delete record. Please try again.');
            }
            $delStmt->close();
        } else {
            setFlash('error', 'Record not found.');
        }
    }

    header("Location: budget.php");
    exit();
}

// ============================================================
// UPDATE INCOME — POST handler (called from inline edit modal)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_budget'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    $editId     = (int) ($_POST['budget_id']       ?? 0);
    $categoryId = (int) ($_POST['edit_category_id'] ?? 0);
    $amount     = trim($_POST['edit_amount']         ?? '');
    $remarks    = trim($_POST['edit_remarks']        ?? '');
    $editDate   = trim($_POST['edit_date']           ?? '');

    $editErrors = [];

    // Verify record ownership
    $ownStmt = $con->prepare("SELECT id, receipt FROM budgets WHERE id = ? AND user_id = ?");
    $ownStmt->bind_param("ii", $editId, $userId);
    $ownStmt->execute();
    $ownRow = $ownStmt->get_result()->fetch_assoc();
    $ownStmt->close();

    if (!$ownRow) {
        $editErrors[] = "Record not found or access denied.";
    }

    // Validate category — must belong to this user and be Sales type
    if ($categoryId <= 0) {
        $editErrors[] = "Category is required.";
    } else {
        $catChk2 = $con->prepare(
            "SELECT id FROM categories WHERE id = ? AND user_id = ? AND category_type = 'Sales'"
        );
        $catChk2->bind_param("ii", $categoryId, $userId);
        $catChk2->execute();
        if (!$catChk2->get_result()->fetch_assoc()) {
            $editErrors[] = "Invalid category selected.";
        }
        $catChk2->close();
    }

    if ($amount === '') {
        $editErrors[] = "Amount is required.";
    } elseif (!is_numeric($amount) || (float)$amount <= 0) {
        $editErrors[] = "Please enter a valid positive amount.";
    }

    if (strlen($remarks) > 255) {
        $editErrors[] = "Remarks must be 255 characters or fewer.";
    }

    // Validate date
    if (empty($editDate)) {
        $editErrors[] = "Date is required.";
    } else {
        $parsedDate = DateTime::createFromFormat('Y-m-d', $editDate);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $editDate) {
            $editErrors[] = "Please enter a valid date.";
        }
    }

    // Handle optional new receipt upload
    $newReceiptName = null; // null = keep existing
    if (!empty($_FILES['edit_receipt']['name'])) {
        $targetDir    = "uploads/";
        $fileExt      = strtolower(pathinfo($_FILES['edit_receipt']['name'], PATHINFO_EXTENSION));
        $allowedExts  = ['jpg', 'jpeg', 'png', 'pdf'];
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxFileSize  = 2 * 1024 * 1024;
        $finfo        = new finfo(FILEINFO_MIME_TYPE);
        $realMime     = $finfo->file($_FILES['edit_receipt']['tmp_name']);

        if (!in_array($fileExt, $allowedExts, true)) {
            $editErrors[] = "Unsupported file format. Only JPG, JPEG, PNG and PDF are allowed.";
        } elseif (!in_array($realMime, $allowedMimes, true)) {
            $editErrors[] = "File content does not match its extension.";
        } elseif ($_FILES['edit_receipt']['size'] > $maxFileSize) {
            $editErrors[] = "File too large. Maximum 2MB allowed.";
        } elseif ($_FILES['edit_receipt']['error'] !== UPLOAD_ERR_OK) {
            $editErrors[] = "File upload failed. Please try again.";
        } else {
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $newReceiptName = uniqid('rcpt_', true) . '.' . $fileExt;
            if (!move_uploaded_file($_FILES['edit_receipt']['tmp_name'], $targetDir . $newReceiptName)) {
                $editErrors[] = "Error saving uploaded file.";
                $newReceiptName = null;
            }
        }
    }

    if (empty($editErrors) && $ownRow) {
        $amountVal    = (float) $amount;
        $dateCreated  = $editDate . ' 00:00:00';

        if ($newReceiptName !== null) {
            // Delete old receipt file if replaced
            if (!empty($ownRow['receipt'])) {
                $old = 'uploads/' . basename($ownRow['receipt']);
                if (file_exists($old)) unlink($old);
            }
            $updStmt = $con->prepare(
                "UPDATE budgets SET category_id=?, amount=?, remarks=?, receipt=?, date_created=? WHERE id=? AND user_id=?"
            );
            $updStmt->bind_param("idsssii", $categoryId, $amountVal, $remarks, $newReceiptName, $dateCreated, $editId, $userId);
        } else {
            $updStmt = $con->prepare(
                "UPDATE budgets SET category_id=?, amount=?, remarks=?, date_created=? WHERE id=? AND user_id=?"
            );
            $updStmt->bind_param("idssii", $categoryId, $amountVal, $remarks, $dateCreated, $editId, $userId);
        }

        if ($updStmt->execute()) {
            setFlash('success', 'Income updated successfully!');
        } else {
            setFlash('error', 'Failed to update income. Please try again.');
        }
        $updStmt->close();
    } else {
        setFlash('error', implode(' ', $editErrors));
    }

    header("Location: budget.php");
    exit();
}

// ============================================================
// BULK DELETE — POST handler with CSRF + per-ID ownership check
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request.');
    }

    $idsToDelete = $_POST['ids'] ?? [];
    $deleted     = 0;

    if (is_array($idsToDelete) && !empty($idsToDelete)) {
        foreach ($idsToDelete as $rawId) {
            $id = (int) $rawId;
            if ($id <= 0) continue;

            // Verify ownership per ID — prepared statement
            $chkStmt = $con->prepare(
                "SELECT receipt FROM budgets WHERE id = ? AND user_id = ?"
            );
            $chkStmt->bind_param("ii", $id, $userId);
            $chkStmt->execute();
            $chkRow = $chkStmt->get_result()->fetch_assoc();
            $chkStmt->close();

            if (!$chkRow) continue; // not owned by this user — skip silently

            $bdStmt = $con->prepare(
                "DELETE FROM budgets WHERE id = ? AND user_id = ?"
            );
            $bdStmt->bind_param("ii", $id, $userId);
            if ($bdStmt->execute()) {
                if (!empty($chkRow['receipt'])) {
                    $fp = 'uploads/' . basename($chkRow['receipt']);
                    if (file_exists($fp)) @unlink($fp);
                }
                $deleted++;
            }
            $bdStmt->close();
        }
    }

    setFlash('success', $deleted . ' income record' . ($deleted !== 1 ? 's' : '') . ' deleted successfully!');
    header("Location: budget.php");
    exit();
}

// ============================================================
// FETCH SALES CATEGORIES (for form dropdown)
// ============================================================
$catStmt = $con->prepare(
    "SELECT id, category_name FROM categories
     WHERE user_id = ? AND category_type = 'Sales'
     ORDER BY category_name ASC"
);
$catStmt->bind_param("i", $userId);
$catStmt->execute();
$categoriesResult = $catStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$catStmt->close();

// ============================================================
// BUILD DYNAMIC WHERE CLAUSE (shared by COUNT + data query)
// ============================================================
$whereParts  = ["b.user_id = ?", "c.category_type = 'Sales'"];
$bindTypes   = "i";
$bindValues  = [$userId];

if (!empty($search)) {
    if (ctype_digit($search)) {
        // Pure number — match ID exactly OR match category/remarks as text
        $like = '%' . $search . '%';
        $whereParts[] = "(b.id = ? OR c.category_name LIKE ? OR b.remarks LIKE ?)";
        $bindTypes  .= "iss";
        $bindValues[] = (int) $search;
        $bindValues[] = $like;
        $bindValues[] = $like;
    } else {
        // Text — match category name or remarks
        $like = '%' . $search . '%';
        $whereParts[] = "(c.category_name LIKE ? OR b.remarks LIKE ?)";
        $bindTypes  .= "ss";
        $bindValues[] = $like;
        $bindValues[] = $like;
    }
}
if ($filterCat > 0) {
    $whereParts[] = "b.category_id = ?";
    $bindTypes  .= "i";
    $bindValues[] = $filterCat;
}
if (!empty($dateFrom)) {
    $whereParts[] = "DATE(b.date_created) >= ?";
    $bindTypes  .= "s";
    $bindValues[] = $dateFrom;
}
if (!empty($dateTo)) {
    $whereParts[] = "DATE(b.date_created) <= ?";
    $bindTypes  .= "s";
    $bindValues[] = $dateTo;
}

$whereSQL = "WHERE " . implode(" AND ", $whereParts);

// ============================================================
// COUNT QUERY — uses same WHERE as data query
// ============================================================
$countSQL  = "SELECT COUNT(*) AS total, COALESCE(SUM(b.amount),0) AS grand_total,
                     COALESCE(AVG(b.amount),0) AS avg_amount
              FROM budgets b
              JOIN categories c ON b.category_id = c.id
              $whereSQL";
$cntStmt   = $con->prepare($countSQL);
$cntStmt->bind_param($bindTypes, ...$bindValues);
$cntStmt->execute();
$cntRow       = $cntStmt->get_result()->fetch_assoc();
$cntStmt->close();
$totalRecords = (int) $cntRow['total'];
$grandTotal   = (float) $cntRow['grand_total'];
$avgAmount    = (float) $cntRow['avg_amount'];
$totalPages   = max(1, (int) ceil($totalRecords / $recordsPerPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $recordsPerPage;

// ============================================================
// DATA QUERY — single prepared statement with LIMIT always
// ============================================================
$dataSQL  = "SELECT b.*, c.category_name
             FROM budgets b
             JOIN categories c ON b.category_id = c.id
             $whereSQL
             ORDER BY $sort $order
             LIMIT ? OFFSET ?";
$dataBindTypes  = $bindTypes . "ii";
$dataBindValues = array_merge($bindValues, [$recordsPerPage, $offset]);
$dataStmt = $con->prepare($dataSQL);
$dataStmt->bind_param($dataBindTypes, ...$dataBindValues);
$dataStmt->execute();
$budgetRows = $dataStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$dataStmt->close();

// ============================================================
// [NEW] THIS MONTH vs LAST MONTH — income trend for summary bar
// ============================================================
$thisMonthStart = date('Y-m-01');
$thisMonthEnd   = date('Y-m-t');
$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
$lastMonthEnd   = date('Y-m-t',  strtotime('last day of last month'));

$trendStmt = $con->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN DATE(b.date_created) BETWEEN ? AND ? THEN b.amount ELSE 0 END), 0) AS this_month,
        COALESCE(SUM(CASE WHEN DATE(b.date_created) BETWEEN ? AND ? THEN b.amount ELSE 0 END), 0) AS last_month
     FROM budgets b
     JOIN categories c ON b.category_id = c.id
     WHERE b.user_id = ? AND c.category_type = 'Sales'"
);
$trendStmt->bind_param("ssssi", $thisMonthStart, $thisMonthEnd, $lastMonthStart, $lastMonthEnd, $userId);
$trendStmt->execute();
$trendRow    = $trendStmt->get_result()->fetch_assoc();
$trendStmt->close();
$thisMonthIncome = (float) $trendRow['this_month'];
$lastMonthIncome = (float) $trendRow['last_month'];
$trendDiff       = $thisMonthIncome - $lastMonthIncome;
$trendPct        = $lastMonthIncome > 0
                   ? round(($trendDiff / $lastMonthIncome) * 100, 1)
                   : ($thisMonthIncome > 0 ? 100 : 0);
$trendUp         = $trendDiff >= 0;

// ============================================================
// [NEW] CATEGORY BREAKDOWN — total income per Sales category
//       (all-time, unfiltered — used for the donut chart)
// ============================================================
$catBreakdownStmt = $con->prepare(
    "SELECT c.category_name, COALESCE(SUM(b.amount), 0) AS cat_total
     FROM categories c
     LEFT JOIN budgets b ON b.category_id = c.id AND b.user_id = ?
     WHERE c.user_id = ? AND c.category_type = 'Sales'
     GROUP BY c.id, c.category_name
     ORDER BY cat_total DESC
     LIMIT 8"
);
$catBreakdownStmt->bind_param("ii", $userId, $userId);
$catBreakdownStmt->execute();
$catBreakdownRows = $catBreakdownStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$catBreakdownStmt->close();

// ============================================================
// [NEW] INCOME VS MONTHLY AVERAGE — compare this month's income
//       per Sales category against that category's own all-time
//       monthly average income.
//
//       WHY this approach: Income (Sales) categories and Expense
//       categories are entirely separate rows in the categories
//       table with different IDs — cross-referencing them by ID
//       always returns 0. Instead we show "this month vs my own
//       monthly average" per Sales category, which is always
//       meaningful, always non-zero for active categories, and
//       directly useful for spotting underperforming income lines.
// ============================================================
$budgetVsActualStmt = $con->prepare(
    "SELECT
        c.id              AS cat_id,
        c.category_name,
        -- This month's total income for this category
        COALESCE(SUM(CASE WHEN DATE(b.date_created) BETWEEN ? AND ?
                          THEN b.amount ELSE 0 END), 0)        AS this_month_total,
        -- All-time monthly average: total / number of distinct months with records
        COALESCE(
            SUM(b.amount) /
            NULLIF(COUNT(DISTINCT DATE_FORMAT(b.date_created, '%Y-%m')), 0),
            0
        )                                                       AS monthly_avg
     FROM categories c
     LEFT JOIN budgets b ON b.category_id = c.id AND b.user_id = ?
     WHERE c.user_id = ? AND c.category_type = 'Sales'
     GROUP BY c.id, c.category_name
     HAVING this_month_total > 0 OR monthly_avg > 0
     ORDER BY this_month_total DESC
     LIMIT 6"
);
$budgetVsActualStmt->bind_param("ssii", $thisMonthStart, $thisMonthEnd, $userId, $userId);
$budgetVsActualStmt->execute();
$budgetVsActualRows = $budgetVsActualStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$budgetVsActualStmt->close();

// Flash message
$flash = getFlash();

// Repopulate category name for Alpine.js after error
$oldCatName = '';
if (!empty($oldPost['category_id'])) {
    foreach ($categoriesResult as $c) {
        if ((int)$c['id'] === (int)$oldPost['category_id']) {
            $oldCatName = $c['category_name'];
            break;
        }
    }
}

// JSON-encode chart data safely for JS
$chartLabels = json_encode(array_column($catBreakdownRows, 'category_name'));
$chartValues = json_encode(array_map(fn($r) => (float)$r['cat_total'], $catBreakdownRows));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Management | BizTracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <!-- [NEW] Chart.js for category donut chart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
    body {
        font-family: 'Inter', sans-serif;
    }
    .sidebar {
        width: 280px;
        transition: all 0.3s ease;
        background: linear-gradient(180deg, #4b6cb7 0%, #182848 100%);
    }
    .sidebar.collapsed {
        width: 0;
        overflow: hidden;
    }
    .main-content {
        margin-left: 280px;
        transition: all 0.3s ease;
        background: rgb(235, 235, 235);
    }
    .main-content.collapsed {
        margin-left: 0;
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
        .sidebar.collapsed { width: 280px; margin-left: -280px; }
        .sidebar.collapsed.active { margin-left: 0; }
        .main-content { margin-left: 0; }
        .main-content.active { margin-left: 280px; }
        .main-content.collapsed { margin-left: 0; }
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
    .error-message {
        color: #e53e3e;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }
    /* Sort arrow indicators */
    .sort-link { cursor: pointer; white-space: nowrap; text-decoration: none; color: inherit; }
    .sort-link .sort-icon { font-size: 11px; margin-left: 4px; opacity: 0.5; }
    .sort-link.active-sort .sort-icon { opacity: 1; color: #4b6cb7; }
    /* Quick-date filter tabs */
    .quick-tab {
        padding: 4px 12px;
        border-radius: 9999px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid #d1d5db;
        background: white;
        transition: all 0.2s;
        text-decoration: none;
        color: #374151;
        display: inline-block;
    }
    .quick-tab:hover { background: #f3f4f6; }
    .quick-tab.active-tab { background: #4b6cb7; color: white; border-color: #4b6cb7; }

    /* ── Three-dot action dropdown (matches expense.php) ── */
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
        /* position:fixed lets it escape overflow-x:auto table containers */
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

    /* [NEW] Budget-vs-actual progress bars */
    .bva-bar-track {
        height: 8px;
        border-radius: 9999px;
        background: #e5e7eb;
        overflow: hidden;
        flex: 1;
    }
    .bva-bar-fill {
        height: 100%;
        border-radius: 9999px;
        transition: width 0.5s ease;
    }
    .bva-safe    { background: #22c55e; }
    .bva-warning { background: #f59e0b; }
    .bva-danger  { background: #ef4444; }

    /* [NEW] Remarks character counter */
    .char-counter {
        font-size: 11px;
        color: #9ca3af;
        text-align: right;
        margin-top: 2px;
    }
    .char-counter.near  { color: #f59e0b; }
    .char-counter.over  { color: #ef4444; }

    /* [NEW] Alert banner pulse */
    @keyframes alertPulse {
        0%, 100% { opacity: 1; }
        50%       { opacity: 0.75; }
    }
    .alert-pulse { animation: alertPulse 2.5s ease-in-out infinite; }

    /* [NEW] Trend badge */
    .trend-up   { color: #16a34a; background: #dcfce7; }
    .trend-down { color: #dc2626; background: #fee2e2; }
    .trend-flat { color: #6b7280; background: #f3f4f6; }
    .trend-badge {
        display: inline-flex; align-items: center; gap: 3px;
        padding: 2px 8px; border-radius: 9999px;
        font-size: 11px; font-weight: 600;
    }

    /* [NEW] What-if simulator panel */
    .simulator-panel {
        border: 1.5px dashed #93c5fd;
        border-radius: 8px;
        background: #eff6ff;
        padding: 16px 20px;
    }
    .simulator-result {
        font-size: 13px;
        color: #1e40af;
        background: white;
        border-radius: 6px;
        padding: 8px 12px;
        margin-top: 10px;
        border: 1px solid #bfdbfe;
        display: none;
    }
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
            <a href="budget.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-solid fa-dollar-sign w-5 text-center"></i>
                <span>Income Management</span>
            </a>
            <a href="category.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
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
            <div class="w-full px-4 sm:px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <button id="sidebarToggle"
                                class="text-white -ml-1 rounded hover:bg-white/10 transition-colors w-8 h-8 flex items-center justify-center flex-shrink-0"
                                title="Toggle sidebar">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h1 class="text-2xl font-semibold">Income</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">User</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Manage Income Resource</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Page Body -->
        <div style="background:rgb(235,235,235); padding-bottom:40px; padding-left:1.5rem; padding-right:1.5rem;">

            <!-- ================================================
                 ADD INCOME CARD
                 ================================================ -->
            <div class="mt-10 bg-white p-6 rounded shadow">
                <h2 class="text-2xl font-semibold text-gray-700 mb-6">Add <span class="text-[#4b6cb7]">Income</span></h2>

                <?php if ($flash): ?>
                    <div class="mb-4 p-3 rounded <?php echo $flash['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                        <?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                        <?php foreach ($errors as $err): ?>
                            <p><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- CSRF token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- Category — Alpine.js custom dropdown, repopulates on error -->
                    <div>
                        <div class="relative" x-data="{
                            open: false,
                            selected: '<?php echo htmlspecialchars($oldCatName, ENT_QUOTES, 'UTF-8'); ?>',
                            selectedId: '<?php echo (int)($oldPost['category_id'] ?? 0); ?>'
                        }">
                            <label class="block mb-1 text-sm font-medium text-gray-700">Category</label>
                            <div @click="open = !open"
                                 class="border rounded px-3 py-2 bg-white cursor-pointer flex justify-between items-center">
                                <span x-text="selected || '-- Select Category --'" class="text-gray-700"></span>
                                <i class="fas fa-chevron-down text-gray-400"></i>
                            </div>

                            <input type="hidden" name="category_id" :value="selectedId" required>

                            <?php if (in_array("Category is required.", $errors)): ?>
                                <p class="error-message">Category is required.</p>
                            <?php endif; ?>

                            <ul x-show="open" @click.away="open = false"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                class="absolute z-10 mt-1 max-h-60 w-full overflow-auto bg-white border rounded shadow-lg">
                                <?php foreach ($categoriesResult as $cat):
                                    $catId   = (int) $cat['id'];
                                    $catName = htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8');
                                ?>
                                    <li @click="selected = '<?php echo $catName; ?>'; selectedId = '<?php echo $catId; ?>'; open = false"
                                        class="px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                        <?php echo $catName; ?>
                                    </li>
                                <?php endforeach; ?>
                                <?php if (empty($categoriesResult)): ?>
                                    <li class="px-4 py-2 text-gray-400 text-sm">
                                        No Sales categories found.
                                        <a href="category.php" class="text-blue-500 underline">Add one</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>

                    <!-- Amount -->
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Amount (RM)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="addAmount" required
                               class="w-full px-3 py-2 border rounded <?php echo in_array("Please enter a valid positive amount.", $errors) ? 'border-red-500' : ''; ?>"
                               value="<?php echo htmlspecialchars($oldPost['amount'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <?php if (in_array("Please enter a valid positive amount.", $errors)): ?>
                            <p class="error-message">Please enter a valid positive amount.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Remarks — [NEW] with live character counter -->
                    <div class="md:col-span-2">
                        <label class="block mb-1 text-sm font-medium text-gray-700">Remarks</label>
                        <input type="text" name="remarks" id="addRemarks" maxlength="255"
                               class="w-full px-3 py-2 border rounded"
                               value="<?php echo htmlspecialchars($oldPost['remarks'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <p class="char-counter" id="addRemarksCounter">0 / 255</p>
                    </div>

                    <!-- Receipt upload -->
                    <div class="md:col-span-2">
                        <label class="block mb-1 text-sm font-medium text-gray-700">
                            Receipt (Optional — JPG, PNG, PDF, max 2MB)
                        </label>
                        <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" class="w-full">
                        <?php if (in_array("Only JPG, JPEG, PNG and PDF files are allowed.", $errors)): ?>
                            <p class="error-message">Only JPG, JPEG, PNG and PDF files are allowed.</p>
                        <?php elseif (in_array("File size too large. Maximum 2MB allowed.", $errors)): ?>
                            <p class="error-message">File size too large. Maximum 2MB allowed.</p>
                        <?php elseif (in_array("File content does not match its extension. Upload rejected.", $errors)): ?>
                            <p class="error-message">File content does not match its extension.</p>
                        <?php endif; ?>
                    </div>

                    <div class="md:col-span-2 text-right">
                        <button type="submit" name="submit"
                                class="btn btn-edit px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Add Income
                        </button>
                    </div>
                </form>
            </div>

            <!-- ================================================
                 [NEW] INSIGHTS ROW — Trend + Category Chart + Budget vs Actual
                 ================================================ -->
            <div class="mt-10 grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- [NEW] CATEGORY BREAKDOWN DONUT CHART -->
                <div class="bg-white p-6 rounded shadow">
                    <h2 class="text-lg font-semibold text-gray-700 mb-1">Income by <span class="text-[#4b6cb7]">Category</span></h2>
                    <p class="text-xs text-gray-400 mb-4">All-time breakdown across your Sales categories</p>
                    <?php if (!empty($catBreakdownRows) && array_sum(array_column($catBreakdownRows, 'cat_total')) > 0): ?>
                    <div style="position:relative; height:220px;">
                        <canvas id="categoryDonut"></canvas>
                    </div>
                    <?php else: ?>
                    <div class="flex items-center justify-center h-40 text-gray-400 text-sm">
                        <i class="fas fa-chart-pie mr-2"></i> No income data yet.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- [NEW] INCOME VS MONTHLY AVERAGE PROGRESS BARS -->
                <div class="bg-white p-6 rounded shadow">
                    <h2 class="text-lg font-semibold text-gray-700 mb-1">This Month vs. <span class="text-[#4b6cb7]">Average</span></h2>
                    <p class="text-xs text-gray-400 mb-4">
                        Current month income vs. your all-time monthly average — <strong><?php echo date('F Y'); ?></strong>
                        <span class="ml-2 text-xs text-gray-400">(Average = full bar, this month = fill)</span>
                    </p>
                    <?php if (empty($budgetVsActualRows)): ?>
                        <div class="flex items-center justify-center h-40 text-gray-400 text-sm">
                            <i class="fas fa-balance-scale mr-2"></i> No data for this month yet.
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                        <?php foreach ($budgetVsActualRows as $bva):
                            $thisMonth = (float) $bva['this_month_total'];
                            $avg       = (float) $bva['monthly_avg'];
                            // Percentage of this month vs average (capped at 150% for display)
                            $pct       = $avg > 0 ? min(150, round(($thisMonth / $avg) * 100)) : 0;
                            // For income: green = at/above average, amber = 80–99%, red = below 80%
                            $barClass  = $pct >= 100 ? 'bva-safe' : ($pct >= 80 ? 'bva-warning' : 'bva-danger');
                            // Bar fill width capped at 100 for display, real pct for label
                            $barWidth  = min(100, $pct);
                            $realPct   = $avg > 0 ? round(($thisMonth / $avg) * 100) : 0;
                        ?>
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-sm font-medium text-gray-700 truncate max-w-[140px]">
                                    <?php echo htmlspecialchars($bva['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="text-xs text-gray-500 flex-shrink-0 ml-2">
                                    RM <?php echo number_format($thisMonth, 2); ?> / avg RM <?php echo number_format($avg, 2); ?>
                                    <span class="ml-1 font-semibold <?php echo $realPct >= 100 ? 'text-green-600' : ($realPct >= 80 ? 'text-amber-500' : 'text-red-600'); ?>">
                                        (<?php echo $realPct; ?>%)
                                    </span>
                                </span>
                            </div>
                            <div class="bva-bar-track">
                                <div class="bva-bar-fill <?php echo $barClass; ?>"
                                     style="width: <?php echo $barWidth; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-400 mt-4 flex gap-3">
                            <span><span class="inline-block w-3 h-2 rounded bg-green-500 mr-1"></span>≥100% of avg</span>
                            <span><span class="inline-block w-3 h-2 rounded bg-amber-400 mr-1"></span>80–99% of avg</span>
                            <span><span class="inline-block w-3 h-2 rounded bg-red-500 mr-1"></span>&lt;80% of avg</span>
                        </p>
                    <?php endif; ?>
                </div>

            </div><!-- end insights row -->

            <!-- ================================================
                 [NEW] WHAT-IF INCOME SIMULATOR
                 Lets the user preview how adding a hypothetical income
                 entry would change the key BPNN input ratios — addresses
                 the Future Work / Simulation Mode suggestion
                 ================================================ -->
            <div class="mt-6 bg-white p-6 rounded shadow">
                <h2 class="text-lg font-semibold text-gray-700 mb-1">What-If <span class="text-[#4b6cb7]">Simulator</span>
                    <span class="ml-2 text-xs font-normal text-blue-500 bg-blue-50 px-2 py-0.5 rounded-full">Beta</span>
                </h2>
                <p class="text-xs text-gray-400 mb-4">
                    Preview how a hypothetical income entry would shift your key financial ratios — before adding it for real.
                </p>
                <div class="simulator-panel">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Hypothetical income (RM)</label>
                            <input type="number" id="simIncome" min="0" step="0.01" placeholder="e.g. 2000"
                                   class="w-full px-3 py-2 border rounded text-sm bg-white">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Hypothetical expense (RM)</label>
                            <input type="number" id="simExpense" min="0" step="0.01" placeholder="e.g. 500"
                                   class="w-full px-3 py-2 border rounded text-sm bg-white">
                        </div>
                        <div class="flex items-end">
                            <button type="button" onclick="runSimulator()"
                                    class="w-full px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-medium">
                                <i class="fas fa-calculator mr-1"></i>Run Simulation
                            </button>
                        </div>
                    </div>
                    <div class="simulator-result" id="simResult"></div>
                </div>
            </div>

            <!-- ================================================
                 GENERATE REPORT CARD
                 ================================================ -->
            <div class="mt-10 bg-white p-6 rounded shadow flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-700 mb-6">Income <span class="text-[#4b6cb7]">Report</span></h2>
                    <span class="block text-gray-600 mb-4 text-sm">
                        Generate a comprehensive report of all Income within a selected date range.
                    </span>
                    <button onclick="toggleModal()" class="btn btn-edit px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        Generate Income Report
                    </button>
                </div>
                <img src="assets/images/book.png" alt="Report illustration" class="h-24 w-auto">
            </div>

            <!-- ================================================
                 INCOME TABLE CARD
                 ================================================ -->
            <div class="mt-10 bg-white p-6 rounded shadow">
                <h2 class="text-2xl font-semibold text-gray-700 mb-6">Income <span class="text-[#4b6cb7]">Entries</span></h2>

                <!-- Summary bar — [NEW] trend badge added to Total Income card -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4 flex items-center space-x-3">
                        <div class="p-3 bg-blue-100 rounded-full text-blue-600">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Income</p>
                            <p class="text-lg font-bold text-[#182848]">RM <?php echo number_format($grandTotal, 2); ?></p>
                            <!-- Trend badge — only shown when "This month" quick filter is active -->
                            <?php if ($quickDate === 'this_month'): ?>
                            <span class="trend-badge <?php echo $trendUp ? 'trend-up' : ($trendDiff < 0 ? 'trend-down' : 'trend-flat'); ?> mt-1">
                                <i class="fas fa-arrow-<?php echo $trendUp ? 'up' : 'down'; ?>" style="font-size:9px;"></i>
                                <?php echo ($trendUp ? '+' : '') . $trendPct; ?>% vs last month
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 flex items-center space-x-3">
                        <div class="p-3 bg-green-100 rounded-full text-green-600">
                            <i class="fas fa-list-ol"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Records</p>
                            <p class="text-lg font-bold text-[#182848]"><?php echo number_format($totalRecords); ?></p>
                        </div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4 flex items-center space-x-3">
                        <div class="p-3 bg-purple-100 rounded-full text-purple-600">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase tracking-wide">Average</p>
                            <p class="text-lg font-bold text-[#182848]">RM <?php echo number_format($avgAmount, 2); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Quick date filters -->
                <div class="flex gap-2 mb-4 flex-wrap items-center">
                    <span class="text-sm text-gray-500 mr-1">Quick filter:</span>
                    <?php
                    $qParams = array_filter(['search' => $search, 'filter_cat' => $filterCat ?: null,
                                             'sort' => $sort !== 'date_created' ? $sort : null,
                                             'order' => $order !== 'DESC' ? strtolower($order) : null]);
                    ?>
                    <a href="?<?php echo http_build_query(array_merge($qParams, [])); ?>"
                       class="quick-tab <?php echo empty($quickDate) && empty($dateFrom) ? 'active-tab' : ''; ?>">All time</a>
                    <a href="?<?php echo http_build_query(array_merge($qParams, ['quick_date' => 'this_month'])); ?>"
                       class="quick-tab <?php echo $quickDate === 'this_month' ? 'active-tab' : ''; ?>">This month</a>
                    <a href="?<?php echo http_build_query(array_merge($qParams, ['quick_date' => 'last_month'])); ?>"
                       class="quick-tab <?php echo $quickDate === 'last_month' ? 'active-tab' : ''; ?>">Last month</a>
                    <a href="?<?php echo http_build_query(array_merge($qParams, ['quick_date' => 'this_year'])); ?>"
                       class="quick-tab <?php echo $quickDate === 'this_year' ? 'active-tab' : ''; ?>">This year</a>
                </div>

                <!-- Search + category filter -->
                <form method="GET" action="budget.php" class="mb-4 flex flex-wrap items-center gap-2">
                    <?php if (!empty($quickDate)): ?>
                        <input type="hidden" name="quick_date" value="<?php echo htmlspecialchars($quickDate, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>
                    <?php if (!empty($dateFrom)): ?>
                        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="date_to"   value="<?php echo htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>

                    <input type="text" name="search" id="searchInput"
                           placeholder="Search by ID, category, remarks..."
                           value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                           class="flex-1 min-w-[180px] px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400">

                    <!-- Category filter dropdown -->
                    <select name="filter_cat"
                            class="px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400 bg-white text-gray-700 text-sm">
                        <option value="">All categories</option>
                        <?php foreach ($categoriesResult as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>"
                                <?php echo $filterCat === (int)$cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                        <i class="fas fa-search mr-1"></i>Search
                    </button>

                    <?php if (!empty($search) || $filterCat > 0 || !empty($quickDate) || !empty($dateFrom)): ?>
                        <a href="budget.php" class="px-3 py-2 bg-gray-200 rounded hover:bg-gray-300 text-sm text-gray-700">
                            <i class="fas fa-times mr-1"></i>Clear
                        </a>
                    <?php endif; ?>

                    <!-- Export to CSV button — opens date-range modal -->
                    <button type="button" onclick="openCsvModal()"
                            class="px-3 py-2 bg-gray-700 text-white rounded hover:bg-gray-800 text-sm flex items-center gap-1"
                            title="Export income entries to CSV">
                        <i class="fas fa-download"></i>
                        <span class="hidden sm:inline">Export CSV</span>
                    </button>
                </form>

                <!-- Bulk-delete form wrapper -->
                <form method="POST" action="budget.php" id="bulkForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- Bulk delete action bar (shown when rows are selected) -->
                    <div id="bulkBar" class="hidden mb-3 flex items-center gap-3 p-3 bg-red-50 border border-red-200 rounded">
                        <span id="selectedCount" class="text-sm text-red-700 font-medium"></span>
                        <button type="submit" name="bulk_delete"
                                onclick="return confirm('Delete all selected income records?')"
                                class="px-4 py-1.5 bg-red-600 text-white rounded text-sm hover:bg-red-700">
                            <i class="fas fa-trash mr-1"></i>Delete selected
                        </button>
                        <button type="button" onclick="clearSelection()"
                                class="px-4 py-1.5 bg-gray-400 text-white rounded text-sm hover:bg-gray-500">
                            Cancel
                        </button>
                    </div>

                    <!-- Income Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full border text-sm" id="incomeTable">
                            <thead class="bg-gray-200 text-gray-700">
                                <tr>
                                    <!-- Select-all checkbox -->
                                    <th class="px-3 py-2 border text-center w-8">
                                        <input type="checkbox" id="selectAll" title="Select all"
                                               class="cursor-pointer">
                                    </th>
                                    <th class="px-4 py-2 border text-center">ID</th>
                                    <th class="px-4 py-2 border text-center">
                                        <a href="?<?php echo http_build_query(array_merge(['search'=>$search,'filter_cat'=>$filterCat,'quick_date'=>$quickDate,'date_from'=>$dateFrom,'date_to'=>$dateTo], ['sort'=>'category_name','order'=>$sort==='category_name'?$nextOrder:'asc'])); ?>"
                                           class="sort-link <?php echo $sort==='category_name'?'active-sort':''; ?>">
                                            Category <span class="sort-icon"><?php echo $sort==='category_name'?($order==='ASC'?'▲':'▼'):'⇅'; ?></span>
                                        </a>
                                    </th>
                                    <th class="px-4 py-2 border text-center">
                                        <a href="?<?php echo http_build_query(array_merge(['search'=>$search,'filter_cat'=>$filterCat,'quick_date'=>$quickDate,'date_from'=>$dateFrom,'date_to'=>$dateTo], ['sort'=>'amount','order'=>$sort==='amount'?$nextOrder:'desc'])); ?>"
                                           class="sort-link <?php echo $sort==='amount'?'active-sort':''; ?>">
                                            Amount (RM) <span class="sort-icon"><?php echo $sort==='amount'?($order==='ASC'?'▲':'▼'):'⇅'; ?></span>
                                        </a>
                                    </th>
                                    <th class="px-4 py-2 border text-center">Remarks</th>
                                    <th class="px-4 py-2 border text-center">Receipt</th>
                                    <th class="px-4 py-2 border text-center">
                                        <a href="?<?php echo http_build_query(array_merge(['search'=>$search,'filter_cat'=>$filterCat,'quick_date'=>$quickDate,'date_from'=>$dateFrom,'date_to'=>$dateTo], ['sort'=>'date_created','order'=>$sort==='date_created'?$nextOrder:'desc'])); ?>"
                                           class="sort-link <?php echo $sort==='date_created'?'active-sort':''; ?>">
                                            Date <span class="sort-icon"><?php echo $sort==='date_created'?($order==='ASC'?'▲':'▼'):'⇅'; ?></span>
                                        </a>
                                    </th>
                                    <th class="px-4 py-2 border text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($budgetRows)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-6 text-gray-400">
                                            <?php if ($totalRecords === 0 && empty($search) && $filterCat === 0): ?>
                                                No income records yet. Add your first entry above.
                                            <?php else: ?>
                                                No records match your current filters.
                                                <a href="budget.php" class="text-blue-500 underline ml-1">Clear filters</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($budgetRows as $i => $row): ?>
                                    <tr class="border-t">
                                        <!-- Row checkbox for bulk delete -->
                                        <td class="px-3 py-2 border text-center">
                                            <input type="checkbox" name="ids[]"
                                                   value="<?php echo (int)$row['id']; ?>"
                                                   class="row-checkbox cursor-pointer">
                                        </td>
                                        <td class="px-4 py-2 border text-center"><?php echo (int)$row['id']; ?></td>
                                        <td class="px-4 py-2 border text-center categoryName">
                                            <?php echo htmlspecialchars($row['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-4 py-2 border text-right">
                                            RM <?php echo number_format((float)$row['amount'], 2); ?>
                                        </td>
                                        <td class="px-4 py-2 border">
                                            <?php echo htmlspecialchars($row['remarks'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-4 py-2 border text-center">
                                            <?php if (!empty($row['receipt'])): ?>
                                                <!-- Receipt served via secure proxy — no file path in JS -->
                                                <button type="button"
                                                        onclick="viewReceipt(<?php echo (int)$row['id']; ?>)"
                                                        class="btn btn-edit bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-xs">
                                                    View
                                                </button>
                                            <?php else: ?>
                                                <span class="text-gray-500 text-xs">No Receipt</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-2 border">
                                            <?php echo date('d M Y, H:i', strtotime($row['date_created'])); ?>
                                        </td>
                                        <td class="px-4 py-2 border text-center">
                                            <div class="action-menu flex justify-center">
                                                <button type="button" class="action-trigger" onclick="toggleBudgetMenu(this)" aria-label="Actions">&vellip;</button>
                                                <div class="action-dropdown">
                                                    <!-- Edit — opens inline modal -->
                                                    <button type="button" class="action-item item-blue"
                                                            data-id="<?php echo (int)$row['id']; ?>"
                                                            data-category="<?php echo (int)$row['category_id']; ?>"
                                                            data-category-name="<?php echo htmlspecialchars($row['category_name'], ENT_QUOTES,'UTF-8'); ?>"
                                                            data-amount="<?php echo htmlspecialchars($row['amount'], ENT_QUOTES,'UTF-8'); ?>"
                                                            data-remarks="<?php echo htmlspecialchars($row['remarks'], ENT_QUOTES,'UTF-8'); ?>"
                                                            data-date="<?php echo htmlspecialchars(date('Y-m-d', strtotime($row['date_created'])), ENT_QUOTES,'UTF-8'); ?>"
                                                            data-has-receipt="<?php echo !empty($row['receipt']) ? '1' : '0'; ?>"
                                                            onclick="openBudgetEditModal(this); closeBudgetMenus();">
                                                        <span class="item-icon"><i class="fas fa-pen"></i></span>
                                                        Edit
                                                    </button>
                                                    <div class="dropdown-divider"></div>
                                                    <!-- Delete — POST + CSRF inline -->
                                                    <form method="POST" action="budget.php"
                                                          onsubmit="return confirm('Delete this income entry?');">
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
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form><!-- end bulkForm -->

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="flex justify-center mt-6 space-x-2 flex-wrap gap-y-2">
                    <?php
                    $pgBase = array_filter([
                        'search'     => $search ?: null,
                        'filter_cat' => $filterCat ?: null,
                        'quick_date' => $quickDate ?: null,
                        'date_from'  => $dateFrom ?: null,
                        'date_to'    => $dateTo ?: null,
                        'sort'       => $sort !== 'date_created' ? $sort : null,
                        'order'      => $order !== 'DESC' ? strtolower($order) : null,
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
                    of <?php echo number_format($totalRecords); ?> record<?php echo $totalRecords !== 1 ? 's' : ''; ?>
                </p>
                <?php endif; ?>
            </div>

        </div><!-- end bg-gray-100 -->
    </div><!-- end main-content -->

    <!-- ================================================
         REPORT MODAL
         ================================================ -->
    <div id="reportModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 pointer-events-none transition-opacity duration-300">
        <div id="reportContent" class="bg-white p-6 rounded shadow-lg max-w-md w-full transform scale-95 transition-transform duration-300">
            <h3 class="text-xl font-semibold mb-4">Generate Income Report</h3>
            <form method="GET" action="generate_budget_report.php" target="_blank">
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1">Start Date</label>
                    <input type="date" name="start_date" required class="w-full p-2 border rounded">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-1">End Date</label>
                    <input type="date" name="end_date" required class="w-full p-2 border rounded">
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="toggleModal()"
                            class="px-4 py-2 bg-gray-400 text-white rounded">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Generate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================
         CSV EXPORT MODAL
         ================================================ -->
    <div id="csvModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 opacity-0 pointer-events-none transition-opacity duration-300">
        <div id="csvContent" class="bg-white p-6 rounded shadow-lg max-w-md w-full transform scale-95 transition-transform duration-300">
            <h3 class="text-xl font-semibold mb-1">Export Income CSV</h3>
            <p class="text-sm text-gray-500 mb-4">Choose the date range to include in the export.</p>

            <!-- Quick-range tabs -->
            <div class="flex gap-2 flex-wrap mb-4">
                <button type="button" onclick="setCsvQuick('all')"
                        id="csvTab-all"
                        class="csv-qtab quick-tab active-tab">All time</button>
                <button type="button" onclick="setCsvQuick('this_month')"
                        id="csvTab-this_month"
                        class="csv-qtab quick-tab">This month</button>
                <button type="button" onclick="setCsvQuick('this_year')"
                        id="csvTab-this_year"
                        class="csv-qtab quick-tab">This year</button>
                <button type="button" onclick="setCsvQuick('custom')"
                        id="csvTab-custom"
                        class="csv-qtab quick-tab">Custom range</button>
            </div>

            <!-- Custom date range — shown only when "Custom range" is selected -->
            <div id="csvCustomRange" class="hidden mb-4 grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">From</label>
                    <input type="date" id="csvDateFrom" class="w-full p-2 border rounded text-sm">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">To</label>
                    <input type="date" id="csvDateTo" class="w-full p-2 border rounded text-sm">
                </div>
            </div>

            <div id="csvRangeLabel" class="text-xs text-gray-400 mb-5">Exporting all income records.</div>

            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeCsvModal()"
                        class="px-4 py-2 bg-gray-400 text-white rounded hover:bg-gray-500">Cancel</button>
                <button type="button" onclick="submitCsvExport()"
                        class="px-4 py-2 bg-gray-700 text-white rounded hover:bg-gray-800">
                    <i class="fas fa-download mr-1"></i>Download CSV
                </button>
            </div>
        </div>
    </div>

    <!-- ================================================
         RECEIPT MODAL — fade animation, loads via secure proxy
         ================================================ -->
    <div id="receiptModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 opacity-0 pointer-events-none transition-opacity duration-300">
        <div id="receiptContent_wrap" class="bg-white p-4 rounded-lg max-w-4xl w-full max-h-[90vh] overflow-auto transform scale-95 transition-transform duration-300">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-semibold">Receipt</h3>
                <button onclick="closeReceiptModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="receiptContent" class="flex justify-center">
                <p class="text-gray-400 text-sm">Loading...</p>
            </div>
        </div>
    </div>

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script>
        /* ---------- Sidebar toggle — works on all screen sizes ---------- */
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar       = document.querySelector('.sidebar');
            const mainContent   = document.querySelector('.main-content');
            if (!sidebarToggle) return;

            const isMobile = () => window.innerWidth <= 768;

            // Restore desktop collapsed state without animation (no flash/slide on load)
            if (!isMobile() && localStorage.getItem('sidebarCollapsed') === 'true') {
                // Disable transitions for the instant restore
                sidebar.style.transition     = 'none';
                mainContent.style.transition = 'none';
                sidebar.classList.add('collapsed');
                mainContent.classList.add('collapsed');
                // Re-enable transitions on the next frame so future clicks animate normally
                requestAnimationFrame(() => {
                    sidebar.style.transition     = '';
                    mainContent.style.transition = '';
                });
            }

            sidebarToggle.addEventListener('click', function () {
                if (isMobile()) {
                    // Mobile: slide in/out with active class (original behaviour)
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('active');
                } else {
                    // Desktop: collapse/expand and persist choice
                    const isCollapsed = sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed', isCollapsed);
                    localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
                }
            });

            // On resize, clean up stale classes so layout is never broken
            window.addEventListener('resize', function () {
                if (isMobile()) {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('collapsed');
                } else {
                    sidebar.classList.remove('active');
                    mainContent.classList.remove('active');
                }
            });
        });

        function confirmLogout() {
            return confirm('Are you sure you want to log out?');
        }
    </script>

    <script>
        /* ---------- Report modal ---------- */
        const modal   = document.getElementById('reportModal');
        const content = document.getElementById('reportContent');

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

        /* Close report modal when clicking the backdrop */
        window.addEventListener('click', function (e) {
            if (e.target === modal) toggleModal();
            if (e.target === document.getElementById('receiptModal')) closeReceiptModal();
        });    </script>

    <script>
        /* ---------- Receipt modal — fade animation, loads via secure proxy ---------- */
        function viewReceipt(recordId) {
            const rcModal   = document.getElementById('receiptModal');
            const rcWrap    = document.getElementById('receiptContent_wrap');
            const rcContent = document.getElementById('receiptContent');

            // Reset and show loading state
            rcContent.innerHTML = '<p class="text-gray-400 text-sm py-8">Loading...</p>';
            rcModal.classList.remove('pointer-events-none');
            setTimeout(() => {
                rcModal.classList.remove('opacity-0');
                rcModal.classList.add('opacity-100');
                rcWrap.classList.remove('scale-95');
                rcWrap.classList.add('scale-100');
            }, 10);

            // Fetch via proxy — server verifies ownership and returns correct Content-Type
            const proxyUrl = 'view_receipt.php?id=' + encodeURIComponent(recordId) + '&type=budget';

            fetch(proxyUrl, { method: 'HEAD' })
                .then(res => {
                    if (!res.ok) throw new Error('File not found.');
                    const mime = res.headers.get('Content-Type') || '';
                    if (mime.startsWith('image/')) {
                        rcContent.innerHTML =
                            '<img src="' + proxyUrl + '" alt="Receipt" class="max-w-full h-auto">';
                    } else if (mime === 'application/pdf') {
                        rcContent.innerHTML =
                            '<embed src="' + proxyUrl + '" type="application/pdf" width="100%" height="600px">';
                    } else {
                        rcContent.innerHTML =
                            '<p class="text-red-500">This file type cannot be previewed.</p>' +
                            '<a href="' + proxyUrl + '" download class="btn btn-edit mt-2 inline-block">Download Receipt</a>';
                    }
                })
                .catch(() => {
                    rcContent.innerHTML = '<p class="text-red-500">Failed to load receipt. Please try again.</p>';
                });
        }

        function closeReceiptModal() {
            const rcModal = document.getElementById('receiptModal');
            const rcWrap  = document.getElementById('receiptContent_wrap');
            rcWrap.classList.remove('scale-100');
            rcWrap.classList.add('scale-95');
            rcModal.classList.remove('opacity-100');
            rcModal.classList.add('opacity-0', 'pointer-events-none');
            // Reset content after animation
            setTimeout(() => {
                document.getElementById('receiptContent').innerHTML =
                    '<p class="text-gray-400 text-sm">Loading...</p>';
            }, 300);
        }
    </script>

    <script>
        /* ---------- Bulk delete — checkbox selection with action bar ---------- */
        const selectAll   = document.getElementById('selectAll');
        const bulkBar     = document.getElementById('bulkBar');
        const selectedCnt = document.getElementById('selectedCount');

        function updateBulkBar() {
            const checked = document.querySelectorAll('.row-checkbox:checked');
            if (checked.length > 0) {
                bulkBar.classList.remove('hidden');
                selectedCnt.textContent = checked.length + ' record' + (checked.length !== 1 ? 's' : '') + ' selected';
            } else {
                bulkBar.classList.add('hidden');
            }
        }

        function clearSelection() {
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            if (selectAll) selectAll.checked = false;
            updateBulkBar();
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
                updateBulkBar();
            });
        }

        document.querySelectorAll('.row-checkbox').forEach(cb => {
            cb.addEventListener('change', function () {
                const allChecked = document.querySelectorAll('.row-checkbox').length ===
                                   document.querySelectorAll('.row-checkbox:checked').length;
                if (selectAll) selectAll.checked = allChecked;
                updateBulkBar();
            });
        });
    </script>

    <!-- ================================================
         [NEW] CATEGORY DONUT CHART — Chart.js
         ================================================ -->
    <script>
    (function () {
        const canvas = document.getElementById('categoryDonut');
        if (!canvas) return;

        const labels = <?php echo $chartLabels; ?>;
        const values = <?php echo $chartValues; ?>;
        if (!labels.length || values.every(v => v === 0)) return;

        const palette = [
            '#4b6cb7','#22c55e','#f59e0b','#ef4444','#8b5cf6',
            '#06b6d4','#ec4899','#10b981'
        ];

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: palette.slice(0, labels.length),
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            font: { family: 'Inter', size: 11 },
                            padding: 10,
                            boxWidth: 12,
                            generateLabels: function(chart) {
                                const data = chart.data;
                                const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
                                return data.labels.map(function(label, i) {
                                    const val = data.datasets[0].data[i];
                                    const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                    return {
                                        text: label + ' (' + pct + '%)',
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        index: i
                                    };
                                });
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const val = ctx.parsed;
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                return ' RM ' + val.toLocaleString('en-MY', {minimumFractionDigits:2}) + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    })();
    </script>

    <!-- ================================================
         [NEW] REMARKS CHARACTER COUNTER
         ================================================ -->
    <script>
    (function () {
        const input   = document.getElementById('addRemarks');
        const counter = document.getElementById('addRemarksCounter');
        if (!input || !counter) return;

        function updateCounter() {
            const len = input.value.length;
            const max = 255;
            counter.textContent = len + ' / ' + max;
            counter.className = 'char-counter';
            if (len >= max)        counter.classList.add('over');
            else if (len >= 220)   counter.classList.add('near');
        }

        input.addEventListener('input', updateCounter);
        updateCounter(); // initialise on page load (repopulate after error)
    })();
    </script>

    <!-- ================================================
         [NEW] WHAT-IF SIMULATOR
         Uses the same 5-input risk formula as the BPNN description
         in the report (expense_ratio, profit_margin, log_expenses,
         log_income, conservative_ratio) to give an approximate
         preview of how a new income+expense pair shifts risk.
         ================================================ -->
    <script>
    function runSimulator() {
        const incInput = parseFloat(document.getElementById('simIncome').value)  || 0;
        const expInput = parseFloat(document.getElementById('simExpense').value) || 0;
        const result   = document.getElementById('simResult');

        if (incInput <= 0) {
            result.style.display = 'block';
            result.innerHTML = '<i class="fas fa-info-circle mr-1 text-amber-500"></i>Please enter a hypothetical income amount greater than 0.';
            return;
        }

        // Current totals passed from PHP (all-time, current filtered view)
        const currentIncome  = <?php echo json_encode($grandTotal); ?>;
        const currentRecords = <?php echo json_encode($totalRecords); ?>;

        const newIncome   = currentIncome + incInput;
        const newExpenses = expInput;

        // Approximate BPNN input features (mirrors report Section 2.3.1)
        const expenseRatio      = newIncome > 0 ? (newExpenses / newIncome) : 0;
        const profitMargin      = newIncome > 0 ? ((newIncome - newExpenses) / newIncome) : 0;
        const conservativeRatio = newIncome > 0 ? Math.max(0, (0.80 - expenseRatio) / 0.80) : 0;

        // Simple linear risk score estimate (sigmoid-like mapping)
        // mirrors the 5-tier thresholds from report Section 2.3.6
        const riskRaw = expenseRatio;
        let riskPct, tier, tierColor;

        if      (riskRaw > 0.95) { riskPct = 90; tier = 'Critical Risk';   tierColor = '#dc2626'; }
        else if (riskRaw > 0.76) { riskPct = 85; tier = 'Critical Risk';   tierColor = '#dc2626'; }
        else if (riskRaw > 0.61) { riskPct = 68; tier = 'High Risk';       tierColor = '#f59e0b'; }
        else if (riskRaw > 0.46) { riskPct = 53; tier = 'Moderate Risk';   tierColor = '#eab308'; }
        else if (riskRaw > 0.31) { riskPct = 37; tier = 'Low Risk';        tierColor = '#22c55e'; }
        else                     { riskPct = 20; tier = 'Very Low Risk';   tierColor = '#16a34a'; }

        const fmt = v => 'RM ' + v.toLocaleString('en-MY', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        result.style.display = 'block';
        result.innerHTML =
            '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">' +
            '<div><p class="text-xs text-gray-400 mb-0.5">New total income</p><p class="font-semibold text-gray-800">' + fmt(newIncome) + '</p></div>' +
            '<div><p class="text-xs text-gray-400 mb-0.5">Expense ratio</p><p class="font-semibold text-gray-800">' + (expenseRatio * 100).toFixed(1) + '%</p></div>' +
            '<div><p class="text-xs text-gray-400 mb-0.5">Profit margin</p><p class="font-semibold text-gray-800">' + (profitMargin * 100).toFixed(1) + '%</p></div>' +
            '<div><p class="text-xs text-gray-400 mb-0.5">Est. risk tier</p>' +
            '<p class="font-bold text-sm" style="color:' + tierColor + '">' + tier + '</p>' +
            '</div>' +
            '</div>' +
            '<p class="text-xs text-gray-400 mt-3">* This is an approximate preview based on your BPNN input features. Run the full Prediction for an accurate result.</p>';
    }
    </script>

    <!-- ================================================
         CSV EXPORT MODAL — JS
         Routes to generate_budget_report.php with format=csv
         so the same server-side query is used, returning all
         matching records regardless of current pagination.
         ================================================ -->
    <script>
    (function () {
        const csvModal   = document.getElementById('csvModal');
        const csvContent = document.getElementById('csvContent');
        let   csvQuick   = 'all'; // current selection

        window.openCsvModal = function () {
            setCsvQuick('all');
            csvModal.classList.remove('pointer-events-none');
            setTimeout(() => {
                csvModal.classList.remove('opacity-0');
                csvModal.classList.add('opacity-100');
                csvContent.classList.remove('scale-95');
                csvContent.classList.add('scale-100');
            }, 10);
        };

        window.closeCsvModal = function () {
            csvContent.classList.remove('scale-100');
            csvContent.classList.add('scale-95');
            csvModal.classList.remove('opacity-100');
            csvModal.classList.add('opacity-0', 'pointer-events-none');
        };

        window.setCsvQuick = function (key) {
            csvQuick = key;
            // Update tab styles
            document.querySelectorAll('.csv-qtab').forEach(function (btn) {
                btn.classList.toggle('active-tab', btn.id === 'csvTab-' + key);
            });
            // Show/hide custom date inputs
            const customRange = document.getElementById('csvCustomRange');
            const label       = document.getElementById('csvRangeLabel');
            if (key === 'custom') {
                customRange.classList.remove('hidden');
                label.textContent = 'Select a start and end date above.';
            } else {
                customRange.classList.add('hidden');
                const labels = {
                    all:        'Exporting all income records.',
                    this_month: 'Exporting records for the current month.',
                    this_year:  'Exporting records for the current year.'
                };
                label.textContent = labels[key] || '';
            }
        };

        window.submitCsvExport = function () {
            let startDate = '';
            let endDate   = '';
            const today   = new Date();

            if (csvQuick === 'this_month') {
                const y = today.getFullYear();
                const m = String(today.getMonth() + 1).padStart(2, '0');
                const lastDay = new Date(y, today.getMonth() + 1, 0).getDate();
                startDate = y + '-' + m + '-01';
                endDate   = y + '-' + m + '-' + String(lastDay).padStart(2, '0');
            } else if (csvQuick === 'this_year') {
                startDate = today.getFullYear() + '-01-01';
                endDate   = today.getFullYear() + '-12-31';
            } else if (csvQuick === 'custom') {
                startDate = document.getElementById('csvDateFrom').value;
                endDate   = document.getElementById('csvDateTo').value;
                if (!startDate || !endDate) {
                    alert('Please select both a start date and an end date.');
                    return;
                }
                if (startDate > endDate) {
                    alert('Start date must be on or before the end date.');
                    return;
                }
            }
            // all time — no dates, report shows everything

            // Build URL — reuse the existing report endpoint, add format=csv
            let url = 'generate_budget_report.php?format=csv';
            if (startDate) url += '&start_date=' + encodeURIComponent(startDate);
            if (endDate)   url += '&end_date='   + encodeURIComponent(endDate);

            window.open(url, '_blank');
            closeCsvModal();
        };

        // Close CSV modal when clicking the backdrop
        csvModal.addEventListener('click', function (e) {
            if (e.target === csvModal) closeCsvModal();
        });
    })();
    </script>

    <!-- ================================================
         EDIT INCOME MODAL
         ================================================ -->
    <div id="editBudgetModal" class="fixed hidden inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="bg-white p-6 rounded shadow-md max-w-lg w-full relative">
            <button onclick="closeBudgetEditModal()"
                    class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl font-bold">&times;</button>

            <h3 class="text-xl font-bold mb-5">Edit <span class="text-[#4b6cb7]">Income</span></h3>

            <form method="POST" action="budget.php" enctype="multipart/form-data" id="editBudgetForm">
                <input type="hidden" name="csrf_token"
                       value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="budget_id" id="editBudgetId">

                <!-- Category -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category <span class="text-red-500">*</span></label>
                    <select name="edit_category_id" id="editBudgetCategoryId" required
                            class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400 bg-white">
                        <?php foreach ($categoriesResult as $cat): ?>
                            <option value="<?php echo (int)$cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['category_name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Amount -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (RM) <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0.01" name="edit_amount" id="editBudgetAmount" required
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400">
                </div>

                <!-- Remarks — [NEW] with live character counter in edit modal -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Remarks</label>
                    <input type="text" name="edit_remarks" id="editBudgetRemarks" maxlength="255"
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400">
                    <p class="char-counter" id="editRemarksCounter">0 / 255</p>
                </div>

                <!-- Date -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="edit_date" id="editBudgetDate" required
                           class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-400">
                </div>

                <!-- Receipt (optional replacement) -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Replace Receipt
                        <span class="text-gray-400 font-normal">(optional — JPG, PNG, PDF, max 2MB)</span>
                    </label>
                    <p id="editBudgetReceiptNote" class="text-xs text-gray-400 mb-1 hidden">
                        <i class="fas fa-paperclip mr-1"></i>This entry has an existing receipt. Uploading a new one will replace it.
                    </p>
                    <input type="file" name="edit_receipt" accept=".jpg,.jpeg,.png,.pdf" class="w-full text-sm">
                </div>

                <div class="flex justify-end gap-3 border-t pt-4">
                    <button type="button" onclick="closeBudgetEditModal()"
                            class="px-5 py-2 bg-gray-400 hover:bg-gray-500 text-white rounded">Cancel</button>
                    <button type="submit" name="update_budget"
                            onclick="return confirm('Save changes to this income entry?')"
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
        function closeBudgetMenus() {
            document.querySelectorAll('.action-trigger.open').forEach(b => b.classList.remove('open'));
            document.querySelectorAll('.action-dropdown.open').forEach(d => {
                d.classList.remove('open');
                d.style.top = ''; d.style.bottom = '';
                d.style.left = ''; d.style.right = '';
            });
        }
        function toggleBudgetMenu(btn) {
            const dd      = btn.nextElementSibling;
            const wasOpen = dd.classList.contains('open');
            closeBudgetMenus();
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
            window.addEventListener(evt, closeBudgetMenus, true);
        });
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.action-menu')) closeBudgetMenus();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { closeBudgetMenus(); closeBudgetEditModal(); if (window.closeCsvModal) closeCsvModal(); closeReceiptModal(); }
        });
    </script>

    <script>
        /* ---------- Edit income modal ---------- */
        function openBudgetEditModal(btn) {
            document.getElementById('editBudgetId').value      = btn.dataset.id;
            document.getElementById('editBudgetAmount').value  = btn.dataset.amount;
            document.getElementById('editBudgetRemarks').value = btn.dataset.remarks;
            document.getElementById('editBudgetDate').value    = btn.dataset.date;

            const sel = document.getElementById('editBudgetCategoryId');
            for (let i = 0; i < sel.options.length; i++) {
                sel.options[i].selected = (sel.options[i].value === btn.dataset.category);
            }

            const rcNote = document.getElementById('editBudgetReceiptNote');
            rcNote.classList.toggle('hidden', btn.dataset.hasReceipt !== '1');

            // [NEW] Initialise edit remarks counter
            updateEditRemarksCounter();

            document.getElementById('editBudgetModal').classList.remove('hidden');
        }
        function closeBudgetEditModal() {
            document.getElementById('editBudgetModal').classList.add('hidden');
            document.getElementById('editBudgetForm').reset();
            document.getElementById('editBudgetReceiptNote').classList.add('hidden');
            document.getElementById('editBudgetDate').value = '';
            // Reset counter
            const ctr = document.getElementById('editRemarksCounter');
            if (ctr) { ctr.textContent = '0 / 255'; ctr.className = 'char-counter'; }
        }
        document.getElementById('editBudgetModal').addEventListener('click', function (e) {
            if (e.target === this) closeBudgetEditModal();
        });

        /* [NEW] Edit modal remarks counter */
        function updateEditRemarksCounter() {
            const input   = document.getElementById('editBudgetRemarks');
            const counter = document.getElementById('editRemarksCounter');
            if (!input || !counter) return;
            const len = input.value.length;
            counter.textContent = len + ' / 255';
            counter.className = 'char-counter';
            if (len >= 255)      counter.classList.add('over');
            else if (len >= 220) counter.classList.add('near');
        }
        const editRemarksInput = document.getElementById('editBudgetRemarks');
        if (editRemarksInput) {
            editRemarksInput.addEventListener('input', updateEditRemarksCounter);
        }
    </script>

</body>
</html>