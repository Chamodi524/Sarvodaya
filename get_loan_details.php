<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

// Get parameters from request
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$loan_type_id = isset($_GET['loan_type_id']) ? intval($_GET['loan_type_id']) : 0;

// Validate parameters
if ($member_id <= 0 || $loan_type_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid member ID or loan type']);
    exit;
}

// Query to get loan details
// Using the correct column name 'amount' instead of 'loan_amount'
$sql = "SELECT l.amount AS loan_amount, lt.interest_rate 
        FROM loans l
        JOIN loan_types lt ON l.loan_type_id = lt.id
        WHERE l.member_id = ? AND l.loan_type_id = ? 
        ORDER BY l.id DESC LIMIT 1";

// Prepare and execute the statement
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $member_id, $loan_type_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $loan_data = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'loan_amount' => floatval($loan_data['loan_amount']),
        'interest_rate' => floatval($loan_data['interest_rate'])
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No loan found for this member and loan type'
    ]);
}

// Close connections
$stmt->close();
$conn->close();
?>