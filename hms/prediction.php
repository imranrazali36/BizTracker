<?php
session_start();
include('include/config.php');
include('include/checklogin.php');
check_login();

// ============================================================
// CSRF TOKEN GENERATION
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// USER DATA — prepared statement
// ============================================================
$userId = (int) $_SESSION['id'];

$userStmt = $con->prepare("SELECT fullName FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$userData = $userStmt->get_result()->fetch_assoc();
$userStmt->close();
$userName = $userData['fullName'] ?? 'User';

// ============================================================
// INITIALISE VARIABLES
// ============================================================
$startDate      = '';
$endDate        = '';
$dateCondition  = '';
$dateError      = '';
$targetProfit   = 0;
$noDataMessage  = '';
$showPopup      = false;

$predictionLevel       = '';
$predictionColor       = '';
$budgetTotal           = 0;
$expenseTotal          = 0;
$predictionExplanation = '';
$predictionSuggestion  = '';
$percentage            = 0;
$feasibilityScore      = 100;
$feasibilityStatus     = 'N/A';
$profitGap             = 0;
$targetRiskAdjustment  = 0;
$totalBudget           = 0;
$totalExpense          = 0;
$totalProfit           = 0;

// ============================================================
// HELPER — strict date validation
// ============================================================
function validateDate(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

// ============================================================
// NEURAL NETWORK FUNCTIONS (unchanged logic)
// ============================================================
function sigmoid($x)
{
    return $x >= 0
        ? (1 / (1 + exp(-$x)))
        : (exp($x) / (1 + exp($x)));
}

function relu($x)
{
    return max(0, $x);
}

function leaky_relu($x, $alpha = 0.01)
{
    return $x >= 0 ? $x : $alpha * $x;
}

function initializeWeights($inputSize, $hiddenSize, $outputSize)
{
    $weights = [];
    $scale   = sqrt(2 / $inputSize);
    for ($i = 0; $i < $hiddenSize; $i++) {
        for ($j = 0; $j < $inputSize; $j++) {
            $weights['input_hidden'][$i][$j] = (mt_rand() / mt_getrandmax() * 2 - 1) * $scale;
        }
    }
    $scale = sqrt(2 / $hiddenSize);
    for ($i = 0; $i < $outputSize; $i++) {
        for ($j = 0; $j < $hiddenSize; $j++) {
            $weights['hidden_output'][$i][$j] = (mt_rand() / mt_getrandmax() * 2 - 1) * $scale;
        }
    }
    return $weights;
}

function forwardPropagation($input, $weights)
{
    $normalizedInput = [];
    $sum = array_sum($input);
    foreach ($input as $val) {
        $normalizedInput[] = $val / max($sum, 0.0001);
    }

    $hidden_input  = [];
    $hidden_output = [];
    foreach ($weights['input_hidden'] as $i => $weightsNeuron) {
        $s = 0;
        foreach ($normalizedInput as $j => $val) {
            $s += $val * $weightsNeuron[$j];
        }
        $hidden_input[$i]  = $s;
        $hidden_output[$i] = leaky_relu($s);
    }

    $final_input = 0;
    foreach ($weights['hidden_output'][0] as $i => $val) {
        $final_input += $val * $hidden_output[$i];
    }

    $output = sigmoid($final_input);
    return [$output, $hidden_output];
}

function trainNetwork($data, $weights, $epochs = 3000, $learningRate = 0.01)
{
    $beta1   = 0.9;
    $beta2   = 0.999;
    $epsilon = 1e-8;

    $m_input_hidden  = array_fill(0, count($weights['input_hidden']),  array_fill(0, count($weights['input_hidden'][0]),  0));
    $v_input_hidden  = $m_input_hidden;
    $m_hidden_output = array_fill(0, count($weights['hidden_output']), array_fill(0, count($weights['hidden_output'][0]), 0));
    $v_hidden_output = $m_hidden_output;

    for ($epoch = 1; $epoch <= $epochs; $epoch++) {
        foreach ($data as $row) {
            list($input, $target) = $row;
            list($output, $hidden_output) = forwardPropagation($input, $weights);
            $error        = $target - $output;
            $delta_output = $error * $output * (1 - $output);

            $delta_hidden = [];
            foreach ($weights['hidden_output'][0] as $i => $weight) {
                $delta_hidden[$i] = $weight * $delta_output * ($hidden_output[$i] > 0 ? 1 : 0.01);
            }

            foreach ($weights['hidden_output'][0] as $i => &$weight) {
                $grad                    = $delta_output * $hidden_output[$i];
                $m_hidden_output[0][$i]  = $beta1 * $m_hidden_output[0][$i]  + (1 - $beta1) * $grad;
                $v_hidden_output[0][$i]  = $beta2 * $v_hidden_output[0][$i]  + (1 - $beta2) * ($grad ** 2);
                $m_hat = $m_hidden_output[0][$i]  / (1 - ($beta1 ** $epoch));
                $v_hat = $v_hidden_output[0][$i]  / (1 - ($beta2 ** $epoch));
                $weight += $learningRate * $m_hat / (sqrt($v_hat) + $epsilon);
            }

            foreach ($weights['input_hidden'] as $i => &$neuronWeights) {
                foreach ($neuronWeights as $j => &$weight) {
                    $grad                   = $delta_hidden[$i] * $input[$j];
                    $m_input_hidden[$i][$j] = $beta1 * $m_input_hidden[$i][$j] + (1 - $beta1) * $grad;
                    $v_input_hidden[$i][$j] = $beta2 * $v_input_hidden[$i][$j] + (1 - $beta2) * ($grad ** 2);
                    $m_hat = $m_input_hidden[$i][$j] / (1 - ($beta1 ** $epoch));
                    $v_hat = $v_input_hidden[$i][$j] / (1 - ($beta2 ** $epoch));
                    $weight += $learningRate * $m_hat / (sqrt($v_hat) + $epsilon);
                }
            }
        }
    }
    return $weights;
}

function predict($input, $weights)
{
    list($output, $hidden_output) = forwardPropagation($input, $weights);
    return $output;
}

function extractFinancialFeatures($budgetTotal, $expenseTotal)
{
    $features   = [];
    $features[] = $expenseTotal / max($budgetTotal, 1);
    $features[] = ($budgetTotal - $expenseTotal) / max($budgetTotal, 1);
    $features[] = log(max($expenseTotal, 1));
    $features[] = log(max($budgetTotal, 1));
    $features[] = min(1, $expenseTotal / max($budgetTotal * 0.8, 1));
    return $features;
}

// ============================================================
// POST HANDLING — CSRF + date validation first
// ============================================================
if (isset($_POST['predict'])) {

    // 1. CSRF check
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        die('Invalid request. Please refresh the page and try again.');
    }

    // 2. Sanitise & validate inputs
    $startDate    = trim($_POST['start_date'] ?? '');
    $endDate      = trim($_POST['end_date']   ?? '');
    $targetProfit = max(0, (float) ($_POST['target_profit'] ?? 0));

    if (empty($startDate) || empty($endDate)) {
        $dateError = "Please select both start and end dates.";
    } elseif (!validateDate($startDate) || !validateDate($endDate)) {
        $dateError = "Invalid date format. Please use the date picker.";
    } elseif ($startDate > $endDate) {
        $dateError = "Invalid date range — start date cannot be after end date.";
    } else {
        // 3. Check data existence — prepared statements
        $chkStmt = $con->prepare(
            "SELECT
                (SELECT COUNT(*) FROM budgets  WHERE user_id = ? AND date_created BETWEEN ? AND ?) AS bCount,
                (SELECT COUNT(*) FROM expenses WHERE user_id = ? AND date_created BETWEEN ? AND ?) AS eCount"
        );
        $chkStmt->bind_param("isisss", $userId, $startDate, $endDate, $userId, $startDate, $endDate);
        $chkStmt->execute();
        $chkRow = $chkStmt->get_result()->fetch_assoc();
        $chkStmt->close();

        if ($chkRow['bCount'] == 0 && $chkRow['eCount'] == 0) {
            $noDataMessage = "No financial data available for this period.";
        } else {
            // 4. Fetch totals — prepared statements
            $bStmt = $con->prepare(
                "SELECT COALESCE(SUM(amount), 0) AS total
                 FROM budgets WHERE user_id = ? AND date_created BETWEEN ? AND ?"
            );
            $bStmt->bind_param("iss", $userId, $startDate, $endDate);
            $bStmt->execute();
            $budgetTotal = (float) $bStmt->get_result()->fetch_assoc()['total'];
            $bStmt->close();

            $eStmt = $con->prepare(
                "SELECT COALESCE(SUM(amount), 0) AS total
                 FROM expenses WHERE user_id = ? AND date_created BETWEEN ? AND ?"
            );
            $eStmt->bind_param("iss", $userId, $startDate, $endDate);
            $eStmt->execute();
            $expenseTotal = (float) $eStmt->get_result()->fetch_assoc()['total'];
            $eStmt->close();

            if ($budgetTotal > 0) {
                // 5. Check session cache for trained weights
                $cacheKey = "nn_{$userId}_{$startDate}_{$endDate}";
                if (isset($_SESSION['nn_weights'][$cacheKey])) {
                    $weights = $_SESSION['nn_weights'][$cacheKey];
                } else {
                    $inputFeatures = extractFinancialFeatures($budgetTotal, $expenseTotal);
                    $weights       = initializeWeights(count($inputFeatures), 6, 1);
                    $trainingData  = [
                        [$inputFeatures, min(1, $expenseTotal / $budgetTotal)],
                        [
                            array_map(fn($x) => $x * 1.2, $inputFeatures),
                            min(1, ($expenseTotal * 1.2) / $budgetTotal)
                        ],
                        [
                            array_map(fn($x) => $x * 0.8, $inputFeatures),
                            min(1, ($expenseTotal * 0.8) / $budgetTotal)
                        ],
                    ];
                    $weights = trainNetwork($trainingData, $weights, 3000, 0.01);
                    $_SESSION['nn_weights'][$cacheKey] = $weights;
                }

                $inputFeatures   = extractFinancialFeatures($budgetTotal, $expenseTotal);
                $predictionValue = predict($inputFeatures, $weights);
                $percentage      = $predictionValue * 100;

                // 6. Target profit analysis
                $actualProfit         = $budgetTotal - $expenseTotal;
                $feasibilityScore     = 100;
                $feasibilityStatus    = 'N/A';
                $targetRiskAdjustment = 0;

                if ($targetProfit > 0) {
                    $profitGap    = $targetProfit - $actualProfit;
                    $stretchRatio = ($targetProfit - $actualProfit) / $budgetTotal;

                    if ($stretchRatio <= 0) {
                        $feasibilityScore  = 100;
                        $feasibilityStatus = 'High (Goal already met)';
                    } else {
                        $feasibilityScore  = max(5, 100 - ($stretchRatio * 150));
                        if ($feasibilityScore > 75)      $feasibilityStatus = 'High';
                        elseif ($feasibilityScore > 40)  $feasibilityStatus = 'Moderate';
                        else                             $feasibilityStatus = 'Low (Highly Ambitious)';
                    }

                    if ($stretchRatio > 0.3) {
                        $targetRiskAdjustment = $stretchRatio * 15;
                        $percentage = min(99.9, $percentage + $targetRiskAdjustment);
                    }
                }

                // 7. Five-level risk assessment
                if ($percentage > 75) {
                    $predictionLevel = 'Critical Risk';
                    $predictionColor = 'text-red-600';
                } elseif ($percentage > 60) {
                    $predictionLevel = 'High Risk';
                    $predictionColor = 'text-orange-600';
                } elseif ($percentage > 45) {
                    $predictionLevel = 'Moderate Risk';
                    $predictionColor = 'text-yellow-600';
                } elseif ($percentage > 30) {
                    $predictionLevel = 'Low Risk';
                    $predictionColor = 'text-blue-600';
                } else {
                    $predictionLevel = 'Very Low Risk';
                    $predictionColor = 'text-green-600';
                }

                // 8. Build explanation
                $escapedTarget  = htmlspecialchars(number_format($targetProfit, 2), ENT_QUOTES, 'UTF-8');
                $escapedGap     = htmlspecialchars(number_format(abs($profitGap), 2), ENT_QUOTES, 'UTF-8');
                $baseRiskStr    = number_format($predictionValue * 100, 2);

                $predictionExplanation = "Based on your data, your base risk is <strong>" . $baseRiskStr . "%</strong>. ";

                if ($targetProfit > 0) {
                    if ($profitGap > 0) {
                        $predictionExplanation .= "To reach your target profit of <strong>RM " . $escapedTarget . "</strong>, "
                            . "you need to generate an additional <strong>RM " . $escapedGap . "</strong> in income or reduce expenses by the same amount.";

                        if ($targetRiskAdjustment > 0) {
                            $adjStr = number_format($targetRiskAdjustment, 1);
                            $predictionExplanation .= " <br><br><i class='fas fa-exclamation-triangle'></i> <strong>Risk Note:</strong> "
                                . "Your risk level was adjusted upwards by " . $adjStr . "% because your profit target is significantly "
                                . "higher than your current performance, which usually requires high-risk capital or over-extension.";
                        }
                    } else {
                        $predictionExplanation .= "Your business is currently performing above your target profit goal.";
                    }
                }

                // 9. Suggestion text
                if ($targetProfit > 0 && $profitGap > 0) {
                    $feasStr    = htmlspecialchars($feasibilityStatus, ENT_QUOTES, 'UTF-8');
                    $feasScore  = number_format($feasibilityScore, 1);
                    $incGrowth  = number_format(($profitGap / $budgetTotal) * 100, 1);
                    $expReduce  = number_format(($profitGap / max($expenseTotal, 1)) * 100, 1);
                    $recStr     = $feasibilityScore < 40
                        ? "Consider a more incremental target to avoid burnout/debt."
                        : "Target is realistic with consistent effort.";

                    $predictionSuggestion  = "1. Feasibility of target: <strong>{$feasStr} ({$feasScore}%)</strong>\n";
                    $predictionSuggestion .= "2. Required Income Growth: {$incGrowth}%\n";
                    $predictionSuggestion .= "3. Required Expense Reduction: {$expReduce}%\n";
                    $predictionSuggestion .= "4. Recommendation: {$recStr}";
                }

                $showPopup    = true;
                $totalBudget  = $budgetTotal;
                $totalExpense = $expenseTotal;
                $totalProfit  = $totalBudget - $totalExpense;
            }
        }
    }
}

// ============================================================
// SUMMARY TOTALS FOR DISPLAY
// ============================================================
if ($totalBudget == 0 && $totalExpense == 0) {
    if (!empty($startDate) && !empty($endDate) && validateDate($startDate) && validateDate($endDate)) {
        $sumBStmt = $con->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM budgets WHERE user_id = ? AND date_created BETWEEN ? AND ?"
        );
        $sumBStmt->bind_param("iss", $userId, $startDate, $endDate);
        $sumBStmt->execute();
        $totalBudget = (float) $sumBStmt->get_result()->fetch_assoc()['total'];
        $sumBStmt->close();

        $sumEStmt = $con->prepare(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE user_id = ? AND date_created BETWEEN ? AND ?"
        );
        $sumEStmt->bind_param("iss", $userId, $startDate, $endDate);
        $sumEStmt->execute();
        $totalExpense = (float) $sumEStmt->get_result()->fetch_assoc()['total'];
        $sumEStmt->close();
    } else {
        $sumBStmt = $con->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM budgets WHERE user_id = ?");
        $sumBStmt->bind_param("i", $userId);
        $sumBStmt->execute();
        $totalBudget = (float) $sumBStmt->get_result()->fetch_assoc()['total'];
        $sumBStmt->close();

        $sumEStmt = $con->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE user_id = ?");
        $sumEStmt->bind_param("i", $userId);
        $sumEStmt->execute();
        $totalExpense = (float) $sumEStmt->get_result()->fetch_assoc()['total'];
        $sumEStmt->close();
    }
    $totalProfit = $totalBudget - $totalExpense;
}

