<?php
/**
 * view_receipt.php
 * Secure file proxy — verifies the requesting user owns the record
 * before streaming the uploaded receipt. Never exposes direct file paths.
 *
 * Usage:
 *   Budget:  view_receipt.php?id=123&type=budget
 *   Expense: view_receipt.php?id=456&type=expense
 */
session_start();
include('include/config.php');
include('include/checklogin.php');
check_login();

$userId = (int) $_SESSION['id'];
if ($userId <= 0) {
    http_response_code(403);
    exit('Access denied.');
}

$id   = (int) ($_GET['id']   ?? 0);
$type = trim($_GET['type'] ?? '');

if ($id <= 0 || !in_array($type, ['budget', 'expense'], true)) {
    http_response_code(400);
    exit('Invalid request.');
}

// Determine table and column based on type
$table = $type === 'budget' ? 'budgets' : 'expenses';

$stmt = $con->prepare(
    "SELECT receipt FROM $table WHERE id = ? AND user_id = ?"
);
$stmt->bind_param("ii", $id, $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || empty($row['receipt'])) {
    http_response_code(404);
    exit('File not found.');
}

// Resolve file path — use basename() to prevent directory traversal
$filename = basename($row['receipt']);
$filePath = __DIR__ . '/uploads/' . $filename;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found on server.');
}

// Detect real MIME type
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($filePath);

// Only allow expected MIME types
$allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($mimeType, $allowedMimes, true)) {
    http_response_code(403);
    exit('File type not permitted.');
}

// Stream the file to the browser
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($filePath);
exit;