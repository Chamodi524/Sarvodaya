<?php
// Start session
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

// Get member ID from request
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;

// Validate member ID
if ($member_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
    exit;
}

// Get member details
$member_query = "SELECT m.id, m.name, m.account_type, at.name as account_type_name 
                FROM members m
                LEFT JOIN account_types at ON m.account_type = at.id
                WHERE m.id = ?";

$stmt = $conn->prepare($member_query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Member not found']);
    exit;
}

$member_data = $result->fetch_assoc();
$account_type_id = $member_data['account_type'];
$account_type_name = $member_data['account_type_name'] ?? 'Unknown Account Type';

// Calculate current balance
$balance_sql = "SELECT COALESCE(SUM(CASE 
                 WHEN transaction_type IN ('DEPOSIT', 'INTEREST') THEN amount 
                 WHEN transaction_type IN ('WITHDRAWAL', 'FEE', 'ADJUSTMENT') THEN -amount 
                 ELSE 0 END), 0) AS balance 
                FROM savings_transactions 
                WHERE member_id = ? AND account_type_id = ?";

$stmt = $conn->prepare($balance_sql);
$stmt->bind_param("ii", $member_id, $account_type_id);
$stmt->execute();
$balance_result = $stmt->get_result();
$balance_row = $balance_result->fetch_assoc();
$current_balance = floatval($balance_row['balance']);

// Return result as JSON
echo json_encode([
    'success' => true,
    'member_id' => $member_id,
    'member_name' => $member_data['name'],
    'account_type' => $account_type_name,
    'balance' => number_format($current_balance, 2, '.', '')
]);

// Close the connection
$stmt->close();
$conn->close();
?>