<?php
session_start();
include('include/config.php');
include('include/checklogin.php');
check_login();

// ============================================================
// AUTH — fetch user details via prepared statement
// ============================================================
$userId = (int) $_SESSION['id'];

$userStmt = $con->prepare("SELECT fullName FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userName = $userStmt->get_result()->fetch_assoc()['fullName'] ?? 'User';
$userStmt->close();

// ============================================================
// FILTER PARAMS — Overall Financial Statement
// ============================================================
$currentYear   = (int) date('Y');
$selectedYear  = isset($_GET['year'])  && ctype_digit($_GET['year'])  ? (int) $_GET['year']  : $currentYear;
$selectedMonth = isset($_GET['month']) && ctype_digit($_GET['month']) ? (int) $_GET['month'] : 0;

if ($selectedYear  < 2020 || $selectedYear  > $currentYear) $selectedYear  = $currentYear;
if ($selectedMonth < 0    || $selectedMonth > 12)           $selectedMonth = 0;

// ============================================================
// FILTER PARAMS — Category-wise Summary
// ============================================================
$selectedCatYear  = isset($_GET['cat_year'])  && ctype_digit($_GET['cat_year'])  ? (int) $_GET['cat_year']  : $currentYear;
$selectedCatMonth = isset($_GET['cat_month']) && ctype_digit($_GET['cat_month']) ? (int) $_GET['cat_month'] : 0;

if ($selectedCatYear  < 2020 || $selectedCatYear  > $currentYear) $selectedCatYear  = $currentYear;
if ($selectedCatMonth < 0    || $selectedCatMonth > 12)           $selectedCatMonth = 0;

// ============================================================
// OVERALL TOTALS — prepared statements
// ============================================================
function getTotals(mysqli $con, int $userId, int $year, int $month): array {
    if ($month > 0) {
        $dateStart = sprintf('%04d-%02d-01', $year, $month);
        $dateEnd   = date('Y-m-t', strtotime($dateStart));
        $bStmt = $con->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM budgets  WHERE user_id=? AND date_created BETWEEN ? AND ?");
        $bStmt->bind_param("iss", $userId, $dateStart, $dateEnd);
        $bStmt->execute();
        $totalBudget = (float) $bStmt->get_result()->fetch_assoc()['t'];
        $bStmt->close();
        $eStmt = $con->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE user_id=? AND date_created BETWEEN ? AND ?");
        $eStmt->bind_param("iss", $userId, $dateStart, $dateEnd);
        $eStmt->execute();
        $totalExpense = (float) $eStmt->get_result()->fetch_assoc()['t'];
        $eStmt->close();
    } else {
        $bStmt = $con->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM budgets  WHERE user_id=? AND YEAR(date_created)=?");
        $bStmt->bind_param("ii", $userId, $year);
        $bStmt->execute();
        $totalBudget = (float) $bStmt->get_result()->fetch_assoc()['t'];
        $bStmt->close();
        $eStmt = $con->prepare("SELECT COALESCE(SUM(amount),0) AS t FROM expenses WHERE user_id=? AND YEAR(date_created)=?");
        $eStmt->bind_param("ii", $userId, $year);
        $eStmt->execute();
        $totalExpense = (float) $eStmt->get_result()->fetch_assoc()['t'];
        $eStmt->close();
    }
    return ['budget' => $totalBudget, 'expense' => $totalExpense];
}

$totals       = getTotals($con, $userId, $selectedYear, $selectedMonth);
$totalBudget  = $totals['budget'];
$totalExpense = $totals['expense'];
$totalProfit  = $totalBudget - $totalExpense;

// ============================================================
// CATEGORY-WISE TOTALS — single JOIN query, no N+1
// ============================================================
function getCategoryTotals(mysqli $con, int $userId, int $year, int $month): array {
    if ($month > 0) {
        $dateStart = sprintf('%04d-%02d-01', $year, $month);
        $dateEnd   = date('Y-m-t', strtotime($dateStart));
        $stmt = $con->prepare("
            SELECT c.id, c.category_name, c.category_type,
                   COALESCE(SUM(b.amount),0) AS budget_total,
                   COALESCE(SUM(e.amount),0) AS expense_total
            FROM categories c
            LEFT JOIN budgets  b ON b.category_id=c.id AND b.user_id=? AND b.date_created BETWEEN ? AND ?
            LEFT JOIN expenses e ON e.category_id=c.id AND e.user_id=? AND e.date_created BETWEEN ? AND ?
            WHERE c.user_id=?
            GROUP BY c.id, c.category_name, c.category_type
            ORDER BY c.category_type, c.category_name
        ");
        $stmt->bind_param("isssssi", $userId, $dateStart, $dateEnd, $userId, $dateStart, $dateEnd, $userId);
    } else {
        $stmt = $con->prepare("
            SELECT c.id, c.category_name, c.category_type,
                   COALESCE(SUM(b.amount),0) AS budget_total,
                   COALESCE(SUM(e.amount),0) AS expense_total
            FROM categories c
            LEFT JOIN budgets  b ON b.category_id=c.id AND b.user_id=? AND YEAR(b.date_created)=?
            LEFT JOIN expenses e ON e.category_id=c.id AND e.user_id=? AND YEAR(e.date_created)=?
            WHERE c.user_id=?
            GROUP BY c.id, c.category_name, c.category_type
            ORDER BY c.category_type, c.category_name
        ");
        $stmt->bind_param("iiiii", $userId, $year, $userId, $year, $userId);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$categoryRows = getCategoryTotals($con, $userId, $selectedCatYear, $selectedCatMonth);

function periodLabel(int $year, int $month): string {
    return $month > 0
        ? date('F Y', mktime(0, 0, 0, $month, 1, $year))
        : "All months in $year";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
    .btn {
        display: inline-block; padding: 8px 16px; border-radius: 4px;
        text-align: center; text-decoration: none; font-size: 14px;
        transition: transform 0.2s ease-in-out, background-color 0.3s ease;
        color: white;
    }
    .btn-edit   { background-color: #4CAF50; }
    .btn-edit:hover   { background-color: #45a049; transform: scale(1.05); }
    .btn-delete { background-color: #f44336; }
    .btn-delete:hover { background-color: #e53935; transform: scale(1.05); }
    .btn:active { transform: scale(1); }
    /* Chart wrapper needs an explicit height when maintainAspectRatio is false */
    #chartContainer { position: relative; height: 280px; max-width: 360px; margin: 0 auto; }
    </style>
</head>
<body class="bg-gray-100">

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
            <a href="dashboard.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-tachometer-alt w-5 text-center"></i><span>Dashboard</span>
            </a>
            <a href="expense.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-comments-dollar w-5 text-center"></i><span>Expense Management</span>
            </a>
            <a href="budget.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-dollar-sign w-5 text-center"></i><span>Income Management</span>
            </a>
            <a href="category.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-list w-5 text-center"></i><span>Category Management</span>
            </a>
            <a href="prediction.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-gear w-5 text-center"></i><span>Prediction Management</span>
            </a>
            <a href="feedback.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-envelope w-5 text-center"></i><span>Feedback Management</span>
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
                        <h1 class="text-2xl font-semibold">Dashboard</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">User</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Dashboard</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <!-- ============================================================
             OVERALL FINANCIAL STATEMENT
             ============================================================ -->
        <section class="container mx-auto px-4 sm:px-6 lg:px-8 mt-8">
            <div class="bg-white p-6 rounded shadow">
                <h2 class="text-2xl font-semibold text-gray-700 mb-6">
                    Overall Financial <span class="text-[#4b6cb7]">Statement</span>
                </h2>

                <!-- Filter form -->
                <div class="bg-gray-50 p-4 rounded shadow mb-6">
                    <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                        <!-- Preserve category filter state across submissions -->
                        <input type="hidden" name="cat_year"  value="<?php echo $selectedCatYear; ?>">
                        <input type="hidden" name="cat_month" value="<?php echo $selectedCatMonth; ?>">

                        <div class="flex-1">
                            <label class="block text-gray-700 font-semibold mb-2">Select Year:</label>
                            <select name="year" class="border border-gray-300 rounded px-4 py-2 w-full">
                                <?php for ($y = $currentYear; $y >= 2020; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $selectedYear === $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="flex-1">
                            <label class="block text-gray-700 font-semibold mb-2">Select Month:</label>
                            <select name="month" class="border border-gray-300 rounded px-4 py-2 w-full">
                                <option value="">All Months</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div>
                            <button type="submit" class="btn btn-edit mt-6">View</button>
                        </div>
                    </form>
                </div>

                <!-- Period label -->
                <p class="text-sm text-gray-500 mb-6">
                    Showing data for:
                    <span class="font-semibold"><?php echo periodLabel($selectedYear, $selectedMonth); ?></span>
                </p>

                <!-- Summary cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow-md p-6 flex items-center space-x-4">
                        <div class="p-4 bg-blue-100 rounded-full text-blue-600">
                            <i class="fas fa-wallet text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Total Income</h3>
                            <p class="text-xl font-bold text-[#182848]">RM <?php echo number_format($totalBudget, 2); ?></p>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6 flex items-center space-x-4">
                        <div class="p-4 bg-red-100 rounded-full text-red-600">
                            <i class="fas fa-coins text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Total Expense</h3>
                            <p class="text-xl font-bold text-[#182848]">RM <?php echo number_format($totalExpense, 2); ?></p>
                        </div>
                    </div>

                    <div class="bg-white rounded-lg shadow-md p-6 flex items-center space-x-4
                        <?php echo $totalProfit < 0 ? 'border-l-4 border-red-400' : ''; ?>">
                        <div class="p-4 <?php echo $totalProfit < 0 ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'; ?> rounded-full">
                            <i class="fas fa-chart-line text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700">Net Profit</h3>
                            <p class="text-xl font-bold <?php echo $totalProfit < 0 ? 'text-red-600' : 'text-[#182848]'; ?>">
                                RM <?php echo number_format($totalProfit, 2); ?>
                            </p>
                            <?php if ($totalProfit < 0): ?>
                                <p class="text-xs text-red-500 mt-1">Expenses exceed income</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Income vs Expense chart — only rendered when there is data -->
                <?php if ($totalBudget > 0 || $totalExpense > 0): ?>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-lg font-semibold text-gray-700 text-center mb-4">Income vs Expense Ratio</h3>
                    <div id="chartContainer">
                        <canvas id="budgetExpenseChart"></canvas>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-gray-50 rounded-lg p-6 text-center text-gray-400">
                    <i class="fas fa-chart-pie text-4xl mb-2"></i>
                    <p class="text-sm mt-2">No financial data for this period.</p>
                </div>
                <?php endif; ?>

            </div>
        </section>

        <!-- ============================================================
             CATEGORY-WISE FINANCIAL SUMMARY
             ============================================================ -->
        <section class="container mx-auto px-4 sm:px-6 lg:px-8 mt-6 pb-10">
            <div class="bg-white p-6 rounded shadow">
                <h2 class="text-2xl font-semibold text-gray-700 mb-6">
                    Category-wise <span class="text-[#4b6cb7]">Financial Summary</span>
                </h2>

                <!-- Filter form -->
                <div class="bg-gray-50 p-4 rounded shadow mb-6">
                    <form method="GET" class="flex flex-col md:flex-row gap-4 items-end">
                        <!-- Preserve overall filter state across submissions -->
                        <input type="hidden" name="year"  value="<?php echo $selectedYear; ?>">
                        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">

                        <div class="flex-1">
                            <label class="block text-gray-700 font-semibold mb-2">Select Year:</label>
                            <select name="cat_year" class="border border-gray-300 rounded px-4 py-2 w-full">
                                <?php for ($y = $currentYear; $y >= 2020; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $selectedCatYear === $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="flex-1">
                            <label class="block text-gray-700 font-semibold mb-2">Select Month:</label>
                            <select name="cat_month" class="border border-gray-300 rounded px-4 py-2 w-full">
                                <option value="">All Months</option>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $selectedCatMonth === $m ? 'selected' : ''; ?>>
                                        <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div>
                            <button type="submit" class="btn btn-edit mt-6">View</button>
                        </div>
                    </form>
                </div>

                <!-- Period label -->
                <p class="text-sm text-gray-500 mb-6">
                    Showing data for:
                    <span class="font-semibold"><?php echo periodLabel($selectedCatYear, $selectedCatMonth); ?></span>
                </p>

                <?php
                $incomeRows  = array_filter($categoryRows, fn($r) => $r['category_type'] === 'Sales'   && $r['budget_total']  > 0);
                $expenseRows = array_filter($categoryRows, fn($r) => $r['category_type'] === 'Expense' && $r['expense_total'] > 0);
                ?>

                <?php if (empty($incomeRows) && empty($expenseRows)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-folder-open text-4xl mb-2"></i>
                        <p class="text-sm mt-2">No category data for this period.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($incomeRows as $row): ?>
                            <div class="bg-green-50 rounded-lg shadow-md p-6 border-l-4 border-green-500">
                                <div class="flex items-center space-x-4">
                                    <div class="p-3 bg-green-100 rounded-full text-green-600">
                                        <i class="fas fa-wallet text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($row['category_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="text-lg font-bold text-gray-800">RM <?php echo number_format($row['budget_total'], 2); ?></p>
                                        <p class="text-sm text-green-600">Income</p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($expenseRows as $row): ?>
                            <div class="bg-red-50 rounded-lg shadow-md p-6 border-l-4 border-red-500">
                                <div class="flex items-center space-x-4">
                                    <div class="p-3 bg-red-100 rounded-full text-red-600">
                                        <i class="fas fa-coins text-xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-700"><?php echo htmlspecialchars($row['category_name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        <p class="text-lg font-bold text-gray-800">RM <?php echo number_format($row['expense_total'], 2); ?></p>
                                        <p class="text-sm text-red-600">Expense</p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>
        </section>

    </div><!-- end .main-content -->

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
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

    <?php if ($totalBudget > 0 || $totalExpense > 0): ?>
    <script>
        const ctx     = document.getElementById('budgetExpenseChart').getContext('2d');
        const budget  = <?php echo json_encode((float) $totalBudget); ?>;
        const expense = <?php echo json_encode((float) $totalExpense); ?>;

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Total Income', 'Total Expense'],
                datasets: [{
                    label: 'Amount (RM)',
                    data: [budget, expense],
                    backgroundColor: ['rgba(46,204,113,0.85)', 'rgba(231,76,60,0.75)'],
                    borderColor:     ['rgba(39,174,96,1)',     'rgba(192,57,43,1)'],
                    borderWidth: 2,
                    hoverOffset: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function(a,b){ return a+b; }, 0);
                                var pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ctx.label + ': RM ' + ctx.parsed.toLocaleString('en-MY', {minimumFractionDigits:2}) + ' (' + pct + '%)';
                            }
                        }
                    },
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>