<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

// Check if loan_id parameter exists
if (!isset($_GET['loan_id']) || empty($_GET['loan_id'])) {
    echo json_encode(['success' => false, 'message' => 'Loan ID is required']);
    exit;
}

$loan_id = intval($_GET['loan_id']);

// Query to get the late fee from loan_types based on the loan ID
$sql = "SELECT lt.late_fee 
        FROM loans l
        JOIN loan_types lt ON l.loan_type_id = lt.id
        WHERE l.id = ?";
        
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $loan_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'late_fee' => $row['late_fee']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Loan not found'
    ]);
}

$stmt->close();
$conn->close();
?>