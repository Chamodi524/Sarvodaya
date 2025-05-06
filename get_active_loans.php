<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die(json_encode([
        'success' => false, 
        'message' => "Connection failed: " . $conn->connect_error
    ]));
}

// Set content type to JSON
header('Content-Type: application/json');

// Check if member_id parameter is provided
if (!isset($_GET['member_id']) || empty($_GET['member_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Member ID is required'
    ]);
    exit;
}

$member_id = intval($_GET['member_id']);

try {
    // Query to get active loans for this member along with loan type names
    // Note: We're joining with loan_types to get the loan name and using the loans.id as loan_id
    $sql = "SELECT l.id, l.loan_type_id, l.amount, l.total_repayment_amount, lt.loan_name 
            FROM loans l
            JOIN loan_types lt ON l.loan_type_id = lt.id
            WHERE l.member_id = ? AND l.status = 'active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $loans = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $loans[] = [
                'id' => $row['id'],  // This is the loan_id that should be stored in receipts.loan_id
                'loan_type_id' => $row['loan_type_id'],
                'amount' => $row['amount'],
                'remaining_amount' => $row['total_repayment_amount'],
                'loan_name' => $row['loan_name']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'loans' => $loans
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'loans' => []
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    $conn->close();
}
?>