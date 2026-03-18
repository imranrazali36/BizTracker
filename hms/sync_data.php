function syncFinancialData($con, $userId) {
    $query = "
        SELECT 
            DATE_FORMAT(b.date_created, '%Y-%m') AS month,
            COALESCE(SUM(b.amount), 0) AS total_budget,
            (
                SELECT COALESCE(SUM(e.amount), 0)
                FROM expenses e
                WHERE DATE_FORMAT(e.date_created, '%Y-%m') = DATE_FORMAT(b.date_created, '%Y-%m') AND e.user_id = $userId
            ) AS total_expense,
            (
                SELECT COUNT(DISTINCT c.id)
                FROM categories c
                WHERE c.user_id = $userId
            ) AS expense_category_count
        FROM budgets b
        WHERE b.user_id = $userId
        GROUP BY DATE_FORMAT(b.date_created, '%Y-%m')
    ";
    
    $result = mysqli_query($con, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        $month = $row['month'];
        $budget = $row['total_budget'];
        $expense = $row['total_expense'];
        $categories = $row['expense_category_count'];

        // Insert or update
        $check = mysqli_query($con, "SELECT id FROM financial_data WHERE user_id = $userId AND month = '$month'");
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($con, "UPDATE financial_data 
                SET total_budget = $budget, total_expense = $expense, expense_category_count = $categories 
                WHERE user_id = $userId AND month = '$month'");
        } else {
            mysqli_query($con, "INSERT INTO financial_data (user_id, month, total_budget, total_expense, expense_category_count) 
                VALUES ($userId, '$month', $budget, $expense, $categories)");
        }
    }
}