// ============================================================
// CATEGORY SUMMARY — single JOIN query
// ============================================================
$categoryRows = [];
if (!empty($startDate) && !empty($endDate) && validateDate($startDate) && validateDate($endDate)) {
    $catStmt = $con->prepare(
        "SELECT
            c.id,
            c.category_name,
            c.category_type,
            COALESCE(SUM(b.amount), 0)  AS total_budget,
            COALESCE(SUM(e.amount), 0)  AS total_expense
         FROM categories c
         LEFT JOIN budgets  b ON b.category_id = c.id AND b.user_id  = ? AND b.date_created  BETWEEN ? AND ?
         LEFT JOIN expenses e ON e.category_id = c.id AND e.user_id  = ? AND e.date_created  BETWEEN ? AND ?
         WHERE c.user_id = ?
         GROUP BY c.id, c.category_name, c.category_type"
    );
    $catStmt->bind_param("isssssi", $userId, $startDate, $endDate, $userId, $startDate, $endDate, $userId);
} else {
    $catStmt = $con->prepare(
        "SELECT
            c.id,
            c.category_name,
            c.category_type,
            COALESCE(SUM(b.amount), 0)  AS total_budget,
            COALESCE(SUM(e.amount), 0)  AS total_expense
         FROM categories c
         LEFT JOIN budgets  b ON b.category_id = c.id AND b.user_id  = ?
         LEFT JOIN expenses e ON e.category_id = c.id AND e.user_id  = ?
         WHERE c.user_id = ?
         GROUP BY c.id, c.category_name, c.category_type"
    );
    $catStmt->bind_param("iii", $userId, $userId, $userId);
}
$catStmt->execute();
$catResult = $catStmt->get_result();
while ($row = $catResult->fetch_assoc()) {
    $categoryRows[] = $row;
}
$catStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prediction Feature | BizTracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar {
            width: 280px;
            transition: all 0.3s ease;
            background: linear-gradient(180deg, #4b6cb7 0%, #182848 100%);
        }
        .sidebar.collapsed { width: 0; overflow: hidden; }
        .main-content {
            margin-left: 280px;
            transition: all 0.3s ease;
            background: rgb(235, 235, 235);
        }
        .main-content.collapsed { margin-left: 0; }
        .nav-link { transition: all 0.3s ease; }
        .nav-link:hover { background-color: rgba(255,255,255,0.1); }
        .nav-link.active { background-color: rgba(255,255,255,0.1); border-left: 4px solid #fff; }
        @media (max-width: 768px) {
            .sidebar { margin-left: -280px; }
            .sidebar.active { margin-left: 0; }
            .sidebar.collapsed { width: 280px; margin-left: -280px; }
            .sidebar.collapsed.active { margin-left: 0; }
            .main-content { margin-left: 0; }
            .main-content.active { margin-left: 280px; }
            .main-content.collapsed { margin-left: 0; }
        }
        .btn {
            display: inline-block; padding: 8px 16px; border-radius: 4px;
            text-align: center; text-decoration: none; font-size: 14px;
            transition: transform 0.2s ease-in-out, background-color 0.3s ease; color: white;
        }
        .btn-edit { background-color: #4CAF50; }
        .btn-edit:hover { background-color: #45a049; transform: scale(1.1); }
        .btn:active { transform: scale(1); }
        .metric-card { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .metric-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
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
            <a href="prediction.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-solid fa-gear w-5 text-center"></i><span>Prediction Management</span>
            </a>
            <a href="feedback.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
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

        <!-- Header — full-width, always-visible toggle matching budget/expense -->
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
                        <h1 class="text-2xl font-semibold">Prediction</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">User</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Failure Rate Prediction</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Page Body -->
        <div style="background:rgb(235,235,235); padding-bottom:40px; padding-left:1.5rem; padding-right:1.5rem;">

            <!-- Prediction Form Card -->
            <div class="mt-10 bg-white p-6 rounded shadow">
                <h2 class="text-2xl font-semibold text-gray-700 mb-2">Business <span class="text-[#4b6cb7]">Risk Prediction</span></h2>
                <p class="text-sm text-gray-500 mb-6">Select a date range to analyse your financial data and predict your business failure risk using AI.</p>

                <?php if (!empty($dateError)): ?>
                    <div class="mb-4 p-3 bg-red-100 text-red-800 rounded">
                        <?php echo htmlspecialchars($dateError, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($noDataMessage)): ?>
                    <div class="mb-4 p-3 bg-blue-100 text-blue-800 rounded">
                        <?php echo htmlspecialchars($noDataMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form method="post" class="mb-4">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-2">
                        <div>
                            <label for="start_date" class="block text-gray-700 font-medium mb-1">Start Date <span class="text-red-500">*</span></label>
                            <input type="date" id="start_date" name="start_date"
                                value="<?php echo htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required>
                            <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                                <i class="fas fa-info-circle"></i> The earliest date to include in the analysis period.
                            </p>
                        </div>
                        <div>
                            <label for="end_date" class="block text-gray-700 font-medium mb-1">End Date <span class="text-red-500">*</span></label>
                            <input type="date" id="end_date" name="end_date"
                                value="<?php echo htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8'); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500" required>
                            <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                                <i class="fas fa-info-circle"></i> Must be on or after the start date.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label for="target_profit" class="block text-gray-700 font-medium mb-1">Target Profit (RM) <span class="text-gray-400 font-normal text-sm">— Optional</span></label>
                        <input type="number" step="0.01" min="0" id="target_profit" name="target_profit"
                            value="<?php echo htmlspecialchars((string) $targetProfit, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="e.g. 5000.00"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500">
                        <p class="text-xs text-gray-400 mt-2 flex items-center gap-1">
                            <i class="fas fa-info-circle"></i> Enter your desired profit goal to see a feasibility score and gap analysis. Leave blank to skip.
                        </p>
                    </div>

                    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-2">
                        <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5 flex-shrink-0 text-sm"></i>
                        <p class="text-xs text-amber-700">
                            <strong>Both dates are required</strong> to run the prediction. The system needs income and expense records within the selected period — make sure you have added entries on the <a href="budget.php" class="underline font-semibold hover:text-amber-900">Income</a> and <a href="expense.php" class="underline font-semibold hover:text-amber-900">Expense</a> pages first.
                        </p>
                    </div>

                    <div class="flex justify-between items-end w-full mt-6">
                        <div class="flex items-center gap-3">
                            <button type="button" onclick="toggleModal()"
                                class="btn btn-edit px-6 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                                View Guidelines
                            </button>
                            <span class="text-sm text-gray-600">Learn how our algorithm works.</span>
                        </div>
                        <button type="submit" name="predict" id="predictBtn"
                            class="btn btn-edit px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 opacity-50 cursor-not-allowed"
                            disabled>
                            Predict Failure Rate
                        </button>
                    </div>
                </form>
            </div>

            <!-- Guidelines Modal -->
            <div id="modal" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center z-50 opacity-0 pointer-events-none transition-opacity duration-300">
                <div id="content" class="bg-white w-full max-w-4xl p-6 rounded shadow-lg overflow-y-auto max-h-[90vh] relative transform scale-95 transition-transform duration-300">
                    <button onclick="toggleModal()" class="absolute top-2 right-2 text-gray-600 hover:text-black text-xl font-bold">&times;</button>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Prediction System Guidelines</h3>
                    <p class="mb-4 text-gray-700">Our prediction system uses the <strong>Backpropagation Neural Network (BPNN)</strong> to analyze income, expenses, and financial ratios to predict your business failure risk.</p>
                    <div class="space-y-4">
                        <div><h4 class="text-lg font-semibold text-blue-700">Step 1: User Inputs the Date Range</h4><p class="text-gray-700">The prediction process begins with the user accessing the prediction.php page. The user selects a start date and an end date to filter financial data within the specified time range.</p></div>
                        <div><h4 class="text-lg font-semibold text-blue-700">Step 2: Retrieving Financial Data</h4><p class="text-gray-700">The application fetches income (budgets) and expense totals from the database filtered by the user's ID and chosen date range.</p></div>
                        <div><h4 class="text-lg font-semibold text-blue-700">Step 3: Financial Feature Extraction</h4><p class="text-gray-700">Five features are computed: Expense Ratio, Profit Margin, Log of Expenses, Log of Budget, and Conservative Ratio.</p></div>
                        <div><h4 class="text-lg font-semibold text-blue-700">Step 4: Training Dataset Simulation</h4><p class="text-gray-700">Three scenarios are generated: current, +20% expense (worse), and -20% expense (better).</p></div>
                        <div><h4 class="text-lg font-semibold text-blue-700">Step 5: Neural Network Initialization</h4><p class="text-gray-700">A BPNN is initialised with 5 input neurons, 6 hidden neurons (Leaky ReLU), and 1 output neuron (Sigmoid).</p></div>
                        <div>
                            <h4 class="text-lg font-semibold text-blue-700">Step 6: Network Training with Backpropagation and Adam Optimizer</h4>
                            <p class="text-gray-700">The neural network is trained for 3000 epochs using the Adam optimizer. Trained weights are cached in the session for the same date range.</p>
                            <img src="assets/images/bpnn.png" alt="BPNN Diagram" class="my-2 w-124 mx-auto">
                        </div>
                        <div>
                            <h4 class="text-lg font-semibold text-blue-700">Step 7: Making the Prediction</h4>
                            <p class="text-gray-700">A forward pass produces a failure risk percentage.</p>
                            <img src="assets/images/riskpercentage.png" alt="Risk Interpretation" class="my-2 w-full w-[400px] mx-auto">
                            <ul class="list-disc list-inside text-gray-600">
                                <li><strong>Critical Risk (&gt;75%):</strong> Immediate action required</li>
                                <li><strong>High Risk (60–75%):</strong> Significant improvements needed</li>
                                <li><strong>Moderate Risk (45–60%):</strong> Recommended adjustments</li>
                                <li><strong>Low Risk (30–45%):</strong> Maintain current practices</li>
                                <li><strong>Very Low Risk (≤30%):</strong> Healthy financial status</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Prediction Financial Report Card -->
            <div class="mt-10 bg-white p-6 rounded shadow">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-700 mb-6">Prediction <span class="text-[#4b6cb7]">Financial Report</span></h2>
                        <span class="text-gray-600 text-sm">Generate a comprehensive report of the prediction within a selected date range.</span>
                    </div>
                    <img src="assets/images/book.png" alt="Report illustration" class="h-24 w-auto">
                </div>

                <?php if (!empty($predictionLevel)): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="p-4 bg-white rounded-lg shadow border-l-4 <?php echo $percentage > 60 ? 'border-red-500' : 'border-green-500'; ?>">
                            <h4 class="text-sm font-semibold text-gray-500 uppercase">Total Risk Level</h4>
                            <p class="text-2xl font-bold <?php echo htmlspecialchars($predictionColor, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($predictionLevel, ENT_QUOTES, 'UTF-8'); ?>
                                (<?php echo number_format($percentage, 2); ?>%)
                            </p>
                        </div>
                        <?php if ($targetProfit > 0): ?>
                            <div class="p-4 bg-white rounded-lg shadow border-l-4 <?php echo $feasibilityScore > 50 ? 'border-blue-500' : 'border-yellow-500'; ?>">
                                <h4 class="text-sm font-semibold text-gray-500 uppercase">Goal Feasibility Score</h4>
                                <p class="text-2xl font-bold text-blue-700">
                                    <?php echo htmlspecialchars($feasibilityStatus, ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo (int) min(100, max(0, $feasibilityScore)); ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="alert-info mb-4 p-4 bg-blue-50 text-blue-800 rounded border border-blue-200">
                        <p class="leading-relaxed"><?php echo $predictionExplanation; ?></p>
                    </div>
                <?php endif; ?>

                <button onclick="printPredictionReport()"
                    class="btn btn-edit px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                    Generate Prediction Report
                </button>

                <!-- Overall Financial Summary -->
                <section class="overall-summary container mx-auto mt-8">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                        <h2 class="text-2xl font-semibold text-gray-700">Overall <span class="text-[#4b6cb7]">Financial Statement</span></h2>
                        <?php
                        $hasDateFilter = !empty($startDate) && !empty($endDate);
                        $periodLabel   = $hasDateFilter
                            ? date('d M Y', strtotime($startDate)) . ' — ' . date('d M Y', strtotime($endDate))
                            : 'All time';
                        ?>
                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-[#4b6cb7] bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-full">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="summary-card bg-white rounded-lg shadow-md p-6 flex items-center space-x-4">
                            <div class="p-4 bg-blue-100 rounded-full text-blue-600">
                                <i class="fas fa-wallet text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="summary-title text-sm font-semibold text-gray-500 uppercase tracking-wide">Total Income</h3>
                                <p class="summary-value text-xl font-bold text-[#182848]">RM <?php echo number_format($totalBudget, 2); ?></p>
                            </div>
                        </div>
                        <div class="summary-card bg-white rounded-lg shadow-md p-6 flex items-center space-x-4">
                            <div class="p-4 bg-red-100 rounded-full text-red-600">
                                <i class="fas fa-coins text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="summary-title text-sm font-semibold text-gray-500 uppercase tracking-wide">Total Expense</h3>
                                <p class="summary-value text-xl font-bold text-[#182848]">RM <?php echo number_format($totalExpense, 2); ?></p>
                            </div>
                        </div>
                        <?php
                        $profitPositive    = $totalProfit >= 0;
                        $profitBorderClass = $profitPositive ? '' : 'border-l-4 border-red-400';
                        $profitIconBg      = $profitPositive ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600';
                        $profitValueClass  = $profitPositive ? 'text-[#182848]' : 'text-red-600';
                        ?>
                        <div class="summary-card bg-white rounded-lg shadow-md p-6 flex items-center space-x-4 <?php echo $profitBorderClass; ?>">
                            <div class="p-4 <?php echo $profitIconBg; ?> rounded-full">
                                <i class="fas fa-chart-line text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="summary-title text-sm font-semibold text-gray-500 uppercase tracking-wide">Net Profit</h3>
                                <p class="summary-value text-xl font-bold <?php echo $profitValueClass; ?>">RM <?php echo number_format($totalProfit, 2); ?></p>
                                <?php if (!$profitPositive): ?>
                                    <p class="text-xs text-red-500 mt-0.5">Expenses exceed income</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($totalBudget > 0): ?>
                    <?php
                    $expRatio      = min(100, round(($totalExpense / $totalBudget) * 100, 1));
                    $barColor      = $expRatio > 90 ? 'bg-red-500' : ($expRatio > 70 ? 'bg-orange-400' : 'bg-green-500');
                    $barLabel      = $expRatio > 90 ? 'Critical' : ($expRatio > 70 ? 'Watch' : 'Healthy');
                    $barLabelColor = $expRatio > 90 ? 'text-red-600' : ($expRatio > 70 ? 'text-orange-500' : 'text-green-600');
                    ?>
                    <div class="mt-5 p-4 bg-gray-50 rounded-lg border border-gray-200">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                                <i class="fas fa-tachometer-alt mr-1"></i> Expense-to-Income Ratio
                            </span>
                            <span class="text-xs font-bold <?php echo $barLabelColor; ?>">
                                <?php echo $expRatio; ?>% — <?php echo $barLabel; ?>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="<?php echo $barColor; ?> h-2.5 rounded-full transition-all" style="width:<?php echo $expRatio; ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1.5">
                            For every RM1.00 earned, RM<?php echo number_format($totalBudget > 0 ? $totalExpense / $totalBudget : 0, 2); ?> is spent.
                        </p>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Category-wise Summary -->
                <div class="mt-10 bg-white">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                        <h2 class="text-2xl font-semibold text-gray-700">Category-wise <span class="text-[#4b6cb7]">Financial Summary</span></h2>
                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-[#4b6cb7] bg-blue-50 border border-blue-200 px-3 py-1.5 rounded-full">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <section class="category-summary container mx-auto mt-4">
                        <?php
                        $totalSales  = array_sum(array_column(array_filter($categoryRows, fn($r) => $r['category_type'] === 'Sales'),   'total_budget'));
                        $totalExpCat = array_sum(array_column(array_filter($categoryRows, fn($r) => $r['category_type'] === 'Expense'), 'total_expense'));
                        $salesRows   = array_filter($categoryRows, fn($r) => $r['category_type'] === 'Sales'   && (float)$r['total_budget']  > 0);
                        $expenseRows = array_filter($categoryRows, fn($r) => $r['category_type'] === 'Expense' && (float)$r['total_expense'] > 0);
                        $hasAny      = !empty($salesRows) || !empty($expenseRows);
                        ?>
                        <?php if (!$hasAny): ?>
                            <p class="text-gray-400 text-sm py-4">No category data for this period.</p>
                        <?php else: ?>

                        <?php if (!empty($salesRows)): ?>
                        <div class="mb-6">
                            <p class="text-xs font-bold text-blue-600 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-blue-500"></span> Income Categories
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($salesRows as $category):
                                    $catName   = $category['category_name'];
                                    $catBudget = (float) $category['total_budget'];
                                    $sharePct  = $totalSales > 0 ? round(($catBudget / $totalSales) * 100, 1) : 0;
                                ?>
                                <div class="category-card bg-white rounded-lg shadow-sm border-l-4 border-blue-400 p-4"
                                     data-type="Sales"
                                     data-name="<?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>"
                                     data-amount="<?php echo number_format($catBudget, 2); ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <h5 class="category-title text-sm font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>
                                        </h5>
                                        <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Sales</span>
                                    </div>
                                    <p class="category-value text-lg font-bold text-[#182848]">
                                        RM <?php echo number_format($catBudget, 2); ?>
                                    </p>
                                    <div class="mt-2">
                                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                                            <span>Share of total income</span>
                                            <span class="font-semibold text-blue-500"><?php echo $sharePct; ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                                            <div class="bg-blue-400 h-1.5 rounded-full" style="width:<?php echo $sharePct; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($expenseRows)): ?>
                        <div>
                            <p class="text-xs font-bold text-red-600 uppercase tracking-widest mb-3 flex items-center gap-2">
                                <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span> Expense Categories
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($expenseRows as $category):
                                    $catName    = $category['category_name'];
                                    $catExpense = (float) $category['total_expense'];
                                    $sharePct   = $totalExpCat > 0 ? round(($catExpense / $totalExpCat) * 100, 1) : 0;
                                ?>
                                <div class="category-card bg-white rounded-lg shadow-sm border-l-4 border-red-400 p-4"
                                     data-type="Expense"
                                     data-name="<?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>"
                                     data-amount="<?php echo number_format($catExpense, 2); ?>">
                                    <div class="flex justify-between items-start mb-2">
                                        <h5 class="category-title text-sm font-semibold text-gray-800">
                                            <?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?>
                                        </h5>
                                        <span class="text-xs font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded-full">Expense</span>
                                    </div>
                                    <p class="category-value text-lg font-bold text-[#182848]">
                                        RM <?php echo number_format($catExpense, 2); ?>
                                    </p>
                                    <div class="mt-2">
                                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                                            <span>Share of total expenses</span>
                                            <span class="font-semibold text-red-500"><?php echo $sharePct; ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                                            <div class="bg-red-400 h-1.5 rounded-full" style="width:<?php echo $sharePct; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php endif; ?>
                    </section>
                </div>
            </div>

        </div>
    </div>

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script>
        /* ---------- Sidebar — dual-mode with no-flash restore ---------- */
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar       = document.querySelector('.sidebar');
            const mainContent   = document.querySelector('.main-content');
            if (!sidebarToggle) return;

            const isMobile = () => window.innerWidth <= 768;

            if (!isMobile() && localStorage.getItem('sidebarCollapsed') === 'true') {
                sidebar.style.transition     = 'none';
                mainContent.style.transition = 'none';
                sidebar.classList.add('collapsed');
                mainContent.classList.add('collapsed');
                requestAnimationFrame(() => {
                    sidebar.style.transition     = '';
                    mainContent.style.transition = '';
                });
            }

            sidebarToggle.addEventListener('click', function () {
                if (isMobile()) {
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('active');
                } else {
                    const isCollapsed = sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('collapsed', isCollapsed);
                    localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
                }
            });

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
        /* ---------- Guidelines modal ---------- */
        const modal   = document.getElementById('modal');
        const content = document.getElementById('content');

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

        window.addEventListener('click', function (e) {
            if (e.target === modal) toggleModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') toggleModal();
        });
    </script>

    <script>
        /* ---------- Date validation → enable/disable predict button ---------- */
        function validateDates() {
            const startDate  = document.getElementById('start_date').value;
            const endDate    = document.getElementById('end_date').value;
            const predictBtn = document.getElementById('predictBtn');
            if (startDate && endDate) {
                predictBtn.disabled = false;
                predictBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                predictBtn.disabled = true;
                predictBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
        document.getElementById('start_date').addEventListener('change', validateDates);
        document.getElementById('end_date').addEventListener('change',   validateDates);
        document.addEventListener('DOMContentLoaded', validateDates);
    </script>

    <script>
        /* ---------- Generate Prediction Report ----------
           Reads data-type / data-name / data-amount attributes added to each
           category card — no fragile innerText pattern-matching needed.
        ------------------------------------------------------------------ */
        function printPredictionReport() {
            const predictionBox = document.querySelector('.alert-info');
            const summaryCards  = document.querySelectorAll('.overall-summary .summary-card');

            // ── Overall summary table ──────────────────────────────────────
            let overallRows = '';
            summaryCards.forEach(card => {
                const label = card.querySelector('.summary-title')?.innerText.trim() || '';
                const value = card.querySelector('.summary-value')?.innerText.trim()  || '';
                overallRows += `<tr><td>${label}</td><td>${value}</td></tr>`;
            });

            const targetProfitValue = parseFloat(document.getElementById('target_profit').value) || 0;
            if (targetProfitValue > 0) {
                overallRows += `<tr style="background:#f9fafb;">
                    <td><strong>Target Profit</strong></td>
                    <td><strong>RM ${targetProfitValue.toLocaleString(undefined, { minimumFractionDigits: 2 })}</strong></td>
                </tr>`;
            }

            const overallTable = `
                <h3>Overall Financial Summary</h3>
                <table border="1" cellspacing="0" cellpadding="8" style="width:100%; margin-bottom:20px;">
                    <thead style="background:#f0f0f0;"><tr><th>Type</th><th>Amount (RM)</th></tr></thead>
                    <tbody>${overallRows}</tbody>
                </table>`;

            // ── Category breakdown — read from data-* attributes ──────────
            let salesRows   = '';
            let expenseRows = '';

            document.querySelectorAll('.category-summary .category-card').forEach(card => {
                const type   = card.dataset.type   || '';
                const name   = card.dataset.name   || '';
                const amount = card.dataset.amount  || '';
                if (type === 'Sales') {
                    salesRows   += `<tr><td>${name}</td><td>RM ${amount}</td></tr>`;
                } else if (type === 'Expense') {
                    expenseRows += `<tr><td>${name}</td><td>RM ${amount}</td></tr>`;
                }
            });

            const salesTable = `
                <h3>Income Breakdown by Category</h3>
                <table border="1" cellspacing="0" cellpadding="8" style="width:100%; margin-bottom:20px;">
                    <thead style="background:#e6f0ff;"><tr><th>Category</th><th>Total (RM)</th></tr></thead>
                    <tbody>${salesRows || '<tr><td colspan="2">No income category data available.</td></tr>'}</tbody>
                </table>`;

            const expenseTable = `
                <h3>Expense Breakdown by Category</h3>
                <table border="1" cellspacing="0" cellpadding="8" style="width:100%; margin-bottom:20px;">
                    <thead style="background:#ffe6e6;"><tr><th>Category</th><th>Total (RM)</th></tr></thead>
                    <tbody>${expenseRows || '<tr><td colspan="2">No expense category data available.</td></tr>'}</tbody>
                </table>`;

            const printContent = `
                <html>
                <head>
                    <title>Business Failure Prediction Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h2, h3 { color: #333; }
                        table { border-collapse: collapse; width: 100%; }
                        th, td { text-align: left; padding: 8px; }
                        th { background-color: #f9f9f9; }
                        .alert-info { background:#eff6ff; border:1px solid #bfdbfe; padding:12px; border-radius:6px; margin-bottom:16px; color:#1e40af; }
                    </style>
                </head>
                <body>
                    <h2>Business Failure Prediction Report</h2>
                    ${predictionBox ? predictionBox.outerHTML : ''}
                    ${overallTable}
                    ${salesTable}
                    ${expenseTable}
                </body>
                </html>`;

            const printWindow = window.open('', '', 'width=900,height=650');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }
    </script>

</body>
</html>