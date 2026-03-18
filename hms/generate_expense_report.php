<?php
session_start();
include('include/config.php');
include('include/checklogin.php');
check_login();

// ============================================================
// USER — cast to int, never trust session raw value in SQL
// ============================================================
$userId = (int) $_SESSION['id'];
if ($userId <= 0) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// ============================================================
// DATE RANGE — start_date / end_date are OPTIONAL.
//   All time  : neither param is sent  → no date filter
//   This month: expense.php sends computed dates
//   This year : expense.php sends computed dates
//   Custom    : user picks dates in the modal
// ============================================================
$startDate = trim($_GET['start_date'] ?? '');
$endDate   = trim($_GET['end_date']   ?? '');

// Validate dates if provided
if ($startDate !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $startDate);
    if (!$d || $d->format('Y-m-d') !== $startDate) {
        $startDate = '';
    }
}
if ($endDate !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $endDate);
    if (!$d || $d->format('Y-m-d') !== $endDate) {
        $endDate = '';
    }
}

// ============================================================
// OUTPUT FORMAT — ?format=csv triggers download, else print
// ============================================================
$format = strtolower(trim($_GET['format'] ?? 'print'));
$isCsv  = ($format === 'csv');

// ============================================================
// BUILD QUERY — prepared statement, date filter only if dates
//              are actually provided
// ============================================================
$sql        = "SELECT e.id, e.amount, e.remarks, e.date_created,
                      c.category_name
               FROM expenses e
               JOIN categories c ON e.category_id = c.id
               WHERE e.user_id = ?
                 AND c.category_type = 'Expense'";
$bindTypes  = "i";
$bindValues = [$userId];

if ($startDate !== '' && $endDate !== '') {
    $sql        .= " AND DATE(e.date_created) BETWEEN ? AND ?";
    $bindTypes  .= "ss";
    $bindValues[] = $startDate;
    $bindValues[] = $endDate;
} elseif ($startDate !== '') {
    $sql        .= " AND DATE(e.date_created) >= ?";
    $bindTypes  .= "s";
    $bindValues[] = $startDate;
} elseif ($endDate !== '') {
    $sql        .= " AND DATE(e.date_created) <= ?";
    $bindTypes  .= "s";
    $bindValues[] = $endDate;
}

$sql .= " ORDER BY e.date_created ASC";

$stmt = $con->prepare($sql);
$stmt->bind_param($bindTypes, ...$bindValues);
$stmt->execute();
$result = $stmt->get_result();
$rows   = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ============================================================
// RANGE LABEL — human-readable string for both formats
// ============================================================
if ($startDate !== '' && $endDate !== '') {
    $rangeLabel = date('d M Y', strtotime($startDate)) . ' – ' . date('d M Y', strtotime($endDate));
} elseif ($startDate !== '') {
    $rangeLabel = 'From ' . date('d M Y', strtotime($startDate));
} elseif ($endDate !== '') {
    $rangeLabel = 'Up to ' . date('d M Y', strtotime($endDate));
} else {
    $rangeLabel = 'All time';
}

// ============================================================
// CSV OUTPUT — streams a downloadable .csv file
// ============================================================
if ($isCsv) {
    $safeName = 'expense_' . ($startDate ?: 'all') . ($endDate ? '_to_' . $endDate : '') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $safeName . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM so Excel opens it correctly
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // Meta row
    fputcsv($out, ['BizTracker Expense Report', $rangeLabel, 'Generated: ' . date('d M Y H:i')]);
    fputcsv($out, []); // blank row

    // Header row
    fputcsv($out, ['ID', 'Category', 'Amount (RM)', 'Remarks', 'Date']);

    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float) $row['amount'];
        fputcsv($out, [
            (int) $row['id'],
            $row['category_name'],
            number_format((float) $row['amount'], 2, '.', ''),
            $row['remarks'],
            date('d M Y, H:i', strtotime($row['date_created'])),
        ]);
    }

    // Total row
    fputcsv($out, []);
    fputcsv($out, ['', 'TOTAL', number_format($total, 2, '.', ''), '', '']);

    fclose($out);
    exit;
}

// ============================================================
// PRINT / HTML OUTPUT
// ============================================================
$grandTotal = 0.0;
foreach ($rows as $row) {
    $grandTotal += (float) $row['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Report | BizTracker</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
        body { font-family: 'Inter', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-white p-10">

    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold">Expense Report</h1>
        <p class="text-gray-600 mt-1 text-sm"><?php echo htmlspecialchars($rangeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
        <p class="text-gray-400 text-xs mt-1">Generated: <?php echo date('d M Y, H:i'); ?></p>
    </div>

    <?php if (empty($rows)): ?>
        <p class="text-center text-gray-500 py-12">No expense records found for this date range.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-300 text-sm">
            <thead class="bg-gray-100 text-gray-800">
                <tr>
                    <th class="py-2 px-4 border text-center">No</th>
                    <th class="py-2 px-4 border text-center">ID</th>
                    <th class="py-2 px-4 border text-center">Category</th>
                    <th class="py-2 px-4 border text-center">Amount (RM)</th>
                    <th class="py-2 px-4 border text-center">Remarks</th>
                    <th class="py-2 px-4 border text-center">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $row): ?>
                <tr class="text-center border-t">
                    <td class="py-2 px-4 border"><?php echo $i + 1; ?></td>
                    <td class="py-2 px-4 border"><?php echo (int)$row['id']; ?></td>
                    <td class="py-2 px-4 border"><?php echo htmlspecialchars($row['category_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="py-2 px-4 border">RM <?php echo number_format((float)$row['amount'], 2); ?></td>
                    <td class="py-2 px-4 border"><?php echo htmlspecialchars($row['remarks'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="py-2 px-4 border"><?php echo date('d M Y, H:i', strtotime($row['date_created'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="bg-gray-100 font-semibold">
                    <td colspan="3" class="py-2 px-4 border text-right">Total</td>
                    <td class="py-2 px-4 border text-center">RM <?php echo number_format($grandTotal, 2); ?></td>
                    <td colspan="2" class="py-2 px-4 border"></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="mt-6 text-center no-print">
        <button onclick="window.print()"
                class="px-6 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            Print Report
        </button>
        <button onclick="window.close()"
                class="ml-3 px-6 py-2 bg-gray-400 text-white rounded hover:bg-gray-500">
            Close
        </button>
    </div>

</body>
</html>